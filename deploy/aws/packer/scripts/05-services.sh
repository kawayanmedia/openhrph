#!/usr/bin/env bash
set -euo pipefail

: "${PHP_VERSION:?PHP_VERSION is required}"

# Nginx
sed "s/__PHP_VERSION__/${PHP_VERSION}/g" /tmp/hris-nginx.conf > /etc/nginx/sites-available/hris
ln -sf /etc/nginx/sites-available/hris /etc/nginx/sites-enabled/hris
rm -f /etc/nginx/sites-enabled/default
nginx -t

# systemd units
install -m 0644 /tmp/hris-queue.service /etc/systemd/system/hris-queue.service
install -m 0644 /tmp/hris-scheduler.service /etc/systemd/system/hris-scheduler.service
install -m 0644 /tmp/hris-scheduler.timer /etc/systemd/system/hris-scheduler.timer
install -m 0644 /tmp/hris-reverb.service /etc/systemd/system/hris-reverb.service
install -m 0644 /tmp/hris-backup.service /etc/systemd/system/hris-backup.service
install -m 0644 /tmp/hris-backup.timer /etc/systemd/system/hris-backup.timer

install -m 0755 /tmp/hris-bootstrap /usr/local/bin/hris-bootstrap
install -m 0755 /tmp/hris-deploy /usr/local/bin/hris-deploy
install -m 0755 /tmp/hris-backup /usr/local/bin/hris-backup

systemctl daemon-reload
systemctl enable "php${PHP_VERSION}-fpm" nginx hris-queue
systemctl enable hris-scheduler.timer
systemctl enable hris-backup.timer
# Reverb is optional and disabled by default; enable it if BROADCAST_CONNECTION=reverb.
systemctl disable hris-reverb || true
