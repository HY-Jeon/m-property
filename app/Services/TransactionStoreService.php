<?php

namespace App\Services;

use App\Models\MonthlyAggregate;
use App\Models\Transaction;
use Illuminate\Support\Carbon;

/**
 * 실거래 저장·집계 로직 (FetchRealEstateData 커맨드에서 추출).
 * 전국 수집용: 법정동 필터 없이 응답 전체를 저장한다.
 */
class TransactionStoreService
{
    /**
     * 한 지역·한 계약월의 API 응답 전체를 저장하고 월별 집계를 갱신한다.
     *
     * @param  array<int, array<string, string>>  $items
     * @return array{saved: int, cancelled: int}
     */
    public function storeMonth(string $lawdCd, string $dealYmd, array $items): array
    {
        // 해제(취소) 신고 건은 cdealType만 채워져 별도 항목으로 내려온다.
        // 정상 건을 먼저 저장한 뒤 해제 건을 나중에 처리해 순서와 무관하게 반영되도록 한다.
        $normal = array_values(array_filter($items, static fn (array $i) => trim($i['cdealType'] ?? '') === ''));
        $cancelled = array_values(array_filter($items, static fn (array $i) => trim($i['cdealType'] ?? '') !== ''));

        foreach ($normal as $item) {
            $this->saveTransaction($lawdCd, $item);
        }

        foreach ($cancelled as $item) {
            $this->markCancelled($lawdCd, $item);
        }

        foreach ($this->distinctDongs($items) as $dong) {
            $this->refreshMonthlyAggregate($lawdCd, $dong, $dealYmd);
        }

        return ['saved' => count($normal), 'cancelled' => count($cancelled)];
    }

    /**
     * @param  array<int, array<string, string>>  $items
     * @return array<int, string>
     */
    private function distinctDongs(array $items): array
    {
        return array_values(array_unique(array_filter(
            array_map(static fn (array $i) => $i['umdNm'] ?? '', $items)
        )));
    }

    /**
     * @param  array<string, string>  $item
     */
    private function saveTransaction(string $lawdCd, array $item): void
    {
        Transaction::updateOrCreate(
            ['raw_hash' => $this->naturalKeyHash($lawdCd, $item)],
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
                'is_cancelled' => false,
            ]
        );
    }

    /**
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
            ['region_code' => $lawdCd, 'legal_dong' => $legalDong, 'year_month' => $yearMonth],
            [
                'transaction_count' => $stats->count,
                'avg_deal_amount' => (int) round($stats->avg_amount),
                'min_deal_amount' => $stats->min_amount,
                'max_deal_amount' => $stats->max_amount,
            ]
        );
    }
}
