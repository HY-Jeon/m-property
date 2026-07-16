<?php

namespace App\Console\Commands;

use App\Models\MonthlyAggregate;
use App\Models\Transaction;
use App\Services\MolitApiService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

#[Signature('realestate:fetch
    {--month= : 조회할 계약월(YYYYMM) 단일 지정. 생략 시 realestate.lookback_months 설정만큼 최근 개월을 모두 재조회}
    {--from= : 백필 시작 계약월(YYYYMM). --to와 함께 여러 달을 한 번에 수집}
    {--to= : 백필 종료 계약월(YYYYMM). 생략 시 --from과 동일한 한 달만 조회}')]
#[Description('국토교통부 아파트매매 실거래자료를 수집해 대상 법정동만 저장하고 월별 집계를 갱신한다. 뒤늦게 등록되는 해제(취소) 신고를 놓치지 않도록 최근 개월을 매번 재조회한다')]
class FetchRealEstateData extends Command
{
    public function handle(MolitApiService $molit): int
    {
        try {
            $months = $this->targetMonths();
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        foreach ($months as $dealYmd) {
            foreach (config('realestate.regions') as $lawdCd => $region) {
                $this->fetchRegionMonth($molit, (string) $lawdCd, $region, $dealYmd);
            }
        }

        return self::SUCCESS;
    }

    /**
     * @return array<int, string>
     */
    private function targetMonths(): array
    {
        if ($from = $this->option('from')) {
            return $this->monthRange($from, $this->option('to') ?? $from);
        }

        if ($month = $this->option('month')) {
            $this->validateYearMonth($month);

            return [$month];
        }

        $lookback = (int) config('realestate.lookback_months', 3);

        return collect(range(1, $lookback))
            ->map(fn (int $i) => Carbon::now()->subMonths($i)->format('Ym'))
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function monthRange(string $from, string $to): array
    {
        $this->validateYearMonth($from);
        $this->validateYearMonth($to);

        $start = Carbon::createFromFormat('Ym', $from)->startOfMonth();
        $end = Carbon::createFromFormat('Ym', $to)->startOfMonth();

        if ($end->lt($start)) {
            [$start, $end] = [$end, $start];
        }

        $months = [];

        for ($cursor = $start->copy(); $cursor->lte($end); $cursor->addMonth()) {
            $months[] = $cursor->format('Ym');
        }

        return $months;
    }

    private function validateYearMonth(string $ym): void
    {
        if (! preg_match('/^\d{4}(0[1-9]|1[0-2])$/', $ym)) {
            throw new \InvalidArgumentException("계약월 형식이 올바르지 않습니다(YYYYMM): {$ym}");
        }
    }

    /**
     * @param  array{name: string, target_dongs: array<int, string>}  $region
     */
    private function fetchRegionMonth(MolitApiService $molit, string $lawdCd, array $region, string $dealYmd): void
    {
        $this->info("[{$region['name']}] {$dealYmd} 실거래 자료 조회 중 (LAWD_CD={$lawdCd})");

        try {
            $items = $molit->fetchAll($lawdCd, $dealYmd);
        } catch (\Throwable $e) {
            $this->error("[{$region['name']}] {$dealYmd} 조회 실패: {$e->getMessage()}");

            return;
        }

        $targetDongs = $region['target_dongs'];
        $matched = array_values(array_filter(
            $items,
            static fn (array $item) => in_array($item['umdNm'] ?? '', $targetDongs, true)
        ));

        // 해제(취소) 신고 건은 원 거래와 동일한 핵심 필드를 공유한 채 cdealType만 채워져 별도 항목으로 내려온다.
        // API 응답 순서와 무관하게 해제 여부가 항상 반영되도록 정상 건을 먼저 저장한 뒤 해제 건을 나중에 처리한다.
        $normal = array_values(array_filter($matched, static fn (array $item) => trim($item['cdealType'] ?? '') === ''));
        $cancelled = array_values(array_filter($matched, static fn (array $item) => trim($item['cdealType'] ?? '') !== ''));

        foreach ($normal as $item) {
            $this->saveTransaction($lawdCd, $item);
        }

        foreach ($cancelled as $item) {
            $this->markCancelled($lawdCd, $item);
        }

        $saved = count($normal);
        $cancelledCount = count($cancelled);
        $this->info("[{$region['name']}] {$dealYmd}: {$saved}건 저장, {$cancelledCount}건 해제 반영 (대상 법정동: ".implode(', ', $targetDongs).')');

        foreach ($targetDongs as $dong) {
            $this->refreshMonthlyAggregate($lawdCd, $dong, $dealYmd);
        }
    }

    /**
     * @param  array<string, string>  $item
     */
    private function saveTransaction(string $lawdCd, array $item): void
    {
        $legalDong = $item['umdNm'];
        $apartmentName = $item['aptNm'] ?? '';
        $jibun = $item['jibun'] ?? null;
        $exclusiveArea = (float) ($item['excluUseAr'] ?? 0);
        $floor = isset($item['floor']) && $item['floor'] !== '' ? (int) $item['floor'] : null;
        $buildYear = isset($item['buildYear']) && $item['buildYear'] !== '' ? (int) $item['buildYear'] : null;
        $dealDate = $this->dealDate($item);
        $dealAmount = $this->dealAmount($item);
        $dealType = ($item['dealingGbn'] ?? '') !== '' ? $item['dealingGbn'] : null;

        Transaction::updateOrCreate(
            ['raw_hash' => $this->naturalKeyHash($lawdCd, $item)],
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
                'is_cancelled' => false,
            ]
        );
    }

    /**
     * 해제(취소) 신고 건: 원 거래와 동일한 자연키를 가진 기존 행을 찾아 취소 상태만 반영한다.
     * 매칭되는 정상 건이 이번 조회 결과에 없다면(예: 원 계약월과 다른 시점에 해제 반영) 해당 필드만으로 새로 생성한다.
     *
     * @param  array<string, string>  $item
     */
    private function markCancelled(string $lawdCd, array $item): void
    {
        $rawHash = $this->naturalKeyHash($lawdCd, $item);

        $updated = Transaction::where('raw_hash', $rawHash)->update(['is_cancelled' => true]);

        if ($updated > 0) {
            return;
        }

        Transaction::updateOrCreate(
            ['raw_hash' => $rawHash],
            [
                'region_code' => $lawdCd,
                'legal_dong' => $item['umdNm'],
                'apartment_name' => $item['aptNm'] ?? '',
                'jibun' => $item['jibun'] ?? null,
                'exclusive_area' => (float) ($item['excluUseAr'] ?? 0),
                'floor' => isset($item['floor']) && $item['floor'] !== '' ? (int) $item['floor'] : null,
                'build_year' => isset($item['buildYear']) && $item['buildYear'] !== '' ? (int) $item['buildYear'] : null,
                'deal_date' => $this->dealDate($item),
                'deal_amount' => $this->dealAmount($item),
                'deal_type' => ($item['dealingGbn'] ?? '') !== '' ? $item['dealingGbn'] : null,
                'is_cancelled' => true,
            ]
        );
    }

    /**
     * @param  array<string, string>  $item
     */
    private function naturalKeyHash(string $lawdCd, array $item): string
    {
        return sha1(implode('|', [
            $lawdCd,
            $item['umdNm'],
            $item['aptNm'] ?? '',
            $item['jibun'] ?? null,
            (float) ($item['excluUseAr'] ?? 0),
            isset($item['floor']) && $item['floor'] !== '' ? (int) $item['floor'] : null,
            $this->dealDate($item),
            $this->dealAmount($item),
        ]));
    }

    /**
     * @param  array<string, string>  $item
     */
    private function dealDate(array $item): string
    {
        return sprintf(
            '%04d-%02d-%02d',
            (int) ($item['dealYear'] ?? 0),
            (int) ($item['dealMonth'] ?? 0),
            (int) ($item['dealDay'] ?? 0)
        );
    }

    /**
     * @param  array<string, string>  $item
     */
    private function dealAmount(array $item): int
    {
        return (int) str_replace(',', '', trim($item['dealAmount'] ?? '0'));
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
