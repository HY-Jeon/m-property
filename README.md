# 부동산 실거래가 시각화 MVP

국토교통부 아파트매매 실거래가 Open API를 활용해, 특정 지역의 시세 추이를 차트/테이블로 보여주는 서비스입니다.

## 설계 결정

### 1. 해제(취소) 신고는 정상 거래를 저장한 뒤에 처리

해제 신고는 원 거래와 핵심 필드가 같고 `cdealType`만 채워진 별도 항목으로 내려옵니다.
API 응답 순서에 상관없이 해제가 반영되도록, 한 지역·한 달 응답을 정상 건과 해제 건으로 나눠 **정상 건을 먼저 저장하고 해제 건을 나중에 처리**합니다.

- 해제 건과 같은 자연키를 가진 거래가 있으면 `is_cancelled = true`로 바꿉니다.
- 원 거래가 없으면(원 계약월과 다른 달 조회에서 해제만 보이는 경우 등) 해제 상태로 새로 저장합니다.
- 월별 집계는 해제 건을 빼고 계산합니다. 유효 거래가 0건이 되면 그 달의 집계 행을 삭제합니다.

### 2. 최근 6개월을 매번 다시 조회

해제 신고는 계약 후 한참 지나 등록되기도 합니다. 그래서 `--month` 없이 실행하면 지난달부터 거슬러 **최근 6개월**(`config/realestate.php`의 `lookback_months`)을 매번 다시 조회해, 늦게 올라온 해제 신고를 반영합니다.

### 3. 자연키 해시로 멱등 upsert

API 응답에는 거래 고유 ID가 없습니다. 그래서 아래 필드를 이어 붙인 문자열의 SHA-1을 `transactions.raw_hash`(유니크)로 쓰고, `updateOrCreate`로 저장합니다.

```
지역코드(LAWD_CD) | 법정동 | 아파트명 | 지번 | 전용면적 | 층 | 계약일 | 거래금액
```

같은 달을 몇 번 다시 조회해도 행이 늘어나지 않습니다. 해제 건도 같은 해시로 원 거래를 찾습니다.

## 대상 지역 (MVP)

`config/realestate.php`에서 지역 코드별로 수집할 법정동을 지정합니다.

| 지역 (LAWD_CD) | 대상 법정동 |
|---|---|
| 경기도 구리시 (41310) | 교문동 |
| 서울특별시 동대문구 (11230) | 장안동 |

API는 시군구 단위로 응답하므로, 받아온 뒤 `target_dongs`에 있는 법정동만 저장합니다.

## 기술 스택

| 구분 | 기술 |
|---|---|
| 백엔드 | Laravel 13 (PHP 8.5) |
| 데이터베이스 | MariaDB 11.4 |
| 캐시 | Redis 7 |
| 웹서버 | Nginx |
| 프론트엔드 차트 | TradingView Lightweight Charts |
| 인프라 | Docker Compose (로컬 개발) |
| 데이터 소스 | 공공데이터포털 - 국토교통부 아파트매매 실거래자료 |

## 로컬 개발 환경 실행

### 사전 준비

- Docker / Docker Compose 설치
- `.env.example`을 복사해 `.env` 만들기 (아래 참고)

### 실행 순서

```bash
# 1. 환경 파일 준비 후 MOLIT_API_SERVICE_KEY 입력
cp .env.example .env

# 2. 컨테이너 빌드 및 실행
docker compose up -d --build

# 3. 의존성 설치
docker compose exec app composer install

# 4. 앱 키 생성
docker compose exec app php artisan key:generate

# 5. 마이그레이션 실행
docker compose exec app php artisan migrate
```

브라우저에서 `http://localhost:8080`에 접속하면 `/transactions`로 이동합니다.

| 서비스 | 호스트 포트 | 비고 |
|---|---|---|
| Nginx | 8080 | |
| MariaDB | 13306 | 컨테이너 내부는 3306 |
| Redis | 노출 안 함 | 컨테이너 네트워크 안에서만 접근 (`docker compose exec redis redis-cli`) |

### 환경변수 (.env 주요 항목)

`.env.example`의 기본값입니다.

```
DB_CONNECTION=mysql
DB_HOST=db
DB_PORT=3306
DB_DATABASE=realestate
DB_USERNAME=laravel
DB_PASSWORD=secret

SESSION_DRIVER=database
QUEUE_CONNECTION=database
CACHE_STORE=database

REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

MOLIT_API_SERVICE_KEY=
```

`MOLIT_API_SERVICE_KEY`에는 공공데이터포털에서 발급받은 인증키를 넣습니다. 이 키는 `config/services.php`를 거쳐서만 읽고, 코드에 직접 쓰지 않습니다.

## 데이터 수집

### 스케줄

Laravel Scheduler로 매월 1일 03:00에 자동 수집합니다.

```php
// routes/console.php
Schedule::command('realestate:fetch')->monthlyOn(1, '03:00')->withoutOverlapping();
```

### 수동 실행

```bash
# 최근 6개월 재조회 (옵션 생략 시)
docker compose exec app php artisan realestate:fetch

# 특정 계약월 한 달
docker compose exec app php artisan realestate:fetch --month=202609

# 여러 달 백필 (--to 생략 시 --from 한 달만)
docker compose exec app php artisan realestate:fetch --from=202501 --to=202512
```

