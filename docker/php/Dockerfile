# The application's PHP runtime for local development and CI (TASK-001); the
# production deployment definition is TASK-072's. Images are pinned by digest.
FROM composer:2.10.3@sha256:af98f42dfff7c68ba8d53c2164fd9fde1087b7d449514baa38c418b1f6bc4bac AS composer

FROM php:8.5.11-fpm-alpine3.24@sha256:fa01fb1645cd0fc566a5f146b099adace33b906571f972f71f2182a7c12d1cd7

# pdo_pgsql for PostgreSQL; the Valkey client is the predis package, so no extension.
RUN set -eux; \
    apk add --no-cache libpq unzip; \
    apk add --no-cache --virtual .build-deps $PHPIZE_DEPS libpq-dev; \
    docker-php-ext-install -j"$(nproc)" pdo_pgsql; \
    apk del --no-network .build-deps; \
    cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"; \
    php -m | grep -qx pdo_pgsql

COPY --from=composer /usr/bin/composer /usr/local/bin/composer

ENV COMPOSER_HOME=/tmp/composer

WORKDIR /var/www/html
