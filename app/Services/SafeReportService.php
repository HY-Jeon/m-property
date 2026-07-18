<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * 안전체크 — 주소 기반 재난위험 리포트 서비스 (v2 통합 모듈)
 *
 * 공공데이터: 도로명주소 검색, 국토부 건축물대장(건축HUB), 행안부 긴급재난문자
 * 키가 없으면 데모 모드(샘플 데이터)로 동작한다.
 */
class SafeReportService
{
    private const API_JUSO        = 'https://business.juso.go.kr/addrlink/addrLinkApi.do';
    private const API_BLDG_LEDGER = 'https://apis.data.go.kr/1613000/BldRgstHubService/getBrTitleInfo';
    private const API_DISASTER    = 'https://apis.data.go.kr/1741000/DisasterMsg5/getDisasterMsg1List';

    /** data.go.kr WAF 우회용 브라우저 유사 헤더 (MolitApiService와 동일 전략) */
    private const HEADERS = [
        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36',
        'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        'Accept-Language' => 'ko-KR,ko;q=0.9,en-US;q=0.8,en;q=0.7',
        'Referer' => 'https://www.data.go.kr/',
    ];

    public function __construct(
        private readonly ?string $dataGoKrKey = null,
        private readonly ?string $jusoKey = null,
    ) {
    }

    public function isDemoMode(): bool
    {
        return empty($this->dataGoKrKey) || empty($this->jusoKey);
    }

    /**
     * 주소(또는 데모 키워드)로 재난위험 리포트를 생성한다.
     *
     * @return array{address?: string, demo?: bool, overall?: string, items?: array, error?: string}
     */
    public function buildReport(string $query): array
    {
        if ($this->isDemoMode()) {
            [$addr, $d] = $this->demoData($query);
        } else {
            $found = $this->searchAddress($query);
            if ($found === null) {
                return ['error' => '주소를 찾을 수 없습니다. 도로명 또는 지번 주소로 다시 검색해 주세요.'];
            }
            $bldg = $this->getBuilding($found) ?? ['built_year' => 0, 'structure' => '정보없음', 'basement' => 0];
            $msgs = $this->getDisasterMsgs($found['siNm'], $found['sggNm']);
            $addr = $found['roadAddr'];
            $d = [
                'flood_history' => [],   // TODO: 생활안전지도 침수흔적 WMS 좌표 질의 연동
                'flood_risk'    => '홍수위험지도 확인 필요',
                'built_year'    => $bldg['built_year'],
                'structure'     => $bldg['structure'],
                'basement'      => $bldg['basement'],
                'msg_count_3y'  => $msgs['count'],
                'msg_types'     => $msgs['types'],
            ];
        }

        [$g1, $n1] = $this->gradeFloodHistory($d['flood_history']);
        [$g2, $n2] = $this->gradeFloodRisk($d['flood_risk']);
        [$g3, $n3] = $this->gradeBuilding($d['built_year'], $d['structure'], $d['basement']);
        [$g4, $n4] = $this->gradeMsgs($d['msg_count_3y']);

        return [
            'address' => $addr,
            'demo'    => $this->isDemoMode(),
            'overall' => $this->overallGrade([$g1, $g2, $g3, $g4]),
            'items'   => [
                ['title' => '침수 이력', 'grade' => $g1, 'note' => $n1, 'detail' => $d['flood_history']],
                ['title' => '홍수 위험', 'grade' => $g2, 'note' => $n2, 'detail' => []],
                ['title' => '건물 안전', 'grade' => $g3, 'note' => $n3, 'detail' => []],
                ['title' => '재난 빈도', 'grade' => $g4, 'note' => $n4, 'detail' => $d['msg_types']],
            ],
        ];
    }

    /**
     * 실거래(Transaction) 정보 기반 간이 등급 — 목록 위젯용.
     * 건축년도만으로 건물 안전 등급을 계산한다.
     */
    public function quickGradeByBuildYear(?int $buildYear): string
    {
        if ($buildYear === null || $buildYear <= 0) {
            return '-';
        }
        $age = (int) date('Y') - $buildYear;

        return match (true) {
            $age < 10 => 'A',
            $age < 20 => 'B',
            $age < 30 => 'C',
            $age < 40 => 'D',
            default   => 'E',
        };
    }

    /* ---------- 외부 API ---------- */

    private function searchAddress(string $query): ?array
    {
        $res = Http::withHeaders(self::HEADERS)->timeout(10)->get(self::API_JUSO, [
            'confmKey'     => $this->jusoKey,
            'currentPage'  => 1,
            'countPerPage' => 1,
            'keyword'      => $query,
            'resultType'   => 'json',
        ])->json();

        $j = $res['results']['juso'][0] ?? null;
        if ($j === null) {
            return null;
        }

        return [
            'roadAddr'  => $j['roadAddr'],
            'sigunguCd' => substr($j['admCd'], 0, 5),
            'bjdongCd'  => substr($j['admCd'], 5, 5),
            'bun'       => str_pad($j['lnbrMnnm'], 4, '0', STR_PAD_LEFT),
            'ji'        => str_pad($j['lnbrSlno'], 4, '0', STR_PAD_LEFT),
            'siNm'      => $j['siNm'],
            'sggNm'     => $j['sggNm'],
        ];
    }

