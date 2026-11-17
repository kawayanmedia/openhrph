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

echo "This destroys the Compute Engine instance, VPC, Cloud Storage bucket, secret, and IAM resources."
echo "Set deletion_protection=false and bucket_force_destroy=true only for disposable stacks."
read -r -p "Type the environment name ($ENVIRONMENT) to continue: " confirmation

if [ "$confirmation" != "$ENVIRONMENT" ]; then
    echo "Aborted."
    exit 1
fi

terraform init
terraform destroy
