#!/usr/bin/env bash
set -euo pipefail

export DEBIAN_FRONTEND=noninteractive

# Node is only needed to build Vite assets. It stays in the image so operators
# can run the documented deploy helper after a code change.
curl -fsSL https://deb.nodesource.com/setup_22.x | bash -
apt-get install -y --no-install-recommends nodejs
node --version
npm --version

GO_VERSION="${GO_VERSION:-1.24.6}"
curl -fsSL "https://go.dev/dl/go${GO_VERSION}.linux-amd64.tar.gz" -o /tmp/go.tar.gz
tar -C /usr/local -xzf /tmp/go.tar.gz
rm -f /tmp/go.tar.gz
printf 'export PATH="$PATH:/usr/local/go/bin"\n' > /etc/profile.d/go.sh
chmod 0644 /etc/profile.d/go.sh
/usr/local/go/bin/go version
