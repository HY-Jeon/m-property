<?php

namespace Tests\Concerns;

trait BuildsMolitXml
{
    /**
     * 국토교통부 아파트매매 API의 거래 1건 형태로 기본값을 채운다.
     *
     * @param  array<string, string>  $overrides
     * @return array<string, string>
     */
    protected function molitItem(array $overrides = []): array
    {
        return array_merge([
            'aptNm' => '테스트아파트',
            'umdNm' => '장안동',
            'jibun' => '123-4',
            'excluUseAr' => '84.97',
            'floor' => '10',
            'buildYear' => '2005',
            'dealYear' => '2026',
            'dealMonth' => '9',
            'dealDay' => '15',
            'dealAmount' => '85,000',
            'dealingGbn' => '중개거래',
            'cdealType' => '',
        ], $overrides);
    }

    /**
     * @param  array<int, array<string, string>>  $items
     */
    protected function molitXml(array $items, ?int $totalCount = null, string $resultCode = '000', string $resultMsg = 'OK'): string
    {
        $itemsXml = '';
        foreach ($items as $item) {
            $itemsXml .= '<item>';
            foreach ($item as $key => $value) {
                $itemsXml .= "<{$key}>".htmlspecialchars($value, ENT_XML1)."</{$key}>";
            }
            $itemsXml .= '</item>';
        }

        $totalCount ??= count($items);

        return <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<response>
    <header><resultCode>{$resultCode}</resultCode><resultMsg>{$resultMsg}</resultMsg></header>
    <body>
        <items>{$itemsXml}</items>
        <numOfRows>1000</numOfRows>
        <pageNo>1</pageNo>
        <totalCount>{$totalCount}</totalCount>
    </body>
</response>
XML;
    }
}
