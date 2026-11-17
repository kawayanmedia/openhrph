# syntax=docker/dockerfile:1.7

ARG PHP_VERSION=8.4
ARG NODE_VERSION=22
ARG GO_VERSION=1.24

# ---------------------------------------------------------------------------
# Front-end assets
# ---------------------------------------------------------------------------
FROM node:${NODE_VERSION}-bookworm AS assets

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund

COPY vite.config.mjs ./
COPY resources ./resources
COPY public ./public
RUN npm run build

# ---------------------------------------------------------------------------
# Go payroll engine
# ---------------------------------------------------------------------------
FROM golang:${GO_VERSION}-bookworm AS payroll

WORKDIR /src

COPY payroll-engine/go.mod payroll-engine/go.sum ./
RUN go mod download

COPY payroll-engine ./
RUN CGO_ENABLED=0 go build \
    -trimpath \
    -ldflags "-s -w" \
    -o /out/payroll-engine \
    ./cmd/payroll-engine

# ---------------------------------------------------------------------------
# Composer dependencies
# ---------------------------------------------------------------------------
FROM composer:2 AS vendor

WORKDIR /app

# The attendance package is a path repository. Mirror it so the vendor tree is
# self-contained and does not contain symlinks into the build stage.
ENV COMPOSER_MIRROR_PATH_REPOS=1

COPY composer.json composer.lock ./
COPY kawayanmedia ./kawayanmedia
RUN composer install \
    --no-dev \
    --no-scripts \
    --prefer-dist \
    --no-interaction \
    --no-progress \
    --optimize-autoloader \
    --ignore-platform-reqs

# ---------------------------------------------------------------------------
# Runtime
# ---------------------------------------------------------------------------
FROM php:${PHP_VERSION}-fpm-bookworm AS runtime

ARG APP_VERSION=dev

LABEL org.opencontainers.image.title="HRIS" \
      org.opencontainers.image.description="OpenHR PH payroll and HRIS" \
      org.opencontainers.image.version="${APP_VERSION}"

ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr \
    COMPOSER_ALLOW_SUPERUSER=1

# hadolint ignore=DL3008,SC2086
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        ca-certificates \
        curl \
        default-mysql-client \
        gettext-base \
        git \
        gosu \
        libfreetype6 \
        libicu72 \
        libjpeg62-turbo \
        libonig5 \
        libpng16-16 \
        libzip4 \
        netcat-openbsd \
        nginx \
        unzip \
    && apt-get install -y --no-install-recommends \
        $PHPIZE_DEPS \
        libfreetype6-dev \
        libicu-dev \
        libjpeg62-turbo-dev \
        libonig-dev \
        libpng-dev \
        libzip-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        gd \
        intl \
        mbstring \
        opcache \
        pcntl \
        pdo_mysql \
        zip \
    && apt-get purge -y --auto-remove \
        $PHPIZE_DEPS \
        libfreetype6-dev \
        libicu-dev \
        libjpeg62-turbo-dev \
        libonig-dev \
        libpng-dev \
        libzip-dev \
    && rm -rf /var/lib/apt/lists/*

COPY deploy/docker/php/99-hris.ini /usr/local/etc/php/conf.d/99-hris.ini
COPY deploy/docker/php/www.conf /usr/local/etc/php-fpm.d/www.conf
COPY deploy/docker/nginx/default.conf.template /etc/nginx/templates/default.conf.template
COPY deploy/docker/entrypoint.sh /usr/local/bin/hris-entrypoint
COPY deploy/docker/bootstrap-db.sh /usr/local/bin/hris-bootstrap-db
RUN chmod 0755 /usr/local/bin/hris-entrypoint /usr/local/bin/hris-bootstrap-db \
    && rm -f /etc/nginx/sites-enabled/default

WORKDIR /var/www/html

COPY --from=vendor /app/vendor ./vendor
COPY . .
COPY --from=assets /app/public/build ./public/build
COPY --from=payroll /out/payroll-engine ./bin/payroll-engine

# package:discover needs an application key file to exist at build time. The
# temporary environment is removed before the layer is sealed.
RUN mkdir -p bootstrap/cache \
    && cp .env.example .env \
    && php artisan key:generate --force \
    && php artisan package:discover --ansi \
    && php artisan storage:link \
    && rm -f .env \
    && mkdir -p \
        bootstrap/cache \
        storage/app/public \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
    && chown -R www-data:www-data storage bootstrap/cache public/storage \
    && chmod -R ug+rwX storage bootstrap/cache \
    && chmod 0755 bin/payroll-engine

EXPOSE 80 443 9000 8080

ENTRYPOINT ["/usr/local/bin/hris-entrypoint"]
CMD ["php-fpm", "-F"]