- data.go.kr WAF의 순간 트래픽 차단을 피하려고, 요청 사이에 0.5초 간격을 둡니다.
- 지역·월 중 하나라도 조회에 실패하면 나머지는 계속 수집하고, 마지막에 **종료 코드 1(FAILURE)**을 반환합니다. 실패 내용은 `Log::error`로 `storage/logs`에 남깁니다.
- 계약월 형식(YYYYMM)이 틀리면 API를 부르지 않고 바로 FAILURE를 반환합니다.

## 화면

| 경로 | 내용 |
|---|---|
| `/` | `/transactions`로 리다이렉트 |
| `/transactions` | 법정동별 월별 시세 차트와 실거래 목록 |
| `/safety` | 주소별 재난위험 리포트 (**데모 단계**, 아래 참고) |

### `/safety` (데모 단계)

주소로 침수 이력·홍수 위험·건축물 노후도·재난문자 건수를 등급으로 보여주는 실험 기능입니다. 아직 데모 단계입니다.

- `DATA_GO_KR_SERVICE_KEY`(없으면 `MOLIT_API_SERVICE_KEY`를 씀)와 `JUSO_API_KEY`가 **둘 다** 있어야 실제 API를 부릅니다. 하나라도 없으면 미리 넣어 둔 샘플 데이터로 동작합니다. 두 키는 아직 `.env.example`에 없습니다.
- 실제 API를 쓸 때도 침수 이력(생활안전지도 침수흔적 연동)은 구현되지 않았고, 홍수 위험은 "홍수위험지도 확인 필요"라는 고정 문구로 나옵니다.

## 테스트

```bash
docker compose exec app php artisan test
```

PHPUnit으로 돌리며, DB는 sqlite in-memory(`RefreshDatabase`)를 씁니다. 외부 API는 `Http::fake`로 대체하고, 실제 요청은 막아 둡니다(`Http::preventStrayRequests`).

- `MolitApiService`: 정상 XML, 오류 resultCode, 깨진 XML, 2페이지 페이징
- `TransactionStoreService`: 멱등성, 해제 반영, 원 거래 없는 해제, 집계에서 해제 제외, 전부 해제 시 집계 삭제, 다른 지역의 같은 이름 법정동
- `realestate:fetch` 커맨드: 계약월 형식 오류, 대상 외 법정동 제외, 조회 실패 시 FAILURE와 로그
- `/` → `/transactions` 리다이렉트

## 프로젝트 구조

```
app/
├── Console/Commands/
│   └── FetchRealEstateData.php    # realestate:fetch (조회 + target_dongs 필터)
├── Http/Controllers/
│   ├── TransactionController.php  # /transactions
│   └── SafetyController.php       # /safety (데모)
├── Models/
│   ├── Transaction.php            # 실거래 원본 (raw_hash 유니크)
│   └── MonthlyAggregate.php       # 월별 집계 (region_code, legal_dong, year_month 유니크)
└── Services/
    ├── MolitApiService.php        # 국토부 API 호출·XML 파싱·페이징
    ├── TransactionStoreService.php # 저장·해제 반영·월별 집계
    └── SafeReportService.php      # 재난위험 리포트 (데모)

config/realestate.php              # 수집 대상 지역/법정동, 재조회 개월 수

docker/
├── php/Dockerfile
├── nginx/default.conf
└── mariadb/my.cnf

tests/
├── Concerns/BuildsMolitXml.php    # API 응답 XML 헬퍼
├── Unit/MolitApiServiceTest.php
└── Feature/                       # 저장 서비스, 커맨드, 라우트 테스트
```

## 알려진 한계

- **실패 알림 없음**: 수집이 실패하면 종료 코드와 로그로만 알 수 있습니다. Telegram/Slack 알림은 아직 없습니다.
- **같은 조건의 거래가 합쳐질 수 있음**: 같은 단지·면적·층에서 같은 날 같은 금액으로 거래가 2건 있으면, 자연키가 같아 1건으로 저장됩니다.
- **Redis를 쓰지 않음**: Redis 컨테이너는 있지만, `.env.example` 기본값은 세션·캐시·큐 모두 `database` 드라이버입니다. Redis로 바꾸려면 `REDIS_HOST=redis`로 설정해야 합니다(호스트 포트를 노출하지 않으므로 `127.0.0.1`로는 연결되지 않습니다).
- **화면이 법정동 이름만으로 묶음**: 저장과 집계는 지역 코드까지 구분하지만, `/transactions` 화면은 법정동 이름으로만 묶습니다. 대상 지역을 늘리다가 같은 이름의 법정동이 생기면 화면에서 섞입니다.
- **테스트 DB와 운영 DB의 날짜 차이**: sqlite는 `deal_date`를 시간까지 붙은 문자열로 저장해, 테스트에서 월말 날짜 거래가 월 범위 조회에서 빠질 수 있습니다. 운영(MariaDB, DATE 컬럼)에는 영향이 없습니다.
- **`/safety`는 데모 단계**: 위 설명 참고.

## 브랜치 전략

```
main (배포용, GitHub Ruleset으로 보호됨)
  ↑ PR + Copilot 리뷰
dev (작업용)
```

- `main`: 직접 push 금지, PR을 통해서만 병합
- `dev`: 기능 개발 및 테스트

## 라이선스

개인 프로젝트 (MVP)
