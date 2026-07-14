# 부동산 실거래가 시각화 MVP

국토교통부 아파트매매 실거래가 Open API를 활용해, 특정 지역의 시세 추이를 차트/테이블로 보여주는 서비스입니다.

## 대상 지역 (MVP)

- 경기도 구리시
- 서울특별시 동대문구

## 기술 스택

| 구분 | 기술 |
|---|---|
| 백엔드 | Laravel (PHP 8.3) |
| 데이터베이스 | MariaDB 11.4 |
| 캐시/세션 | Redis 7 |
| 웹서버 | Nginx |
| 프론트엔드 차트 | TradingView Lightweight Charts |
| 인프라 | Docker Compose, Oracle Cloud Free Tier (VPS) |
| 데이터 소스 | 공공데이터포털 - 국토교통부 아파트매매 실거래자료 |

## 로컬 개발 환경 실행

### 사전 준비a
- Docker / Docker Compose 설치
- `.env` 파일 준비 (아래 참고)

### 실행 순서 

```bash
# 1. 컨테이너 빌드 및 실행
docker compose up -d --build

# 2. 의존성 설치
docker compose exec app composer install

# 3. 앱 키 생성
docker compose exec app php artisan key:generate

# 4. 마이그레이션 실행
docker compose exec app php artisan migrate
```

브라우저에서 `http://localhost:8080` 접속하여 확인합니다.
데이터 확인 http://localhost:8080/transactions 접속

### 환경변수 설정 (.env 주요 항목)

```
DB_CONNECTION=mysql
DB_HOST=db
DB_PORT=3306
DB_DATABASE=realestate
DB_USERNAME=laravel
DB_PASSWORD=secret

SESSION_DRIVER=redis
CACHE_STORE=redis
REDIS_HOST=redis
REDIS_PASSWORD=null
REDIS_PORT=6379

MOLIT_API_SERVICE_KEY=여기에_공공데이터포털_인증키
```

## 프로젝트 구조

```
app/
├── Console/Commands/
│   └── FetchRealEstateData.php   # 실거래가 API 호출 커맨드 (크론잡용)
├── Models/
│   ├── Transaction.php            # 실거래 원본 데이터
│   └── MonthlyAggregate.php       # 월별 집계 데이터
├── Services/
│   └── MolitApiService.php        # 국토부 API 호출 로직

docker/
├── php/Dockerfile
└── nginx/default.conf
```

## 데이터 수집 스케줄

Laravel Scheduler를 사용하여 월 단위로 실거래가 데이터를 자동 수집합니다.

```php
// routes/console.php
Schedule::command('realestate:fetch')->monthlyOn(1, '03:00');
```

수동 실행: `docker compose exec app php artisan realestate:fetch --month=YYYYMM` (month 생략 시 전월 기준)

크론잡 실패 시 Telegram 또는 slack으로 알림을 받도록 구성되어 있습니다 (추후 추가 예정).

## 브랜치 전략

```
main (배포용, GitHub Ruleset으로 보호됨)
  ↑ PR + Copilot 리뷰
dev (작업용)
```

- `main`: 직접 push 금지, PR을 통해서만 병합
- `dev`: 기능 개발 및 테스트

## 라이선스

개인 프로젝트 (MVP 단계)