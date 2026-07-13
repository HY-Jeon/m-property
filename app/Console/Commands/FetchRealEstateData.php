<?php

namespace App\Console\Commands;

use App\Models\MonthlyAggregate;
use App\Models\Transaction;
use App\Services\MolitApiService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

#[Signature('realestate:fetch {--month= : 조회할 계약월(YYYYMM), 기본값은 전월}')]
#[Description('국토교통부 아파트매매 실거래자료를 수집해 대상 법정동만 저장하고 월별 집계를 갱신한다')]
class FetchRealEstateData extends Command
{
    public function handle(MolitApiService $molit): int
    {
        $dealYmd = $this->option('month') ?? Carbon::now()->subMonth()->format('Ym');

        foreach (config('realestate.regions') as $lawdCd => $region) {
            $this->info("[{$region['name']}] {$dealYmd} 실거래 자료 조회 중 (LAWD_CD={$lawdCd})");

            try {
                $items = $molit->fetchAll($lawdCd, $dealYmd);
            } catch (\Throwable $e) {
                $this->error("[{$region['name']}] 조회 실패: {$e->getMessage()}");

                continue;
            }

            $targetDongs = $region['target_dongs'];
            $saved = 0;

            foreach ($items as $item) {
                $legalDong = $item['법정동'] ?? '';

                if (! in_array($legalDong, $targetDongs, true)) {
                    continue;
                }

                $this->saveTransaction($lawdCd, $item);
                $saved++;
            }

            $this->info("[{$region['name']}] {$saved}건 저장 (대상 법정동: ".implode(', ', $targetDongs).')');

            foreach ($targetDongs as $dong) {
                $this->refreshMonthlyAggregate($lawdCd, $dong, $dealYmd);
            }
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<string, string>  $item
     */
    private function saveTransaction(string $lawdCd, array $item): void
    {
        $legalDong = $item['법정동'];
        $apartmentName = $item['아파트'] ?? '';
        $jibun = $item['지번'] ?? null;
        $exclusiveArea = (float) ($item['전용면적'] ?? 0);
        $floor = isset($item['층']) && $item['층'] !== '' ? (int) $item['층'] : null;
        $buildYear = isset($item['건축년도']) && $item['건축년도'] !== '' ? (int) $item['건축년도'] : null;
        $dealDate = sprintf(
            '%04d-%02d-%02d',
            (int) ($item['년'] ?? 0),
            (int) ($item['월'] ?? 0),
            (int) ($item['일'] ?? 0)
        );
        $dealAmount = (int) str_replace(',', '', trim($item['거래금액'] ?? '0'));
        $dealType = ($item['거래유형'] ?? '') !== '' ? $item['거래유형'] : null;
        $isCancelled = ($item['해제여부'] ?? '') === 'O';

        $rawHash = sha1(implode('|', [
            $lawdCd, $legalDong, $apartmentName, $jibun, $exclusiveArea, $floor, $dealDate, $dealAmount,
        ]));

        Transaction::updateOrCreate(
            ['raw_hash' => $rawHash],
            [
                'region_code' => $lawdCd,
                'legal_dong' => $legalDong,
                'apartment_name' => $apartmentName,
                'jibun' => $jibun,
                'exclusive_area' => $exclusiveArea,
                'floor' => $floor,
                'build_year' => $buildYear,
                'deal_date' => $dealDate,
                'deal_amount' => $dealAmount,
                'deal_type' => $dealType,
                'is_cancelled' => $isCancelled,
            ]
        );
    }

    private function refreshMonthlyAggregate(string $lawdCd, string $legalDong, string $yearMonth): void
    {
        $start = Carbon::createFromFormat('Ym', $yearMonth)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $stats = Transaction::query()
            ->where('region_code', $lawdCd)
            ->where('legal_dong', $legalDong)
            ->where('is_cancelled', false)
            ->whereBetween('deal_date', [$start->toDateString(), $end->toDateString()])
            ->selectRaw('COUNT(*) as count, AVG(deal_amount) as avg_amount, MIN(deal_amount) as min_amount, MAX(deal_amount) as max_amount')
            ->first();

        if (! $stats || $stats->count === 0) {
            return;
        }

        MonthlyAggregate::updateOrCreate(
            ['legal_dong' => $legalDong, 'year_month' => $yearMonth],
            [
                'region_code' => $lawdCd,
                'transaction_count' => $stats->count,
                'avg_deal_amount' => (int) round($stats->avg_amount),
                'min_deal_amount' => $stats->min_amount,
                'max_deal_amount' => $stats->max_amount,
            ]
        );
    }
}
