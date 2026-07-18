<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>안전체크 — 계약 전 재난위험 리포트</title>
<style>
  * { margin:0; padding:0; box-sizing:border-box; }
  body { font-family:-apple-system,'Malgun Gothic',sans-serif; background:#f4f6f8; color:#222; }
  .wrap { max-width:720px; margin:0 auto; padding:24px 16px 60px; }
  header { text-align:center; padding:28px 0 20px; }
  header h1 { font-size:26px; }
  header p { color:#667; margin-top:6px; font-size:14px; }
  .nav { text-align:center; margin-bottom:16px; font-size:13px; }
  .nav a { color:#1565c0; }
  .search { display:flex; gap:8px; margin-bottom:12px; }
  .search input { flex:1; padding:13px 16px; border:2px solid #d0d7de; border-radius:10px; font-size:15px; }
  .search button { padding:13px 24px; border:0; border-radius:10px; background:#1565c0; color:#fff; font-size:15px; cursor:pointer; }
  .demo-links { font-size:13px; color:#667; margin-bottom:24px; line-height:2; }
  .demo-links a { color:#1565c0; margin-right:10px; }
  .badge-demo { display:inline-block; background:#fff3e0; color:#ef6c00; font-size:12px; padding:3px 10px; border-radius:20px; margin-bottom:12px; }
  .card { background:#fff; border-radius:14px; padding:24px; box-shadow:0 1px 4px rgba(0,0,0,.08); margin-bottom:16px; }
  .addr { font-size:15px; color:#445; margin-bottom:16px; }
  .overall { display:flex; align-items:center; gap:18px; }
  .overall .g { width:76px; height:76px; border-radius:50%; color:#fff; font-size:38px; font-weight:bold; display:flex; align-items:center; justify-content:center; }
  .overall .t h2 { font-size:19px; }
  .overall .t p { font-size:13px; color:#667; margin-top:4px; }
  .item { display:flex; align-items:flex-start; gap:14px; padding:14px 0; border-bottom:1px solid #eef1f4; }
  .item:last-child { border-bottom:0; }
  .item .g { min-width:40px; height:40px; border-radius:10px; color:#fff; font-weight:bold; font-size:20px; display:flex; align-items:center; justify-content:center; }
  .item h3 { font-size:15px; }
  .item p { font-size:13px; color:#556; margin-top:3px; }
  .item ul { font-size:12px; color:#778; margin:6px 0 0 16px; }
  .src { font-size:11px; color:#99a; margin-top:14px; line-height:1.7; }
  .err { background:#ffebee; color:#c62828; padding:14px 18px; border-radius:10px; font-size:14px; }
  .print-btn { margin-top:8px; background:#fff; border:1px solid #d0d7de; padding:10px 18px; border-radius:8px; cursor:pointer; font-size:13px; }
  @media print { .search,.demo-links,.print-btn,.nav,header p { display:none; } body { background:#fff; } }
</style>
</head>
<body>
@php
    $gradeColor = fn (string $g): string => match ($g) {
        'A' => '#2e7d32', 'B' => '#689f38', 'C' => '#f9a825',
        'D' => '#ef6c00', 'E' => '#c62828', default => '#666',
    };
@endphp
<div class="wrap">
  <header>
    <h1>🏠 안전체크</h1>
    <p>계약 전 30초, 이 집의 재난위험을 확인하세요 — 공공데이터 기반</p>
  </header>
  <div class="nav"><a href="/transactions">← 실거래가 보기</a></div>

  <form class="search" method="get" action="/safety">
    <input type="text" name="q" placeholder="도로명 또는 지번 주소 입력" value="{{ $q }}" required>
    <button type="submit">조회</button>
  </form>

  @if ($demoMode)
  <div class="demo-links">
    데모 주소:
    @foreach ($demos as $da)
      <a href="/safety?q={{ urlencode($da) }}">{{ \Illuminate\Support\Str::limit($da, 24) }}</a>
    @endforeach
  </div>
  @endif

  @if ($report !== null)
    @if (isset($report['error']))
      <div class="err">{{ $report['error'] }}</div>
    @else
      @if ($report['demo'])
        <span class="badge-demo">데모 모드 — 샘플 데이터입니다 (.env에 API 키 입력 시 실데이터 조회)</span>
      @endif

      <div class="card">
        <div class="addr">📍 {{ $report['address'] }}</div>
        <div class="overall">
          <div class="g" style="background:{{ $gradeColor($report['overall']) }}">{{ $report['overall'] }}</div>
          <div class="t">
            <h2>종합 재난안전 등급</h2>
            <p>침수 이력 · 홍수 위험 · 건물 안전 · 재난 빈도 4개 항목 기반 (A 안전 ~ E 위험)</p>
          </div>
        </div>
      </div>

      <div class="card">
        @foreach ($report['items'] as $it)
        <div class="item">
          <div class="g" style="background:{{ $gradeColor($it['grade']) }}">{{ $it['grade'] }}</div>
          <div>
            <h3>{{ $it['title'] }}</h3>
            <p>{{ $it['note'] }}</p>
            @if ($it['detail'])
            <ul>
              @foreach ($it['detail'] as $d)<li>{{ $d }}</li>@endforeach
            </ul>
            @endif
          </div>
        </div>
        @endforeach
        <div class="src">
          출처: 행정안전부 침수흔적도·긴급재난문자, 환경부 홍수위험지도, 국토교통부 건축물대장(건축HUB), 생활안전지도 — 공공데이터포털(data.go.kr)<br>
          본 리포트는 공공데이터를 사실 그대로 표시한 참고자료이며, 법적 효력이 없습니다.
        </div>
      </div>
      <button class="print-btn" onclick="window.print()">🖨 리포트 인쇄 / PDF 저장</button>
    @endif
  @endif
</div>
</body>
</html>
