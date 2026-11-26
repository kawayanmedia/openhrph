---
slug: /deployment
description: Deploy OpenHR PH with Docker Compose, AWS, GCP, Azure, or managed Laravel hosting.
---

# Deployment overview

OpenHR PH can run as a Docker Compose stack, on a golden-image VM in AWS, GCP, or Azure, on managed Laravel hosting, or serverless so an idle installation stops billing. Choose the path that matches your team's operational capacity and budget.

| Option | Best for | Trade-offs |
| --- | --- | --- |
| [Docker Compose](docker.md) | The shortest path to a reproducible self-hosted stack, local production-like testing, and single-VM deployments | You own the host, MySQL container, patching, backups, and scaling |
| [AWS AMI + Terraform](aws-ami.md) | Teams that want full control, predictable infrastructure, and a reproducible golden image | You own patching, backups, scaling, and monitoring |
| [GCP image + Terraform](gcp.md) | Teams running on Google Cloud that want a golden image and native MySQL | Single VM; you own patching, backups, and scaling |
| [Azure image + Terraform](azure.md) | Teams running on Microsoft Azure that want a golden image and native MySQL | Single VM; you own patching, backups, and scaling |
| [Laravel Cloud](laravel-cloud.md) | Teams that want managed PHP hosting, queues, and databases | Less infrastructure control; the Go payroll binary and some PHP extensions need verification |
| [Desktop app](desktop.md) | Single-workstation or offline installs that need no server, MySQL, or terminal | Local SQLite data; no multi-device sync; uses the PHP payroll driver |
| [Serverless and pause-when-idle](serverless.md) | Installations that must stop billing when nobody is using them: AWS Vapor + Aurora Serverless v2, GCP Cloud Run, Azure Container Apps, or pausing the existing VM stacks | Cold starts; payroll must be queued; on GCP and Azure the database only stops if you accept downtime |

## Before deploying

1. **Rotate any exposed credentials.** See the [open-source release checklist](../security/open-source-checklist.md).
2. **Set `APP_ENV=production` and `APP_DEBUG=false`.**
3. **Use MySQL 8** for production. SQLite is for tests and quick trials.
4. **Build the payroll engine.** Payroll processing needs `bin/payroll-engine` (or set `PAYROLL_ENGINE_DRIVER=php` for a slower driver that is parity-locked to the same results).
5. **Plan object storage and backups.** Uploaded documents and payslips are business records.
6. **Run the scheduler and a queue worker.** Payrolls above the inline threshold are queued.
7. **Use HTTPS.** Session cookies are marked secure in production.

## Database migrations

Schema changes ship as Laravel migrations in `database/migrations`; there is no SQL dump to import. Every first boot and in-place deploy runs `php artisan migrate --force`, which creates a new schema or upgrades an existing one in place. The deploy scripts then seed the system-wide catalogues (leave types and compensations) and refresh the statutory tables, the 2026 NWPC wage orders (private-sector installations), and the 2026 holiday calendar.

The migrations are guarded with `Schema::hasTable` and `Schema::hasColumn`, so databases created before the SQL dumps were retired upgrade cleanly. See [Operations](operations.md#migrations) for the baseline procedure and reference-data commands.

## The golden-image layers

The AWS, GCP, and Azure paths deliberately separate two concerns:

- **Packer** builds a golden image: operating system, PHP, Nginx, Node, Go, the application at a specific Git ref, and the payroll binary. The GCP and Azure images also include Caddy and MySQL 8.
- **Terraform** creates the runtime: network, compute instance, storage, database wiring, IAM, secrets, and optional DNS.

Application secrets are never baked into the image. Terraform writes an encrypted environment file to SSM Parameter Store (AWS), Secret Manager (GCP), or Key Vault (Azure), and the instance's bootstrap script fetches it on first boot.

The Docker path builds the same application once and runs it with Nginx, PHP-FPM, queue workers, a scheduler, and MySQL 8 in Compose.

Continue with [Docker Compose](docker.md), [AWS AMI deployment](aws-ami.md), [GCP deployment](gcp.md), or [Azure deployment](azure.md). For an install that runs on a single workstation without a server, see [Desktop app](desktop.md). To stop paying for capacity nobody is using, see [Serverless and pause-when-idle](serverless.md).
