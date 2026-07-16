<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;
use SimpleXMLElement;

class MolitApiService
{
    private const ENDPOINT = 'https://apis.data.go.kr/1613000/RTMSDataSvcAptTrade/getRTMSDataSvcAptTrade';

    /**
     * data.go.kr WAF가 브라우저 특성이 없는 요청(기본 curl/HttpClient 등)을 차단하므로
     * 최소한의 브라우저 유사 헤더를 함께 보낸다.
     *
     * @var array<string, string>
     */
    private const HEADERS = [
        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36',
        'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        'Accept-Language' => 'ko-KR,ko;q=0.9,en-US;q=0.8,en;q=0.7',
        'Referer' => 'https://www.data.go.kr/',
    ];

    public function __construct(private readonly string $serviceKey)
    {
    }

    /**
     * 지정한 지역(LAWD_CD)/계약월(DEAL_YMD)의 아파트매매 실거래 자료를 전체 페이지 수집한다.
     *
     * @return array<int, array<string, string>>
     */
    public function fetchAll(string $lawdCd, string $dealYmd): array
    {
        $numOfRows = 1000;
        $pageNo = 1;
        $items = [];

        do {
            if ($pageNo > 1) {
                usleep(500_000);
            }

            $page = $this->fetchPage($lawdCd, $dealYmd, $pageNo, $numOfRows);

            if ($page['items'] === []) {
                break;
            }

            $items = array_merge($items, $page['items']);
            $pageNo++;
        } while (count($items) < $page['totalCount']);

        return $items;
    }

    /**
     * @return array{items: array<int, array<string, string>>, totalCount: int}
     */
    private function fetchPage(string $lawdCd, string $dealYmd, int $pageNo, int $numOfRows): array
    {
        $response = Http::withHeaders(self::HEADERS)->timeout(15)->get(self::ENDPOINT, [
            'serviceKey' => $this->serviceKey,
            'LAWD_CD' => $lawdCd,
            'DEAL_YMD' => $dealYmd,
            'pageNo' => $pageNo,
            'numOfRows' => $numOfRows,
        ]);

        $response->throw();

        return $this->parseXml($response->body());
    }

    /**
     * @return array{items: array<int, array<string, string>>, totalCount: int}
     */
    private function parseXml(string $xml): array
    {
        $previous = libxml_use_internal_errors(true);
        $doc = simplexml_load_string($xml);
        libxml_use_internal_errors($previous);

        if ($doc === false) {
            throw new RuntimeException('MOLIT API 응답 XML 파싱에 실패했습니다.');
        }

        $resultCode = (string) ($doc->header->resultCode ?? '');
        $resultMsg = (string) ($doc->header->resultMsg ?? '알 수 없는 오류');

        if ($resultCode !== '000') {
            throw new RuntimeException("MOLIT API 오류 [{$resultCode}]: {$resultMsg}");
        }

        $items = [];
        foreach ($doc->body->items->item ?? [] as $item) {
            /** @var SimpleXMLElement $item */
            $items[] = array_map(
                static fn ($value) => trim((string) $value),
                (array) $item
            );
        }

        return [
            'items' => $items,
            'totalCount' => (int) ($doc->body->totalCount ?? count($items)),
        ];
    }
}
