#!/usr/bin/env bash
set -euo pipefail

: "${PHP_VERSION:?PHP_VERSION is required}"

export DEBIAN_FRONTEND=noninteractive

if ! apt-cache show "php${PHP_VERSION}-fpm" >/dev/null 2>&1; then
    add-apt-repository -y ppa:ondrej/php
    apt-get update
fi

packages=(
    "php${PHP_VERSION}-bcmath"
    "php${PHP_VERSION}-cli"
    "php${PHP_VERSION}-common"
    "php${PHP_VERSION}-curl"
    "php${PHP_VERSION}-fpm"
    "php${PHP_VERSION}-gd"
    "php${PHP_VERSION}-intl"
    "php${PHP_VERSION}-mbstring"
    "php${PHP_VERSION}-mysql"
    "php${PHP_VERSION}-opcache"
    "php${PHP_VERSION}-xml"
    "php${PHP_VERSION}-zip"
)

apt-get install -y --no-install-recommends "${packages[@]}"

# Optional extensions: skip cleanly when the distro does not package them.
for optional in redis sqlite3; do
    if apt-cache show "php${PHP_VERSION}-${optional}" >/dev/null 2>&1; then
        apt-get install -y --no-install-recommends "php${PHP_VERSION}-${optional}"
    fi
done

curl -fsSL https://getcomposer.org/installer -o /tmp/composer-setup.php
php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer
rm -f /tmp/composer-setup.php
composer --version

install -m 0644 /tmp/99-hris.ini "/etc/php/${PHP_VERSION}/fpm/conf.d/99-hris.ini"
install -m 0644 /tmp/99-hris.ini "/etc/php/${PHP_VERSION}/cli/conf.d/99-hris.ini"

sed "s/__PHP_VERSION__/${PHP_VERSION}/g" /tmp/hris-fpm-pool.conf > "/etc/php/${PHP_VERSION}/fpm/pool.d/www.conf"

systemctl enable "php${PHP_VERSION}-fpm"
