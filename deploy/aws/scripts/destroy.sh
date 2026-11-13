#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
TF_DIR="$(cd "$SCRIPT_DIR/../terraform" && pwd)"

cd "$TF_DIR"

command -v terraform >/dev/null 2>&1 || {
    echo "Terraform is required: https://developer.hashicorp.com/terraform/install" >&2
    exit 1
}

echo "This destroys the EC2 instance, RDS database, S3 bucket, and networking."
echo "Set deletion_protection=false and skip_final_snapshot=true only for disposable stacks."
read -r -p "Type the environment name (${1:-production}) to continue: " confirmation

if [ "$confirmation" != "${1:-production}" ]; then
    echo "Aborted."
    exit 1
fi

terraform init
terraform destroy
