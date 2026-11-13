#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PACKER_DIR="$(cd "$SCRIPT_DIR/../packer" && pwd)"

cd "$PACKER_DIR"

command -v packer >/dev/null 2>&1 || {
    echo "Packer is required: https://developer.hashicorp.com/packer/install" >&2
    exit 1
}

packer init .
packer validate .
packer build "$@" .

echo
echo "AMI build complete. Terraform selects the newest AMI tagged Component=hris and AppRef=<app_ref>."
