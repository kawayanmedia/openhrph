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
    nginx \
    snapd \
    software-properties-common \
    unzip \
    zip

systemctl enable nginx

# AWS CLI v2 is used by the bootstrap, backup, and deploy helpers.
curl -fsSL "https://awscli.amazonaws.com/awscli-exe-linux-x86_64.zip" -o /tmp/awscliv2.zip
unzip -q /tmp/awscliv2.zip -d /tmp
/tmp/aws/install
rm -rf /tmp/aws /tmp/awscliv2.zip
aws --version

# Session Manager avoids exposing SSH in production. The Terraform stack also
# works without it when a key pair is supplied.
systemctl start snapd
snap install amazon-ssm-agent --classic
systemctl enable "snap.amazon-ssm-agent.amazon-ssm-agent.service"

# Keep the instance's clock and logs predictable.
timedatectl set-timezone UTC
