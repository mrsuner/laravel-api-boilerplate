# syntax=docker/dockerfile:1.7

# ------------------------------------------------------------------------------
# Stage: vendor — install composer dependencies (cacheable)
# ------------------------------------------------------------------------------
FROM composer:2 AS vendor

WORKDIR /app

COPY composer.json composer.lock ./

# Install runtime deps only; dev deps are layered on later for the dev target.
RUN --mount=type=cache,target=/tmp/cache \
    composer install \
        --no-dev \
        --no-interaction \
        --no-scripts \
        --no-autoloader \
        --prefer-dist


# ------------------------------------------------------------------------------
# Stage: assets — build front-end bundle with Vite
# ------------------------------------------------------------------------------
FROM node:22-alpine AS assets

WORKDIR /app

COPY package.json ./
RUN npm install --no-audit --no-fund

COPY vite.config.js ./
COPY resources ./resources
RUN npm run build


# ------------------------------------------------------------------------------
# Stage: base — PHP-FPM with extensions required by the application
# ------------------------------------------------------------------------------
FROM php:8.4-fpm-alpine AS base

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_NO_INTERACTION=1 \
    PHP_OPCACHE_VALIDATE_TIMESTAMPS=0

# System packages: build deps removed after extension install to keep image lean.
RUN set -eux; \
    apk add --no-cache \
        bash \
        ca-certificates \
        curl \
        git \
        icu-libs \
        libpng \
        libjpeg-turbo \
        libwebp \
        libzip \
        oniguruma \
        postgresql-libs \
        tini \
        tzdata; \
    apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        autoconf \
        icu-dev \
        libpng-dev \
        libjpeg-turbo-dev \
        libwebp-dev \
        libzip-dev \
        linux-headers \
        oniguruma-dev \
        postgresql-dev; \
    docker-php-ext-configure gd --with-jpeg --with-webp; \
    docker-php-ext-install -j"$(nproc)" \
        bcmath \
        exif \
        gd \
        intl \
        mbstring \
        opcache \
        pcntl \
        pdo_pgsql \
        pgsql \
        sockets \
        zip; \
    pecl install redis; \
    docker-php-ext-enable redis; \
    apk del .build-deps; \
    rm -rf /tmp/pear

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/php/www.conf /usr/local/etc/php-fpm.d/zz-www.conf
COPY docker/entrypoint/app.sh /usr/local/bin/docker-entrypoint

RUN chmod +x /usr/local/bin/docker-entrypoint

# Map the image's www-data uid/gid to a configurable value at build time so
# bind-mounted files on the host stay writable from inside the container.
ARG WWW_USER_ID=1000
ARG WWW_GROUP_ID=1000
RUN set -eux; \
    apk add --no-cache shadow; \
    if [ "$(id -u www-data)" != "${WWW_USER_ID}" ]; then \
        usermod  -u "${WWW_USER_ID}" www-data; \
    fi; \
    if [ "$(id -g www-data)" != "${WWW_GROUP_ID}" ]; then \
        groupmod -g "${WWW_GROUP_ID}" www-data; \
    fi; \
    apk del shadow

WORKDIR /var/www/html

EXPOSE 9000

# The entrypoint runs shared bootstrap (storage dirs, prod caches, optional
# migrations) and then exec's the command supplied to the container. The
# image is role-agnostic on purpose — every service in docker-compose.yml
# specifies its own `command:` (php-fpm for the web, queue:work for the
# worker, schedule:work for the scheduler) so the compose file is the
# single source of truth for what each container does.
ENTRYPOINT ["/sbin/tini", "--", "/usr/local/bin/docker-entrypoint"]


# ------------------------------------------------------------------------------
# Stage: dev — code is bind-mounted at runtime; install dev composer deps
# ------------------------------------------------------------------------------
FROM base AS dev

ENV PHP_OPCACHE_VALIDATE_TIMESTAMPS=1

# Install xdebug in dev only; disabled by default, opt-in via XDEBUG_MODE env.
RUN set -eux; \
    apk add --no-cache --virtual .build-deps $PHPIZE_DEPS linux-headers; \
    pecl install xdebug; \
    docker-php-ext-enable xdebug; \
    apk del .build-deps; \
    rm -rf /tmp/pear

COPY docker/php/xdebug.ini /usr/local/etc/php/conf.d/zz-xdebug.ini

USER www-data


# ------------------------------------------------------------------------------
# Stage: prod — bake code, vendor, and built assets into the image
# ------------------------------------------------------------------------------
FROM base AS prod

COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/zz-opcache.ini

COPY --chown=www-data:www-data . /var/www/html
COPY --from=vendor --chown=www-data:www-data /app/vendor /var/www/html/vendor
COPY --from=assets --chown=www-data:www-data /app/public/build /var/www/html/public/build

RUN set -eux; \
    composer dump-autoload --optimize --classmap-authoritative; \
    mkdir -p storage/framework/{cache,sessions,testing,views} \
             storage/logs \
             bootstrap/cache; \
    chown -R www-data:www-data storage bootstrap/cache; \
    chmod -R ug+rwX storage bootstrap/cache

USER www-data
