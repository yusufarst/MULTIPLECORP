# The application's PHP runtime for local development and CI (TASK-001); the
# production deployment definition is TASK-072's. Images are pinned by digest.
FROM composer:2.10.3@sha256:af98f42dfff7c68ba8d53c2164fd9fde1087b7d449514baa38c418b1f6bc4bac AS composer

FROM php:8.5.11-fpm-alpine3.24@sha256:fa01fb1645cd0fc566a5f146b099adace33b906571f972f71f2182a7c12d1cd7

# pdo_pgsql for PostgreSQL; the Valkey client is the predis package, so no extension.
# TASK-022 (DIR-099): GD with only the JPEG, PNG and WebP codecs, the image library of SECURITY FL-03 and H9-12;
# zip, read by the structural inspection of office packages (FL-02, FL-06); WeasyPrint, the renderer of FL-08,
# from Alpine's own package, run by the application as an external process, with DejaVu as the system fallback
# font Pango needs — a document sets its own bundled fonts (FL-08).
RUN set -eux; \
    apk add --no-cache libpq unzip libjpeg-turbo libpng libwebp libzip weasyprint=68.1-r1 font-dejavu=2.37-r6; \
    apk add --no-cache --virtual .build-deps $PHPIZE_DEPS libpq-dev libjpeg-turbo-dev libpng-dev libwebp-dev libzip-dev; \
    docker-php-ext-configure gd --with-jpeg --with-webp; \
    docker-php-ext-install -j"$(nproc)" pdo_pgsql gd zip; \
    apk del --no-network .build-deps; \
    cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"; \
    php -m | grep -qx pdo_pgsql; \
    php -m | grep -qx gd; \
    php -m | grep -qx zip; \
    weasyprint --version

COPY --from=composer /usr/bin/composer /usr/local/bin/composer

ENV COMPOSER_HOME=/tmp/composer

WORKDIR /var/www/html
