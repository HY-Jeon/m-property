<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>실거래가 대시보드</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-900">
    <div class="max-w-7xl mx-auto px-4 py-8 sm:px-6 lg:px-8">
        <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="text-sm text-slate-500">부동산 실거래 대시보드</p>
                <h1 class="text-3xl font-semibold tracking-tight text-slate-950">최근 거래 및 월별 평균 시세</h1>
                <p class="mt-2 text-slate-600 max-w-2xl">국토교통부 아파트매매 실거래 데이터를 기반으로 월별 평균가와 최근 거래 내역을 한눈에 확인합니다.</p>
            </div>

            <a href="/" class="inline-flex items-center justify-center rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-slate-800">
                홈으로 돌아가기
            </a>
        </div>

        <div class="mt-8 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-sm font-medium text-slate-500">집계 항목</p>
                <p class="mt-3 text-3xl font-semibold text-slate-950">{{ $aggregates->count() }}</p>
                <p class="mt-2 text-sm text-slate-600">등록된 월별 집계 건수</p>
            </div>
            <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-sm font-medium text-slate-500">최근 거래</p>
                <p class="mt-3 text-3xl font-semibold text-slate-950">{{ $transactions->count() }}</p>
                <p class="mt-2 text-sm text-slate-600">최대 200건 최근 거래 표시</p>
            </div>
            <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-sm font-medium text-slate-500">최신 계약월</p>
                <p class="mt-3 text-3xl font-semibold text-slate-950">{{ optional($aggregates->last())->year_month ?? '-' }}</p>
                <p class="mt-2 text-sm text-slate-600">가장 최근에 집계된 계약월</p>
            </div>
            <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-sm font-medium text-slate-500">추적 대상 법정동</p>
                <p class="mt-3 text-3xl font-semibold text-slate-950">{{ $aggregates->pluck('legal_dong')->unique()->count() }}</p>
                <p class="mt-2 text-sm text-slate-600">데이터가 수집된 법정동 수</p>
            </div>
        </div>

        <section class="mt-10 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 class="text-xl font-semibold text-slate-950">월별 평균가 차트</h2>
                    <p class="mt-1 text-sm text-slate-600">법정동별 평균 거래가를 월별로 비교합니다.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    @foreach($seriesData as $dong => $series)
                        <span class="rounded-full bg-slate-100 px-3 py-1 text-sm text-slate-700">{{ $dong }}</span>
                    @endforeach
                </div>
            </div>

            <div id="realestate-chart" class="mt-6 h-[420px] w-full rounded-3xl bg-slate-50 shadow-inner"></div>
        </section>

        <section class="mt-10 grid gap-8 xl:grid-cols-[1.3fr_0.7fr]">
            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <h2 class="text-xl font-semibold text-slate-950">월별 집계</h2>
                        <p class="mt-1 text-sm text-slate-600">거래 건수, 평균가, 최고가/최저가를 지역별로 확인하세요.</p>
                    </div>
                </div>

                <div class="mt-6 overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-4 py-3 text-left font-semibold text-slate-700">법정동</th>
                                <th class="px-4 py-3 text-right font-semibold text-slate-700">계약월</th>
                                <th class="px-4 py-3 text-right font-semibold text-slate-700">거래건수</th>
                                <th class="px-4 py-3 text-right font-semibold text-slate-700">평균가</th>
                                <th class="px-4 py-3 text-right font-semibold text-slate-700">최저가</th>
                                <th class="px-4 py-3 text-right font-semibold text-slate-700">최고가</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-slate-700">
                            @foreach ($aggregates as $row)
                                <tr>
                                    <td class="whitespace-nowrap px-4 py-3">{{ $row->legal_dong }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right">{{ $row->year_month }}</td>
                                    <td class="px-4 py-3 text-right">{{ number_format($row->transaction_count) }}</td>
                                    <td class="px-4 py-3 text-right">{{ number_format($row->avg_deal_amount) }}</td>
                                    <td class="px-4 py-3 text-right">{{ number_format($row->min_deal_amount) }}</td>
                                    <td class="px-4 py-3 text-right">{{ number_format($row->max_deal_amount) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="text-xl font-semibold text-slate-950">최근 거래 내역</h2>
                <p class="mt-1 text-sm text-slate-600">최근 200건 거래 결과를 표시합니다.</p>
                <div class="mt-6 overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-3 py-3 text-left font-semibold text-slate-700">법정동</th>
                                <th class="px-3 py-3 text-left font-semibold text-slate-700">아파트</th>
                                <th class="px-3 py-3 text-right font-semibold text-slate-700">거래일</th>
                                <th class="px-3 py-3 text-right font-semibold text-slate-700">금액</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-slate-700">
                            @foreach ($transactions as $t)
                                <tr class="{{ $t->is_cancelled ? 'opacity-60 line-through text-red-600' : '' }}">
                                    <td class="whitespace-nowrap px-3 py-3">{{ $t->legal_dong }}</td>
                                    <td class="whitespace-nowrap px-3 py-3">{{ $t->apartment_name }}</td>
                                    <td class="whitespace-nowrap px-3 py-3 text-right">{{ $t->deal_date->format('Y-m-d') }}</td>
                                    <td class="whitespace-nowrap px-3 py-3 text-right">{{ number_format($t->deal_amount) }} 만원</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </div>

    <script>
        window.realestateChartData = @json($seriesData);
    </script>
</body>
</html>
