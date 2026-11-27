---
slug: /
description: "Choose where to start with OpenHR PH: day-to-day use, development, or deployment."
---

# OpenHR PH documentation

This documentation is split into two paths. Pick the one that matches what you need to do.

It is also published as a searchable site at [kawayanmedia.github.io/openhrph](https://kawayanmedia.github.io/openhrph/).
Run it locally from `docs-site/` with `npm start`.

> **Try the demo first.** The [hosted demo](https://openhrph.laravel.cloud) creates an isolated
> workspace with sample employees, timesheets, and an open payroll. Sign up, verify your email
> address, and the workspace opens.

## Choose your sector

The installation is configured for one sector. Use the guide set that matches it:

| Sector | Guide set |
| --- | --- |
| **Private** | [Private-sector HR](user-guide/private-hr.md) — recruitment, employment, separation, company retirement plans |
| **Government (PH)** | [Government-sector HR](user-guide/government-hr.md) — plantilla, appointments, personnel actions, HRMPSB, service records, leave cards, GSIS retirement |

Each sector hub links every sector-specific page. Shared modules (employees, organization, attendance,
payroll, performance, learning, retirement mechanics, compliance, analytics) apply to both.

## I use OpenHR PH

For HR officers, payroll staff, administrators, and employees who use the system day to day. No programming knowledge is needed.

| Guide | What it covers |
| --- | --- |
| [Company to payslip walkthrough](user-guide/end-to-end-walkthrough.md) | One complete first run: company profile, reference data, payroll group, employee, portal access, payroll, payslip, and corrections |
| [Getting started](user-guide/getting-started.md) | Signing in, switching companies, navigation, search, and changing your password |
| [Employees](user-guide/employees.md) | Employee records, documents, schedules, compensation, and import |
| [Organization and positions](user-guide/organization-and-positions.md) | Organization units, locations, cost centers, positions, requirements, assignments, reporting lines, and employment changes |
| [Documents and workflow](user-guide/documents-and-workflow.md) | Document versions, classification, acknowledgments, approvals, delegations, and audit trail |
| [Attendance and requests](user-guide/attendance-and-requests.md) | Leave, overtime, undertime, official business, attendance certificates, and request approvals |
| [Timesheet management](user-guide/timesheet-management.md) | Timesheet routine, device imports, night shifts, absences, locking, and reopening |
| [Workforce corrections and approvals](user-guide/workforce-corrections.md) | Attendance corrections and the timesheet submit/approve/reject/lock workflow |
| [Payroll](user-guide/payroll.md) | Payroll groups, compensation catalogue, calculations, processing, approval, and locking |
| [Reports and payslips](user-guide/reports-and-payslips.md) | Exports, registers, remittances, and employee payslip access |
| [Workforce analytics](user-guide/workforce-analytics.md) | Headcount, hires, separations, retirement forecasts, completion rates, and compliance exposure |
| [Performance](user-guide/performance.md) | Cycles, goals, reviews, ratings, finalization, acknowledgment, and development plans |
| [Learning and certifications](user-guide/learning.md) | Courses, sessions, enrollments, completion, and certifications |
| [Retirement](user-guide/retirement.md) | Plans, eligibility, cases, calculations, documents, and external claims |
| [Compliance](user-guide/compliance.md) | Sources, rules, versions, review, approval, and effective-date resolution |
| [Employee self-service](user-guide/employee-self-service.md) | Portal dashboard, requests, performance, training, documents, and attendance report |
| [HR, workforce and talent overview](user-guide/hr-and-workforce.md) | Directory of every HR, workforce, and talent guide |
| [Private-sector HR](user-guide/private-hr.md) | Private menu overview and workflow map |
| [Private recruitment to hire](user-guide/private-recruitment.md) | Requisitions, vacancies, applications, screening, interviews, assessments, offers, and hire conversion |
| [Private employment](user-guide/private-employment.md) | Employments, contracts, probation, regularization, onboarding, and private leave policy rules |
| [Private separation](user-guide/private-separation.md) | Separation cases, clearance, final settlement, and effective separation |
| [Government-sector HR](user-guide/government-hr.md) | Government menu overview and workflow map |
| [Government plantilla and qualifications](user-guide/government-plantilla.md) | Plantilla versions and items, occupancy, qualification standards and screening |
| [Government appointments](user-guide/government-appointments.md) | Appointment records, state machine, documents, CSC actions, assumptions |
| [Government personnel actions](user-guide/government-personnel-actions.md) | Promotion, transfer, detail, reassignment and separation actions |
| [Government HRMPSB](user-guide/government-hrmpsb.md) | Committees, members and deliberations |
| [Government records](user-guide/government-records.md) | Salary grades, NOSA, service records, leave cards and PDS/201 file |
| [Government separation and retirement](user-guide/government-separation-retirement.md) | Resignation, GSIS retirement, terminal leave, death in service, dropping, dismissal, clearance |
| [Data import](user-guide/data-import.md) | Bulk upload of offices, departments, payroll groups, positions, schedules, and employees from one workbook |
| [Settings and access](user-guide/settings-and-access.md) | Offices, departments, holidays, users, groups, and permissions |
| [Troubleshooting](user-guide/troubleshooting.md) | Common problems and when to contact an administrator |

Start with the [company-to-payslip walkthrough](user-guide/end-to-end-walkthrough.md) if you are setting up a company for the first time, or [Getting started](user-guide/getting-started.md) if you only need to sign in and find your way around.

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
| [HR platform expansion — November 2026](releases/2026-11-hr-platform.md) | Release notes for the five HR phases, migrations, upgrade steps, validation results, and known limitations |
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
