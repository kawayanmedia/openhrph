#!/usr/bin/env bash
set -euo pipefail

: "${APP_REPO:?APP_REPO is required}"
: "${APP_REF:?APP_REF is required}"

APP_DIR=/var/www/hris

mkdir -p /var/www/.composer /var/www/.npm
chown -R www-data:www-data /var/www/.composer /var/www/.npm

rm -rf "$APP_DIR"
if ! git clone --depth 1 --branch "$APP_REF" "$APP_REPO" "$APP_DIR"; then
    # Branch/tag clone failed; retry so a commit SHA can be checked out.
    rm -rf "$APP_DIR"
    git clone "$APP_REPO" "$APP_DIR"
    git -C "$APP_DIR" checkout "$APP_REF"
fi

cd "$APP_DIR"

# Build-time only: the real APP_KEY and database credentials arrive from SSM on
# first boot. This temporary file is removed before the image is sealed.
cp .env.example .env
php artisan key:generate --force

composer install \
    --no-dev \
    --prefer-dist \
    --no-interaction \
    --no-progress \
    --optimize-autoloader

npm ci --no-audit --no-fund
npm run build

make -C payroll-engine build
./bin/payroll-engine --version

php artisan storage:link || true
php artisan config:clear || true
php artisan route:clear || true
php artisan view:clear || true

rm -f .env
rm -rf node_modules

mkdir -p storage/app/public storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
chown -R www-data:www-data "$APP_DIR"
chmod -R ug+rwX storage bootstrap/cache
