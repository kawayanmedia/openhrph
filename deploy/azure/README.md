# Azure deployment

This directory contains the golden-image and infrastructure code for running HRIS on Microsoft Azure.

- `packer/` builds an Ubuntu 24.04 managed image with PHP-FPM, Nginx, Caddy, Node, Go, MySQL 8, the application, and the payroll binary. No secrets are baked into the image.
- `terraform/` creates the resource group, VNet, NSG, static public IP, VM, managed identity, Key Vault, Storage account, and optional Azure DNS records.
- `scripts/` contains the build, deploy, and destroy wrappers.

Start with [Azure deployment](../../docs/deployment/azure.md).

```bash
az login
export AZURE_SUBSCRIPTION_ID=your-subscription-id
export AZURE_IMAGE_RESOURCE_GROUP=rg-hris-images
cp terraform/terraform.tfvars.example terraform/terraform.tfvars
make image
make apply
```
