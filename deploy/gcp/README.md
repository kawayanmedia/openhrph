# GCP deployment

This directory contains the golden-image and infrastructure code for running HRIS on Google Cloud.

- `packer/` builds an Ubuntu 24.04 image with PHP-FPM, Nginx, Caddy, Node, Go, MySQL 8, the application, and the payroll binary. No secrets are baked into the image.
- `terraform/` creates the VPC, firewall, static IP, application instance, service account, Cloud Storage bucket, Secret Manager secret, and optional Cloud DNS records.
- `scripts/` contains the build, deploy, and destroy wrappers.

Start with [GCP deployment](../../docs/deployment/gcp.md).

```bash
export GCP_PROJECT_ID=your-project-id
cp terraform/terraform.tfvars.example terraform/terraform.tfvars
make image
make apply
```
