#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
DOCKER_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
BACKUP_DIR="${BACKUP_DIR:-$DOCKER_DIR/backups}"
RETENTION_DAYS="${BACKUP_RETENTION_DAYS:-14}"
STAMP="$(date +%Y%m%d%H%M%S)"

cd "$DOCKER_DIR"

mkdir -p "$BACKUP_DIR"

echo "Dumping the database"
docker compose exec -T db sh -c \
    'exec mysqldump -uroot -p"$MYSQL_ROOT_PASSWORD" --single-transaction --quick --routines --triggers "$MYSQL_DATABASE"' \
    | gzip > "$BACKUP_DIR/hris-db-$STAMP.sql.gz"

echo "Archiving uploaded files"
docker compose exec -T app \
    tar -czf - -C /var/www/html/storage/app/public . \
    > "$BACKUP_DIR/hris-storage-$STAMP.tar.gz"

echo "Removing backups older than $RETENTION_DAYS days"
find "$BACKUP_DIR" -type f -name 'hris-*' -mtime "+$RETENTION_DAYS" -delete

if [ -n "${BACKUP_S3_URI:-}" ]; then
    if command -v aws >/dev/null 2>&1; then
        echo "Uploading backups to $BACKUP_S3_URI"
        aws s3 cp "$BACKUP_DIR/hris-db-$STAMP.sql.gz" "$BACKUP_S3_URI/database/"
        aws s3 cp "$BACKUP_DIR/hris-storage-$STAMP.tar.gz" "$BACKUP_S3_URI/storage/"
    else
        echo "BACKUP_S3_URI is set but the AWS CLI is not installed; skipping upload." >&2
    fi
fi

echo "Backups written to $BACKUP_DIR"
