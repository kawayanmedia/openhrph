#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
TF_DIR="$(cd "$SCRIPT_DIR/../terraform" && pwd)"
AUTO_APPROVE="${1:-}"

cd "$TF_DIR"

command -v terraform >/dev/null 2>&1 || {
    echo "Terraform is required: https://developer.hashicorp.com/terraform/install" >&2
    exit 1
}

if [ ! -f terraform.tfvars ]; then
    echo "Create terraform.tfvars from terraform.tfvars.example first." >&2
    exit 1
fi

terraform init
terraform fmt -recursive
terraform validate

if [ "$AUTO_APPROVE" = "--auto-approve" ]; then
    terraform apply -auto-approve
else
    terraform apply
fi

echo
terraform output
