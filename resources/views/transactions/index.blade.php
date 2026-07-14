<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="utf-8">
    <title>실거래가 데이터 확인 (디버그)</title>
    <style>
        body { font-family: -apple-system, sans-serif; margin: 2rem; color: #222; }
        h1 { font-size: 1.25rem; }
        h2 { font-size: 1rem; margin-top: 2rem; }
        table { border-collapse: collapse; width: 100%; margin-top: 0.5rem; font-size: 0.85rem; }
        th, td { border: 1px solid #ddd; padding: 0.35rem 0.5rem; text-align: right; }
        th { background: #f5f5f5; text-align: center; }
        td:nth-child(1), td:nth-child(2), td:nth-child(3) { text-align: left; }
        tr.cancelled { color: #b00; text-decoration: line-through; opacity: 0.6; }
    </style>
</head>
<body>
    <h1>실거래가 데이터 확인 (임시 디버그 페이지)</h1>

    <h2>월별 집계 ({{ $aggregates->count() }}건)</h2>
    <table>
        <thead>
            <tr>
                <th>지역</th><th>법정동</th><th>계약월</th><th>거래건수</th>
                <th>평균가(만원)</th><th>최저가(만원)</th><th>최고가(만원)</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($aggregates as $row)
                <tr>
                    <td>{{ $row->region_code }}</td>
                    <td>{{ $row->legal_dong }}</td>
                    <td>{{ $row->year_month }}</td>
                    <td>{{ $row->transaction_count }}</td>
                    <td>{{ number_format($row->avg_deal_amount) }}</td>
                    <td>{{ number_format($row->min_deal_amount) }}</td>
                    <td>{{ number_format($row->max_deal_amount) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <h2>최근 거래 내역 (최대 200건)</h2>
    <table>
        <thead>
            <tr>
                <th>법정동</th><th>아파트</th><th>지번</th><th>전용면적</th>
                <th>층</th><th>건축년도</th><th>거래일</th><th>거래금액(만원)</th><th>거래유형</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($transactions as $t)
                <tr class="{{ $t->is_cancelled ? 'cancelled' : '' }}">
                    <td>{{ $t->legal_dong }}</td>
                    <td>{{ $t->apartment_name }}</td>
                    <td>{{ $t->jibun }}</td>
                    <td>{{ $t->exclusive_area }}</td>
                    <td>{{ $t->floor }}</td>
                    <td>{{ $t->build_year }}</td>
                    <td>{{ $t->deal_date->format('Y-m-d') }}</td>
                    <td>{{ number_format($t->deal_amount) }}</td>
                    <td>{{ $t->deal_type }}{{ $t->is_cancelled ? ' (해제)' : '' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