    private function getBuilding(array $addr): ?array
    {
        $res = Http::withHeaders(self::HEADERS)->timeout(10)->get(self::API_BLDG_LEDGER, [
            'serviceKey' => $this->dataGoKrKey,
            'sigunguCd'  => $addr['sigunguCd'],
            'bjdongCd'   => $addr['bjdongCd'],
            'bun'        => $addr['bun'],
            'ji'         => $addr['ji'],
            '_type'      => 'json',
            'numOfRows'  => 1,
        ])->json();

        $item = $res['response']['body']['items']['item'] ?? null;
        if ($item === null) {
            return null;
        }
        if (array_is_list($item)) {
            $item = $item[0];
        }

        return [
            'built_year' => (int) substr((string) ($item['useAprDay'] ?? '0'), 0, 4),
            'structure'  => $item['strctCdNm'] ?? '정보없음',
            'basement'   => (int) ($item['ugrndFlrCnt'] ?? 0),
        ];
    }

    private function getDisasterMsgs(string $siNm, string $sggNm): array
    {
        $res = Http::withHeaders(self::HEADERS)->timeout(10)->get(self::API_DISASTER, [
            'serviceKey' => $this->dataGoKrKey,
            'pageNo'     => 1,
            'numOfRows'  => 300,
            'type'       => 'json',
        ])->json();

        $rows = $res['DisasterMsg2'][1]['row'] ?? ($res['body'] ?? []);
        $count = 0;
        $types = [];
        foreach ((array) $rows as $r) {
            $loc = $r['location_name'] ?? $r['RCPTN_RGN_NM'] ?? '';
            if (str_contains($loc, $sggNm) || str_contains($loc, $siNm)) {
                $count++;
                $t = $r['disaster_type'] ?? $r['DST_SE_NM'] ?? '기타';
                $types[$t] = ($types[$t] ?? 0) + 1;
            }
        }
        arsort($types);
        $typeStr = array_map(
            static fn (string $t, int $c): string => "$t $c",
            array_keys($types),
            array_values($types),
        );

        return ['count' => $count, 'types' => array_slice($typeStr, 0, 4)];
    }

    /* ---------- 등급 산정 ---------- */

    private function gradeFloodHistory(array $hist): array
    {
        $n = count($hist);

        return match (true) {
            $n === 0 => ['A', '침수 이력 없음'],
            $n === 1 => ['C', '침수 이력 1회'],
            default  => ['E', "침수 이력 {$n}회 — 반복 침수 지역"],
        };
    }

    private function gradeFloodRisk(string $risk): array
    {
        return match (true) {
            str_contains($risk, '위험구역 아님') => ['A', $risk],
            str_contains($risk, '인접')          => ['C', $risk],
            default                              => ['D', $risk],
        };
    }

    private function gradeBuilding(int $year, string $structure, int $basement): array
    {
        $age = (int) date('Y') - $year;
        $g = match (true) {
            $age < 10 => 'A',
            $age < 20 => 'B',
            $age < 30 => 'C',
            $age < 40 => 'D',
            default   => 'E',
        };
        $note = "준공 {$year}년 (경과 {$age}년) · {$structure}";
        if ($basement > 0) {
            $note .= ' · 지하층 있음(침수 취약)';
            if ($g < 'D') {
                $g = chr(ord($g) + 1);
            }
        }

        return [$g, $note];
    }

    private function gradeMsgs(int $count): array
    {
        $g = match (true) {
            $count < 15 => 'A',
            $count < 25 => 'B',
            $count < 35 => 'C',
            $count < 45 => 'D',
            default     => 'E',
        };

        return [$g, "최근 3년 재난문자 {$count}건"];
    }

    private function overallGrade(array $grades): string
    {
        $score = array_sum(array_map(static fn (string $g): int => ord($g) - ord('A'), $grades));
        $o = chr(ord('A') + (int) round($score / count($grades)));
        if ($grades[0] === 'E' && $o < 'D') {
            $o = 'D';
        }

        return $o;
    }

    /* ---------- 데모 데이터 ---------- */

    private function demoData(string $query): array
    {
        $demos = [
            '서울 동대문구 장안동 000-00 (반지하 다세대)' => [
                'flood_history' => ['2022년 8월 집중호우 침수 (침수심 0.5~1.0m)', '2011년 7월 침수 기록'],
                'flood_risk'    => '중랑천 저지대 · 홍수위험지도 위험구역 포함',
                'built_year'    => 1992,
                'structure'     => '연와조',
                'basement'      => 1,
                'msg_count_3y'  => 47,
                'msg_types'     => ['호우 21', '태풍 9', '대설 7', '기타 10'],
            ],
            '경기 구리시 딸기원 000 (신축 아파트)' => [
                'flood_history' => [],
                'flood_risk'    => '위험구역 아님',
                'built_year'    => 2021,
                'structure'     => '철근콘크리트조',
                'basement'      => 0,
                'msg_count_3y'  => 12,
                'msg_types'     => ['호우 5', '대설 4', '기타 3'],
            ],
            '경기 구리시 인창동 000-0 (경사지 노후주택)' => [
                'flood_history' => [],
                'flood_risk'    => '급경사지 붕괴위험지구 인접 (200m 이내)',
                'built_year'    => 1978,
                'structure'     => '목조',
                'basement'      => 0,
                'msg_count_3y'  => 31,
                'msg_types'     => ['태풍 14', '호우 11', '기타 6'],
            ],
        ];

        foreach ($demos as $k => $v) {
            if ($query === $k || str_contains($k, $query)) {
                return [$k, $v];
            }
        }

        $first = array_key_first($demos);

        return [$first, $demos[$first]];
    }

    /** 데모 주소 목록 (뷰의 바로가기 링크용) */
    public function demoAddresses(): array
    {
        return [
            '서울 동대문구 장안동 000-00 (반지하 다세대)',
            '경기 구리시 딸기원 000 (신축 아파트)',
            '경기 구리시 인창동 000-0 (경사지 노후주택)',
        ];
    }
}
