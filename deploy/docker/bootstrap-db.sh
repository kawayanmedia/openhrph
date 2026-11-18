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

COUNTRY="${OPENHR_COUNTRY:-PH}"

if [ -n "${OPENHR_SECTOR:-}" ]; then
    log "configuring the installation sector and country"
    php artisan openhr:install \
        --sector="$OPENHR_SECTOR" \
        --country="$COUNTRY" \
        --skip-statutory \
        --no-interaction || log "installation already configured; continuing"
fi

log "seeding the default catalogues"
php artisan db:seed --class='Database\Seeders\ReferenceDataSeeder' --force

log "importing statutory tables for $COUNTRY"
php artisan payroll:statutory:import --country="$COUNTRY"

if [ "${OPENHR_SECTOR:-}" = "Government" ]; then
    log "skipping the NWPC wage orders for a government installation"
else
    log "importing the 2026 wage orders"
    php artisan payroll:wage-orders:import
fi

log "importing the 2026 holiday calendar"
if ! php artisan payroll:holidays:import; then
    log "holiday import skipped; run payroll:holidays:import after creating the first company"
fi

log "database is ready"
