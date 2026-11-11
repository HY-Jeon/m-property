<?php

namespace Tests\Unit;

use App\Services\MolitApiService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\Concerns\BuildsMolitXml;
use Tests\TestCase;

class MolitApiServiceTest extends TestCase
{
    use BuildsMolitXml;

    private MolitApiService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        $this->service = new MolitApiService('test-service-key');
    }

    public function test_parses_normal_xml_response(): void
    {
        Http::fake([
            'apis.data.go.kr/*' => Http::response($this->molitXml([
                $this->molitItem(['aptNm' => '래미안', 'dealAmount' => ' 85,000']),
                $this->molitItem(['aptNm' => '힐스테이트', 'cdealType' => 'O']),
            ])),
        ]);

        $items = $this->service->fetchAll('11230', '202609');

        $this->assertCount(2, $items);
        $this->assertSame('래미안', $items[0]['aptNm']);
        $this->assertSame('85,000', $items[0]['dealAmount'], '값의 앞뒤 공백은 제거되어야 한다');
        $this->assertSame('O', $items[1]['cdealType']);

        Http::assertSent(fn (Request $request) => $request['serviceKey'] === 'test-service-key'
            && $request['LAWD_CD'] === '11230'
            && $request['DEAL_YMD'] === '202609'
            && (int) $request['pageNo'] === 1);
    }

    public function test_throws_when_result_code_is_error(): void
    {
        Http::fake([
            'apis.data.go.kr/*' => Http::response($this->molitXml([], 0, '30', 'SERVICE KEY IS NOT REGISTERED ERROR.')),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('MOLIT API 오류 [30]: SERVICE KEY IS NOT REGISTERED ERROR.');

        $this->service->fetchAll('11230', '202609');
    }

    public function test_throws_when_xml_is_broken(): void
    {
        Http::fake([
            'apis.data.go.kr/*' => Http::response('<response><header><resultCode>000</resultCode>'),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('XML 파싱에 실패했습니다');

        $this->service->fetchAll('11230', '202609');
    }

    public function test_fetches_all_pages_until_total_count_is_reached(): void
    {
        Http::fake(function (Request $request) {
            return match ((int) $request['pageNo']) {
                1 => Http::response($this->molitXml([
                    $this->molitItem(['aptNm' => 'A']),
                    $this->molitItem(['aptNm' => 'B']),
                ], 3)),
                2 => Http::response($this->molitXml([
                    $this->molitItem(['aptNm' => 'C']),
                ], 3)),
                default => Http::response('unexpected page', 500),
            };
        });

        $items = $this->service->fetchAll('11230', '202609');

        $this->assertSame(['A', 'B', 'C'], array_column($items, 'aptNm'));
        Http::assertSentCount(2);
        Http::assertSent(fn (Request $request) => (int) $request['pageNo'] === 2);
    }
}
