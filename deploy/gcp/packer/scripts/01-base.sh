#!/usr/bin/env bash
set -euo pipefail

export DEBIAN_FRONTEND=noninteractive

apt-get update
apt-get upgrade -y
apt-get install -y --no-install-recommends \
    apt-transport-https \
    ca-certificates \
    curl \
    default-mysql-client \
    git \
    gnupg \
    jq \
    lsb-release \
    mysql-server \
    nginx \
    software-properties-common \
    unzip \
    zip

install -d -m 0755 /var/log/php-fpm /var/log/hris
chown -R www-data:www-data /var/log/php-fpm

# Google Cloud CLI is used by the bootstrap, backup, and deploy helpers.
curl -fsSL https://packages.cloud.google.com/apt/doc/apt-key.gpg \
    | gpg --dearmor -o /usr/share/keyrings/cloud.google.gpg
printf 'deb [signed-by=/usr/share/keyrings/cloud.google.gpg] https://packages.cloud.google.com/apt cloud-sdk main\n' \
    > /etc/apt/sources.list.d/google-cloud-sdk.list

# Caddy terminates TLS in front of Nginx. Ubuntu 24.04 packages an older
# release, so prefer the official repository.
apt-get install -y --no-install-recommends \
    debian-keyring \
    debian-archive-keyring
curl -1sLf 'https://dl.cloudsmith.io/public/caddy/stable/gpg.key' \
    | gpg --dearmor -o /usr/share/keyrings/caddy-stable-archive-keyring.gpg
curl -1sLf 'https://dl.cloudsmith.io/public/caddy/stable/debian.deb.txt' \
    > /etc/apt/sources.list.d/caddy-stable.list

apt-get update
apt-get install -y --no-install-recommends google-cloud-cli caddy

gcloud --version
caddy version

systemctl enable nginx caddy
timedatectl set-timezone UTC
