# AWS deployment

This directory contains the golden-image and infrastructure code for running HRIS on AWS.

- `packer/` builds an Ubuntu 24.04 AMI with PHP-FPM, Nginx, Node, Go, the application, and the payroll binary. No secrets are baked into the image.
- `terraform/` creates the VPC, application EC2 instance, RDS MySQL database, S3 bucket, IAM/SSM wiring, and optional ALB/Route 53/ACM resources.
- `scripts/` contains the build, deploy, and destroy wrappers.

Start with [AWS AMI deployment](../../docs/deployment/aws-ami.md).

```bash
cp terraform/terraform.tfvars.example terraform/terraform.tfvars
make ami
make apply
```
