#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PACKER_DIR="$(cd "$SCRIPT_DIR/../packer" && pwd)"

cd "$PACKER_DIR"

command -v packer >/dev/null 2>&1 || {
    echo "Packer is required: https://developer.hashicorp.com/packer/install" >&2
    exit 1
}

if ! az account show >/dev/null 2>&1; then
    echo "Run 'az login' before building the image." >&2
    exit 1
fi

: "${AZURE_SUBSCRIPTION_ID:?Set AZURE_SUBSCRIPTION_ID}"
: "${AZURE_IMAGE_RESOURCE_GROUP:?Set AZURE_IMAGE_RESOURCE_GROUP}"

packer init .
packer validate \
    -var "subscription_id=$AZURE_SUBSCRIPTION_ID" \
    -var "image_resource_group=$AZURE_IMAGE_RESOURCE_GROUP" \
    .
packer build \
    -var "subscription_id=$AZURE_SUBSCRIPTION_ID" \
    -var "image_resource_group=$AZURE_IMAGE_RESOURCE_GROUP" \
    "$@" \
    .

echo
echo "Image build complete. Terraform selects the newest image named hris-<app_ref>-*."
