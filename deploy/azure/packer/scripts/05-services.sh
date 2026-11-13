#!/usr/bin/env bash
set -euo pipefail

: "${PHP_VERSION:?PHP_VERSION is required}"

# Nginx sits behind Caddy, which owns ports 80 and 443.
sed "s/__PHP_VERSION__/${PHP_VERSION}/g" /tmp/hris-nginx.conf > /etc/nginx/sites-available/hris
ln -sf /etc/nginx/sites-available/hris /etc/nginx/sites-enabled/hris
rm -f /etc/nginx/sites-enabled/default

install -m 0644 /tmp/99-hris.ini "/etc/php/${PHP_VERSION}/fpm/conf.d/99-hris.ini"
install -m 0644 /tmp/99-hris.ini "/etc/php/${PHP_VERSION}/cli/conf.d/99-hris.ini"
sed "s/__PHP_VERSION__/${PHP_VERSION}/g" /tmp/hris-fpm-pool.conf > "/etc/php/${PHP_VERSION}/fpm/pool.d/www.conf"

install -d -m 0755 /var/log/php-fpm
chown -R www-data:www-data /var/log/php-fpm

install -d -m 0755 /etc/mysql/mysql.conf.d
install -m 0644 /tmp/99-hris.cnf /etc/mysql/mysql.conf.d/99-hris.cnf

install -m 0644 /tmp/hris-queue.service /etc/systemd/system/hris-queue.service
install -m 0644 /tmp/hris-scheduler.service /etc/systemd/system/hris-scheduler.service
install -m 0644 /tmp/hris-scheduler.timer /etc/systemd/system/hris-scheduler.timer
install -m 0644 /tmp/hris-reverb.service /etc/systemd/system/hris-reverb.service
install -m 0644 /tmp/hris-bootstrap.service /etc/systemd/system/hris-bootstrap.service
install -m 0644 /tmp/hris-mysql-data.service /etc/systemd/system/hris-mysql-data.service
install -d -m 0755 /etc/systemd/system/mysql.service.d
install -m 0644 /tmp/10-hris-data.conf /etc/systemd/system/mysql.service.d/10-hris-data.conf
install -m 0644 /tmp/hris-backup.service /etc/systemd/system/hris-backup.service
install -m 0644 /tmp/hris-backup.timer /etc/systemd/system/hris-backup.timer

install -m 0755 /tmp/hris-bootstrap /usr/local/bin/hris-bootstrap
install -m 0755 /tmp/hris-mount-data /usr/local/bin/hris-mount-data
install -m 0755 /tmp/hris-deploy /usr/local/bin/hris-deploy
install -m 0755 /tmp/hris-backup /usr/local/bin/hris-backup

# Caddy starts with a plain HTTP listener. The bootstrap replaces this file
# with a domain block when APP_URL uses HTTPS.
cat > /etc/caddy/Caddyfile <<'CADDY'
:80 {
    encode zstd gzip
    reverse_proxy 127.0.0.1:8080
}
CADDY

nginx -t
caddy validate --config /etc/caddy/Caddyfile

systemctl daemon-reload
systemctl enable "php${PHP_VERSION}-fpm" nginx caddy mysql hris-queue
systemctl enable hris-mysql-data.service
systemctl enable hris-scheduler.timer
systemctl enable hris-backup.timer
# Reverb is optional and disabled by default; enable it if BROADCAST_CONNECTION=reverb.
systemctl disable hris-reverb || true
