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

# Azure CLI is used by the bootstrap and backup helpers to read Key Vault and
# write to Blob Storage with the instance's managed identity.
curl -sL https://aka.ms/InstallAzureCLIDeb | bash
az version

# Caddy terminates TLS in front of Nginx. Prefer the official repository over
# the older Ubuntu package.
apt-get install -y --no-install-recommends \
    debian-keyring \
    debian-archive-keyring
curl -1sLf 'https://dl.cloudsmith.io/public/caddy/stable/gpg.key' \
    | gpg --dearmor -o /usr/share/keyrings/caddy-stable-archive-keyring.gpg
curl -1sLf 'https://dl.cloudsmith.io/public/caddy/stable/debian.deb.txt' \
    > /etc/apt/sources.list.d/caddy-stable.list

apt-get update
apt-get install -y --no-install-recommends caddy

caddy version

systemctl enable nginx caddy
timedatectl set-timezone UTC
