#!/usr/bin/env bash
set -euo pipefail

: "${PHP_VERSION:?PHP_VERSION is required}"

# Remove build artefacts and any trace of the temporary environment.
rm -f /var/www/hris/.env
rm -rf /var/www/hris/node_modules
find /var/www/hris/storage/framework -type f ! -name '.gitignore' -delete
find /var/www/hris/bootstrap/cache -type f ! -name '.gitignore' -delete
chown -R www-data:www-data /var/www/hris/storage /var/www/hris/bootstrap/cache
chmod -R ug+rwX /var/www/hris/storage /var/www/hris/bootstrap/cache

apt-get autoremove -y
apt-get clean
rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/* /root/.cache /home/ubuntu/.cache /home/ubuntu/.npm

# Cloud-init regenerates machine-id and SSH host keys on first boot.
truncate -s 0 /etc/machine-id
rm -f /etc/ssh/ssh_host_*
cloud-init clean --logs --seed || true

# Services stay enabled for first boot, but should not be left running in the image.
systemctl stop "php${PHP_VERSION}-fpm" nginx hris-queue || true
