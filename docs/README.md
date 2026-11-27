---
slug: /
description: "Choose where to start with OpenHR PH: day-to-day use, development, or deployment."
---

import Link from '@docusaurus/Link';

# OpenHR PH documentation

This documentation is split into two paths. Pick the one that matches what you need to do.

It is also published as a searchable site at [kawayanmedia.github.io/openhrph](https://kawayanmedia.github.io/openhrph/).
Run it locally from `docs-site/` with `npm start`.

> **Try the demo first.** The [hosted demo](https://openhrph.laravel.cloud) creates an isolated
> workspace with sample employees, timesheets, and an open payroll. Sign up, verify your email
> address, and the workspace opens.

## Choose your sector

The user guide is published in two editions. Each edition contains the sector's own modules and its own copy
of the shared modules, so a reader only sees the guides that apply to the installation.

| Sector | Guide set |
| --- | --- |
| **Private** | <Link to="/docs/user-guide/private/private-hr">Private-sector user guide</Link> — recruitment, employment, separation, company retirement plans, plus every shared module |
| **Government (PH)** | <Link to="/docs/user-guide/government/government-hr">Government-sector user guide</Link> — plantilla, appointments, personnel actions, HRMPSB, service records, leave cards, GSIS retirement, plus every shared module |

Both editions start with the company-to-payslip walkthrough, signing in, and data import, then cover the
shared modules: employees, organization and positions, documents and workflow, attendance and timesheets,
payroll, reports and payslips, analytics, performance, learning, retirement, compliance, self-service, and
settings.

## I build, extend, or operate OpenHR PH

For developers, integrators, and system administrators.

### Understand the system

| Guide | What it covers |
| --- | --- |
| [Architecture](developer-guide/architecture.md) | Layers, request flow, admin panels, payroll engine, tenancy, queues, and storage |
| [HR platform modules](developer-guide/hr-platform.md) | Namespaces, data model, services, admin UI, workflow, tenancy, testing, and extension points for the HR modules |
| [Multi-tenancy](tenancy.md) | Tenant resolution, scoping rules, queues, cache, storage, and adding tenant tables |
| [Payroll engine](developer-guide/payroll-engine.md) | Build, run, statutory tables, preview, processing, and fallback driver |
| [Government and Private sectors](developer-guide/sectors.md) | Sector policy, catalogues, government commands, and the regulatory research matrix |

### Work on the code

| Guide | What it covers |
| --- | --- |
| [Local development](developer-guide/local-development.md) | Prerequisites, installation, database, assets, queues, payroll engine, and first admin |
| [Admin panel](developer-guide/admin-panel.md) | Adding Livewire resources, fields, filters, actions, validation, and navigation |
| [Data import](developer-guide/data-import.md) | Workbook import pipeline, sheet and column definitions, limits, and tests |
| [Testing](developer-guide/testing.md) | Pest/PHPUnit, Playwright, Go tests, and test data |
| [Troubleshooting](developer-guide/troubleshooting.md) | Setup and runtime failures |
| [Contributing](https://github.com/kawayanmedia/openhrph/blob/main/CONTRIBUTING.md) | Branching, conventions, checks, and pull requests |

### Deploy and operate

| Guide | What it covers |
| --- | --- |
| [Deployment overview](deployment/README.md) | Choosing Docker, a cloud golden image, or managed hosting |
| [Docker deployment](deployment/docker.md) | Compose stack, image build, TLS, backups, and operations |
| [AWS AMI deployment](deployment/aws-ami.md) | Packer AMI and Terraform stack, step by step |
| [GCP deployment](deployment/gcp.md) | Packer image and Terraform stack, step by step |
| [Azure deployment](deployment/azure.md) | Packer managed image and Terraform stack, step by step |
| [Operations](deployment/operations.md) | Logs, backups, updates, scaling, health checks, and teardown |
| [Laravel Cloud](deployment/laravel-cloud.md) | Managed hosting with Laravel Cloud or Vapor |
| [Serverless and pause-when-idle](deployment/serverless.md) | Vapor + Aurora Serverless v2, Cloud Run, Container Apps, or pausing idle VMs so an unused installation stops billing |
| [Desktop app](deployment/desktop.md) | Standalone macOS, Windows, and Linux builds with NativePHP, local SQLite, and auto-updates |

### Release and security

| Guide | What it covers |
| --- | --- |
| [Open-source release checklist](security/open-source-checklist.md) | Credential rotation, history cleanup, PII review, and publishing |
| [Security policy](https://github.com/kawayanmedia/openhrph/blob/main/SECURITY.md) | Reporting vulnerabilities and handling secrets |
| [Philippine payroll compliance audit — 2026 update](payroll-audit/2026-11-payroll-compliance-audit.md) | 2026 statutory basis, end-to-end payroll procedure, advisory checklist, and open findings F-25–F-37 |
| [Official sources used by the audit](payroll-audit/2026-11-payroll-compliance-audit.md#official-sources-used) | Statutory circulars and references behind the payroll rules |

## A note on terms

- **Company / tenant** — one organisation using the installation. Data is separated by `company_id`.
- **Workspace** — the admin interface at `/app`.
- **Employee portal** — the self-service area for employees.
- **Payroll group** — the rules and schedule used to compute a payroll (for example, semi-monthly).
- **Payroll run** — one processing period for a payroll group.
- **Payslip** — the employee-level result of a payroll run.

If you are not sure where to start, open one of the two user guide editions above and follow "Start here", or open an issue on GitHub.
