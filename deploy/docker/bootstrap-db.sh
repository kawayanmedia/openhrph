#!/usr/bin/env bash
set -euo pipefail

cd /var/www/html

log() {
    printf '[hris-init] %s\n' "$*"
}

if [ -z "${DB_HOST:-}" ] || [ -z "${DB_DATABASE:-}" ] || [ -z "${DB_USERNAME:-}" ]; then
    log "DB_HOST, DB_DATABASE, and DB_USERNAME are required"
    exit 1
fi

log "running migrations"
php artisan migrate --force

log "importing statutory tables"
php artisan payroll:statutory:import

log "importing the 2026 wage orders"
php artisan payroll:wage-orders:import

log "importing the 2026 holiday calendar"
if ! php artisan payroll:holidays:import; then
    log "holiday import skipped; run payroll:holidays:import after creating the first company"
fi

log "database is ready"
