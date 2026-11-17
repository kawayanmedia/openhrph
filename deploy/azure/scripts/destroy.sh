#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
TF_DIR="$(cd "$SCRIPT_DIR/../terraform" && pwd)"
ENVIRONMENT="${1:-production}"

cd "$TF_DIR"

command -v terraform >/dev/null 2>&1 || {
    echo "Terraform is required: https://developer.hashicorp.com/terraform/install" >&2
    exit 1
}

echo "This destroys the VM, VNet, Key Vault, storage account, and managed identity."
echo "Key Vault purge protection and soft delete may keep the vault name reserved."
read -r -p "Type the environment name ($ENVIRONMENT) to continue: " confirmation

if [ "$confirmation" != "$ENVIRONMENT" ]; then
    echo "Aborted."
    exit 1
fi

terraform init
terraform destroy
