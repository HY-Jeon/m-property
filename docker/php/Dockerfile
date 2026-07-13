FROM php:8.3-fpm

# 시스템 패키지 + PHP 확장에 필요한 라이브러리 설치
RUN apt-get update && apt-get install -y \
    git \
    curl \
    unzip \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    libicu-dev \
    zip \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip intl

# Composer 설치
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

# 권한 설정 (www-data로 실행)
RUN chown -R www-data:www-data /var/www

EXPOSE 9000

CMD ["php-fpm"]
