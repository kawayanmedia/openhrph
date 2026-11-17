---
slug: /
description: "Choose where to start with OpenHR PH: day-to-day use, development, or deployment."
---

# OpenHR PH documentation

This documentation is split into two paths. Pick the one that matches what you need to do.

It is also published as a searchable site at [kawayanmedia.github.io/openhrph](https://kawayanmedia.github.io/openhrph/).
Run it locally from `docs-site/` with `npm start`.

## I use OpenHR PH

For HR officers, payroll staff, administrators, and employees who use the system day to day. No programming knowledge is needed.

| Guide | What it covers |
| --- | --- |
| [Company to payslip walkthrough](user-guide/end-to-end-walkthrough.md) | One complete first run: company profile, reference data, payroll group, employee, portal access, payroll, payslip, and corrections |
| [Getting started](user-guide/getting-started.md) | Signing in, switching companies, navigation, and changing your password |
| [Employees](user-guide/employees.md) | Employee records, documents, schedules, compensation, and import |
| [Attendance and requests](user-guide/attendance-and-requests.md) | Timesheets, overtime, leave, undertime, official business, and corrections |
| [Payroll](user-guide/payroll.md) | Payroll groups, previews, processing, approval, locking, and payslips |
| [Reports and payslips](user-guide/reports-and-payslips.md) | Exports, registers, remittances, and employee payslip access |
| [Settings and access](user-guide/settings-and-access.md) | Offices, departments, holidays, users, groups, and permissions |
| [Troubleshooting](user-guide/troubleshooting.md) | Common problems and when to contact an administrator |

Start with the [company-to-payslip walkthrough](user-guide/end-to-end-walkthrough.md) if you are setting up a company for the first time, or [Getting started](user-guide/getting-started.md) if you only need to sign in and find your way around.

## I build, extend, or operate OpenHR PH

For developers, integrators, and system administrators.

### Understand the system

| Guide | What it covers |
| --- | --- |
| [Architecture](developer-guide/architecture.md) | Layers, request flow, admin panels, payroll engine, tenancy, queues, and storage |
| [Multi-tenancy](tenancy.md) | Tenant resolution, scoping rules, queues, cache, storage, and adding tenant tables |
| [Payroll engine](developer-guide/payroll-engine.md) | Build, run, statutory tables, preview, processing, and fallback driver |

### Work on the code

| Guide | What it covers |
| --- | --- |
| [Local development](developer-guide/local-development.md) | Prerequisites, installation, database, assets, queues, payroll engine, and first admin |
| [Admin panel](developer-guide/admin-panel.md) | Adding Livewire resources, fields, filters, actions, validation, and navigation |
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

If you are not sure where to start, read [Getting started](user-guide/getting-started.md) or open an issue on GitHub.
