<?php

namespace Tests\Feature;

use App\Models\MonthlyAggregate;
use App\Models\Transaction;
use App\Services\TransactionStoreService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsMolitXml;
use Tests\TestCase;

class TransactionStoreServiceTest extends TestCase
{
    use BuildsMolitXml;
    use RefreshDatabase;

    private const REGION = '11230';

    private const MONTH = '202609';

    private TransactionStoreService $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = new TransactionStoreService;
    }

    public function test_storing_same_items_twice_does_not_create_duplicates(): void
    {
        $items = [
            $this->molitItem(['aptNm' => 'A']),
            $this->molitItem(['aptNm' => 'B', 'dealAmount' => '90,000']),
        ];

        $this->store->storeMonth(self::REGION, self::MONTH, $items);
        $this->store->storeMonth(self::REGION, self::MONTH, $items);

        $this->assertSame(2, Transaction::count());
        $this->assertSame(1, MonthlyAggregate::count());
        $this->assertSame(2, MonthlyAggregate::first()->transaction_count);
    }

    public function test_cancellation_marks_existing_transaction_as_cancelled(): void
    {
        $original = $this->molitItem();
        $this->store->storeMonth(self::REGION, self::MONTH, [$original]);

        $result = $this->store->storeMonth(self::REGION, self::MONTH, [
            $original,
            $this->molitItem(['cdealType' => 'O']),
        ]);

        $this->assertSame(['saved' => 1, 'cancelled' => 1], $result);
        $this->assertSame(1, Transaction::count());
        $this->assertTrue(Transaction::first()->is_cancelled);
    }

    public function test_cancellation_without_original_creates_cancelled_transaction(): void
    {
        $this->store->storeMonth(self::REGION, self::MONTH, [
            $this->molitItem(['cdealType' => 'O', 'dealAmount' => '70,000']),
        ]);

        $transaction = Transaction::sole();
        $this->assertTrue($transaction->is_cancelled);
        $this->assertSame(70000, $transaction->deal_amount);
        $this->assertSame('장안동', $transaction->legal_dong);
    }

    public function test_aggregate_excludes_cancelled_transactions(): void
    {
        $this->store->storeMonth(self::REGION, self::MONTH, [
            $this->molitItem(['aptNm' => 'A', 'dealAmount' => '10,000']),
            $this->molitItem(['aptNm' => 'B', 'dealAmount' => '20,000']),
            $this->molitItem(['aptNm' => 'C', 'dealAmount' => '90,000']),
            $this->molitItem(['aptNm' => 'C', 'dealAmount' => '90,000', 'cdealType' => 'O']),
        ]);

        $aggregate = MonthlyAggregate::sole();
        $this->assertSame(2, $aggregate->transaction_count);
        $this->assertSame(15000, $aggregate->avg_deal_amount);
        $this->assertSame(10000, $aggregate->min_deal_amount);
        $this->assertSame(20000, $aggregate->max_deal_amount);
    }

    public function test_aggregate_row_is_removed_when_all_transactions_are_cancelled(): void
    {
        $original = $this->molitItem();
        $this->store->storeMonth(self::REGION, self::MONTH, [$original]);
        $this->assertSame(1, MonthlyAggregate::count());

        $this->store->storeMonth(self::REGION, self::MONTH, [
            $original,
            $this->molitItem(['cdealType' => 'O']),
        ]);

        $this->assertSame(0, MonthlyAggregate::count());
    }

    public function test_same_dong_name_in_different_regions_does_not_conflict(): void
    {
        $this->store->storeMonth('11111', self::MONTH, [
            $this->molitItem(['umdNm' => '중앙동', 'dealAmount' => '50,000']),
        ]);
        $this->store->storeMonth('22222', self::MONTH, [
            $this->molitItem(['umdNm' => '중앙동', 'dealAmount' => '30,000']),
            $this->molitItem(['umdNm' => '중앙동', 'aptNm' => '다른아파트', 'dealAmount' => '40,000']),
        ]);

        $this->assertSame(3, Transaction::count());

        $byRegion = MonthlyAggregate::where('legal_dong', '중앙동')->get()->keyBy('region_code');
        $this->assertCount(2, $byRegion);
        $this->assertSame(1, $byRegion['11111']->transaction_count);
        $this->assertSame(50000, $byRegion['11111']->avg_deal_amount);
        $this->assertSame(2, $byRegion['22222']->transaction_count);
        $this->assertSame(35000, $byRegion['22222']->avg_deal_amount);
    }
}
