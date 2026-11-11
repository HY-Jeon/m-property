<?php

namespace App\Console\Commands;

use App\Services\MolitApiService;
use App\Services\TransactionStoreService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

#[Signature('realestate:fetch
    {--month= : 조회할 계약월(YYYYMM) 단일 지정. 생략 시 realestate.lookback_months 설정만큼 최근 개월을 모두 재조회}
    {--from= : 백필 시작 계약월(YYYYMM). --to와 함께 여러 달을 한 번에 수집}
    {--to= : 백필 종료 계약월(YYYYMM). 생략 시 --from과 동일한 한 달만 조회}')]
#[Description('국토교통부 아파트매매 실거래자료를 수집해 대상 법정동만 저장하고 월별 집계를 갱신한다. 뒤늦게 등록되는 해제(취소) 신고를 놓치지 않도록 최근 개월을 매번 재조회한다')]
class FetchRealEstateData extends Command
{
    public function handle(MolitApiService $molit, TransactionStoreService $store): int
    {
        try {
            $months = $this->targetMonths();
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        // 여러 달을 한 번에 백필/재조회할 때 요청이 연달아 몰리면 data.go.kr WAF의
        // 순간 트래픽 차단에 걸릴 수 있어, 첫 요청을 제외하고 호출 사이에 짧은 간격을 둔다.
        $isFirstRequest = true;

        // 한 지역/월이 실패해도 나머지는 계속 수집하되, 스케줄러가 실패를 감지할 수 있도록 종료 코드에 반영한다.
        $hasFailure = false;

        foreach ($months as $dealYmd) {
            foreach (config('realestate.regions') as $lawdCd => $region) {
                if (! $isFirstRequest) {
                    usleep(500_000);
                }
                $isFirstRequest = false;

                if (! $this->fetchRegionMonth($molit, $store, (string) $lawdCd, $region, $dealYmd)) {
                    $hasFailure = true;
                }
            }
        }

        return $hasFailure ? self::FAILURE : self::SUCCESS;
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
     * @return bool 조회에 성공하면 true
     */
    private function fetchRegionMonth(MolitApiService $molit, TransactionStoreService $store, string $lawdCd, array $region, string $dealYmd): bool
    {
        $this->info("[{$region['name']}] {$dealYmd} 실거래 자료 조회 중 (LAWD_CD={$lawdCd})");

        try {
            $items = $molit->fetchAll($lawdCd, $dealYmd);
        } catch (\Throwable $e) {
            $this->error("[{$region['name']}] {$dealYmd} 조회 실패: {$e->getMessage()}");

            Log::error('실거래 자료 조회 실패', [
                'region' => $region['name'],
                'lawd_cd' => $lawdCd,
                'deal_ymd' => $dealYmd,
                'exception' => $e,
            ]);

            return false;
        }

        $targetDongs = $region['target_dongs'];
        $matched = array_values(array_filter(
            $items,
            static fn (array $item) => in_array($item['umdNm'] ?? '', $targetDongs, true)
        ));

        // 거래가 0건인 달에도 대상 법정동 전체의 집계를 갱신하도록 target_dongs를 명시적으로 넘긴다.
        $result = $store->storeMonth($lawdCd, $dealYmd, $matched, $targetDongs);

        $this->info("[{$region['name']}] {$dealYmd}: {$result['saved']}건 저장, {$result['cancelled']}건 해제 반영 (대상 법정동: ".implode(', ', $targetDongs).')');

        return true;
    }
}
