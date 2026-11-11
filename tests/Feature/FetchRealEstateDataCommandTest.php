<?php

namespace Tests\Feature;

use App\Models\MonthlyAggregate;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\BuildsMolitXml;
use Tests\TestCase;

class FetchRealEstateDataCommandTest extends TestCase
{
    use BuildsMolitXml;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        config()->set('realestate.regions', [
            '41310' => ['name' => '경기도 구리시', 'target_dongs' => ['교문동']],
            '11230' => ['name' => '서울특별시 동대문구', 'target_dongs' => ['장안동']],
        ]);
    }

    public function test_invalid_month_option_fails_without_calling_api(): void
    {
        Http::fake();

        $this->artisan('realestate:fetch', ['--month' => '2026-09'])
            ->expectsOutputToContain('계약월 형식이 올바르지 않습니다')
            ->assertFailed();

        Http::assertNothingSent();
    }

    public function test_only_target_dongs_are_stored(): void
    {
        Http::fake(function (Request $request) {
            return match ($request['LAWD_CD']) {
                '41310' => Http::response($this->molitXml([
                    $this->molitItem(['umdNm' => '교문동', 'aptNm' => '교문아파트']),
                    $this->molitItem(['umdNm' => '인창동', 'aptNm' => '인창아파트']),
                ])),
                '11230' => Http::response($this->molitXml([
                    $this->molitItem(['umdNm' => '장안동', 'aptNm' => '장안아파트']),
                    $this->molitItem(['umdNm' => '휘경동', 'aptNm' => '휘경아파트']),
                ])),
            };
        });

        $this->artisan('realestate:fetch', ['--month' => '202609'])->assertSuccessful();

        $this->assertEqualsCanonicalizing(['교문동', '장안동'], Transaction::pluck('legal_dong')->all());
        $this->assertEqualsCanonicalizing(['교문동', '장안동'], MonthlyAggregate::pluck('legal_dong')->all());
        $this->assertSame(0, Transaction::whereIn('legal_dong', ['인창동', '휘경동'])->count());
    }
}
