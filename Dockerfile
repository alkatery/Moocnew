# syntax=docker/dockerfile:1

# ---------------------------------------------------------------------------
# MOOC Platform — PHP-FPM application image (Laravel 11, PHP 8.3)
# ---------------------------------------------------------------------------
# A single, dependency-complete image used for local development and as the
# base for the production build. Kubernetes is explicitly out of scope
# (ADR / PRD §0): we deploy this image to a managed host.
# ---------------------------------------------------------------------------
FROM php:8.3-fpm-alpine

# System libraries required by the PHP extensions below.
RUN apk add --no-cache \
        bash \
        git \
        icu-dev \
        libzip-dev \
        oniguruma-dev \
        postgresql-dev \
        $PHPIZE_DEPS

# PHP extensions: PostgreSQL (pdo_pgsql), intl (i18n), zip, bcmath
# (integer-minor money math), opcache, plus Redis via PECL.
RUN docker-php-ext-configure intl \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_pgsql \
        intl \
        zip \
        bcmath \
        opcache \
    && pecl install redis \
    && docker-php-ext-enable redis

# Composer (copied from the official image).
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Install PHP dependencies first to leverage Docker layer caching.
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

# Application source.
COPY . .

RUN composer dump-autoload --optimize --no-dev

EXPOSE 9000

CMD ["php-fpm"]
