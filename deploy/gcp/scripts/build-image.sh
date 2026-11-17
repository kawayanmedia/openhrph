#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PACKER_DIR="$(cd "$SCRIPT_DIR/../packer" && pwd)"

cd "$PACKER_DIR"

command -v packer >/dev/null 2>&1 || {
    echo "Packer is required: https://developer.hashicorp.com/packer/install" >&2
    exit 1
}

if [ -z "${GCP_PROJECT_ID:-}" ]; then
    echo "Set GCP_PROJECT_ID before building the image." >&2
    exit 1
fi

packer init .
packer validate -var "gcp_project_id=$GCP_PROJECT_ID" .
packer build -var "gcp_project_id=$GCP_PROJECT_ID" "$@" .

echo
echo "Image build complete. Terraform selects the newest image labeled component=hris and app_ref=<app_ref>."
