# 부동산 실거래가 시각화 MVP

## 프로젝트 개요
국토교통부 아파트매매 실거래가 Open API를 활용해 특정 지역(구리시 딸기원 인근, 서울 동대문구 장안동)의
시세 추이를 차트/테이블로 보여주는 서비스. 개인 사이드 프로젝트, MVP 단계.

## 기술 스택
- Laravel (PHP 8.5-FPM)
- MariaDB 11.4 (SingleStore 아님 — 비용 절감을 위해 MariaDB로 결정)
- Redis 7 (세션/캐시)
- Nginx
- TradingView Lightweight Charts (프론트엔드)
- Docker Compose (로컬 개발), Oracle Cloud Free Tier VPS (배포 예정)

## 개발 환경
- 로컬: Windows 데스크탑 (Docker Desktop + WSL2)
- Windows 특성상 3177~3813, 50000~50059 포트 대역이 예약되어 있음 — 새 포트 매핑 시 주의
- DB 포트는 13306으로 매핑됨 (호스트), 컨테이너 내부는 3306 그대로

## 코딩 컨벤션
- Laravel 표준 관례 따름 (PSR-12)
- API 호출 로직은 반드시 Service 클래스로 분리 (Controller에 직접 작성 금지)
- 마이그레이션 파일명은 Laravel 기본 규칙 따름

## 브랜치 전략
- `main`: 배포용, GitHub Ruleset으로 보호 (PR 필수, force push 금지)
- `dev`: 작업용
- PR 생성 시 Copilot 코드 리뷰 추가

## 절대 하지 말 것
- `.env` 파일을 git에 커밋하지 않기 (Public 저장소이므로 특히 주의)
- API 서비스키를 코드에 하드코딩하지 않기 — 반드시 `.env` → `config/services.php` 경유
- `docker/` 하위 설정 파일(Dockerfile, nginx, mariadb 설정)은 인프라 담당 범위이므로 백엔드 로직 작업 시 임의 수정 금지
