#!/usr/bin/env bash
set -euo pipefail

: "${PHP_VERSION:?PHP_VERSION is required}"

rm -f /var/www/hris/.env
rm -rf /var/www/hris/node_modules
find /var/www/hris/storage/framework -type f ! -name '.gitignore' -delete
find /var/www/hris/bootstrap/cache -type f ! -name '.gitignore' -delete
chown -R www-data:www-data /var/www/hris/storage /var/www/hris/bootstrap/cache
chmod -R ug+rwX /var/www/hris/storage /var/www/hris/bootstrap/cache

apt-get autoremove -y
apt-get clean
rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/* \
    /root/.cache /root/.npm /root/.composer \
    /home/packer/.cache /home/packer/.npm /home/packer/.composer

# Cloud-init regenerates machine-id and SSH host keys on first boot.
truncate -s 0 /etc/machine-id
rm -f /etc/ssh/ssh_host_*
cloud-init clean --logs --seed || true

systemctl stop "php${PHP_VERSION}-fpm" nginx caddy mysql hris-queue || true
