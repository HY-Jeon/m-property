# Docker 개발환경 세팅 기록

부동산 실거래가 시각화 MVP 프로젝트의 로컬 Docker 환경 구축 과정 및 트러블슈팅 기록입니다.

## 최종 스택

- Laravel (PHP 8.3-FPM)
- Nginx 1.27
- MariaDB 11.4
- Redis 7

## 폴더 구조

```
C:\life\
├── app/                        # 라라벨 애플리케이션 코드
├── database/migrations/        # 마이그레이션 파일
├── docker-compose.yml
├── docker/
│   ├── php/
│   │   └── Dockerfile
│   ├── nginx/
│   │   ├── default.conf
│   │   └── logs/               # nginx access/error 로그
│   └── mariadb/
│       ├── my.cnf              # 슬로우쿼리/에러 로그 설정
│       └── logs/
├── .env
└── README.md
```

## 실행 명령어

```powershell
# 최초 빌드 및 실행
docker compose up -d --build

# 컨테이너 상태 확인
docker compose ps

# 의존성 설치
docker compose exec app composer install

# 앱 키 생성
docker compose exec app php artisan key:generate

# 마이그레이션
docker compose exec app php artisan migrate

# DB 연결 상태 확인
docker compose exec app php artisan db:show

# 마이그레이션 상태 확인
docker compose exec app php artisan migrate:status
```

## 트러블슈팅 기록

### 1. `resolve : GetFileAttributesEx ... The system cannot find the file specified`

**원인**: `docker-compose.yml`이 참조하는 `docker/php/Dockerfile` 등 하위 폴더 구조가 실제로 존재하지 않음 (파일들이 루트에 평평하게 놓여있었음)

**해결**: 폴더 구조를 아래처럼 맞춰서 파일 이동
```powershell
mkdir docker\php, docker\nginx\logs, docker\mariadb\logs
Move-Item Dockerfile docker\php\Dockerfile
Move-Item default.conf docker\nginx\default.conf
Move-Item my.cnf docker\mariadb\my.cnf
```

### 2. 포트 3306 바인딩 실패 (`bind: An attempt was made to access a socket in a way forbidden by its access permissions`)

**원인**: Windows(Hyper-V/WSL2)가 특정 포트 대역을 동적으로 예약(exclude)해둔 상태. 확인 명령어:
```powershell
netsh interface ipv4 show excludedportrange protocol=tcp
```
예약 범위(예: 3177~3813)에 3306이 포함되어 있으면 어떤 프로그램도 그 포트를 열 수 없음.

**해결**: 예약 범위 밖의 높은 포트 번호로 변경
```yaml
# docker-compose.yml (db 서비스)
ports:
  - "13306:3306"   # 호스트만 변경, 컨테이너 내부는 3306 유지
```
컨테이너 간 통신(`DB_HOST=db`, `DB_PORT=3306`)은 영향 없음. 외부 DB 툴(HeidiSQL 등) 접속 시에만 13306 사용.

### 3. `composer create-project` 시 `Project directory "/var/www/." is not empty`

**원인**: 이미 `docker-compose.yml`, `docker/` 폴더 등이 있는 상태에서 라라벨 신규 설치 시도

**해결**: 임시 경로에 설치 후 기존 파일 덮어쓰지 않고 병합
```powershell
docker compose exec app composer create-project laravel/laravel /tmp/laravel-fresh
docker compose exec app bash -c "cp -rn /tmp/laravel-fresh/. /var/www/ && rm -rf /tmp/laravel-fresh"
```

### 4. `World-writable config file '/etc/mysql/conf.d/custom.cnf' is ignored`

**원인**: Windows에서 마운트된 파일의 권한이 개방적(777)이라 MariaDB가 보안상 설정 파일을 무시함

**해결** (선택 사항, 슬로우 쿼리 로그 기능에만 영향):
```powershell
docker compose exec db chmod 644 /etc/mysql/conf.d/custom.cnf
docker compose restart db
```
Windows 볼륨 마운트 특성상 재시작 시 권한이 되돌아갈 수 있음 — 급하지 않으면 보류 가능.

### 5. `The "intl" PHP extension is required to use the [format] method`

**원인**: PHP 이미지에 `intl` 확장이 설치되어 있지 않음 (`artisan db:show` 등 일부 출력 포맷팅에서 사용)

**해결**: Dockerfile에 확장 추가
```dockerfile
RUN apt-get update && apt-get install -y \\
    ... \\
    libicu-dev \\
    && docker-php-ext-install ... intl
```
이후 재빌드:
```powershell
docker compose up -d --build app
```

## 참고 — .env 필수 설정값

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
```

## 다음 작업

- [ ] 국토부 실거래가 API 연동 (`MolitApiService`)
- [ ] `Transaction`, `MonthlyAggregate` 모델 및 마이그레이션 작성
- [ ] 데이터 수집 커맨드 (`realestate:fetch`) 작성 및 스케줄 등록
- [ ] 크론잡 실패 시 Telegram 알림 연동
- [ ] 프론트엔드 차트(Lightweight Charts) 연동
