#!/usr/bin/env bash
set -euo pipefail

APP_DIR=/var/www/html
DB_WAIT_SECONDS="${DB_WAIT_SECONDS:-180}"

log() {
    printf '[hris-entrypoint] %s\n' "$*"
}

prepare_directories() {
    mkdir -p \
        "$APP_DIR/bootstrap/cache" \
        "$APP_DIR/storage/app/public" \
        "$APP_DIR/storage/framework/cache/data" \
        "$APP_DIR/storage/framework/sessions" \
        "$APP_DIR/storage/framework/views" \
        "$APP_DIR/storage/logs"

    chown -R www-data:www-data "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"
    chmod -R ug+rwX "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"

    if [ ! -L "$APP_DIR/public/storage" ]; then
        gosu www-data php "$APP_DIR/artisan" storage:link >/dev/null 2>&1 || true
    fi
}

wait_for_database() {
    if [ "${DB_CONNECTION:-mysql}" != "mysql" ]; then
        return 0
    fi

    log "waiting for MySQL at ${DB_HOST:-127.0.0.1}:${DB_PORT:-3306}"
    local deadline=$((SECONDS + DB_WAIT_SECONDS))

    # shellcheck disable=SC2016
    until php -r '
        $host = getenv("DB_HOST") ?: "127.0.0.1";
        $port = getenv("DB_PORT") ?: "3306";
        $name = getenv("DB_DATABASE") ?: "";
        $user = getenv("DB_USERNAME") ?: "";
        $pass = getenv("DB_PASSWORD") ?: "";

        try {
            new PDO("mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4", $user, $pass, [
                PDO::ATTR_TIMEOUT => 3,
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
        } catch (Throwable $e) {
            fwrite(STDERR, $e->getMessage() . PHP_EOL);
            exit(1);
        }
    '; do
        if [ "$SECONDS" -ge "$deadline" ]; then
            log "MySQL did not become ready within ${DB_WAIT_SECONDS}s"
            return 1
        fi
        sleep 3
    done
}

warm_caches() {
    if [ "${HRIS_WARM_CACHE:-1}" = "0" ]; then
        return 0
    fi

    gosu www-data php "$APP_DIR/artisan" config:cache
    gosu www-data php "$APP_DIR/artisan" event:cache
    gosu www-data php "$APP_DIR/artisan" view:cache
}

prepare_directories

case "${1:-}" in
    php-fpm)
        wait_for_database
        warm_caches
        exec "$@"
        ;;
    /usr/local/bin/hris-bootstrap-db)
        wait_for_database
        exec gosu www-data "$@"
        ;;
    php)
        wait_for_database
        if [ "${HRIS_RUN_DB_INIT:-0}" = "1" ]; then
            gosu www-data /usr/local/bin/hris-bootstrap-db
        fi
        warm_caches
        exec gosu www-data "$@"
        ;;
    nginx)
        export NGINX_PORT="${NGINX_PORT:-${PORT:-80}}"
        export PHP_FPM_HOST="${PHP_FPM_HOST:-app}"
        # shellcheck disable=SC2016
        envsubst '${NGINX_PORT} ${PHP_FPM_HOST}' \
            < /etc/nginx/templates/default.conf.template \
            > /etc/nginx/conf.d/default.conf
        nginx -t
        exec "$@"
        ;;
    *)
        exec "$@"
        ;;
esac
