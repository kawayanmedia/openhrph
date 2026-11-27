# OpenHR PH

An open-source Human Resource Information System and Philippine payroll platform for growing organisations. It brings employee records, attendance, leave and overtime requests, payroll processing, statutory contributions, payslips, and reporting into one workspace..

> **Status:** the project is being prepared for public release. Please read [SECURITY.md](SECURITY.md) before deploying it or sharing a fork.

> **Try it:** the hosted demo is at <https://openhrph.laravel.cloud>. Create an account,
> confirm the email we send you, and your own sample workspace opens with demo employees and payroll.

## What it does

- **People** — employee profiles, documents, certificates, training, schedules, compensation, loans, allowances, bonuses, and commissions.
- **HR platform** — organization units, locations, cost centers, positions with effective-dated assignments and reporting lines, an immutable document repository, configurable approvals, and an append-only audit trail.
- **Government HR** — versioned plantilla, qualification screening, appointments, personnel actions, and HRMPSB committees.
- **Private HR** — recruitment to hire, onboarding, employments and contracts, probation and regularization, separation with clearance and final settlement, and private leave policy rules.
- **Workforce and talent** — attendance corrections that never overwrite raw punches, timesheet approval and payroll locking, performance cycles and reviews, training, certifications, and employee self-service for performance, training, and documents.
- **Retirement, compliance and analytics** — retirement plans, cases, reviewed calculations, GSIS/SSS claim tracking, a versioned compliance source/rule register, and a tenant-scoped workforce analytics report.
- **Time and attendance** — biometric device imports, timesheets, attendance corrections, overtime, undertime, leave, official business, and schedule adjustments.
- **Payroll** — payroll groups, period previews, review, approval and locking, payslips, registers, and a payroll bank-credit file.
- **Reports and remittances** — a report centre for statutory working papers, a filing/payment register, and an employee master file with printable 201 files.
- **Company management** — company owners can add companies, switch between them, and keep each company's settings and payroll separate.
- **Universal search** — a command palette on `⌘/Ctrl + /` for navigation, quick actions, and tenant-scoped records.
- **Statutory compliance** — effective-dated tables for the installation's country. The Philippines ships with SSS, PhilHealth, Pag-IBIG, GSIS, BIR withholding tax and minimum-wage tables; other countries are configured through generic statutory components.
- **Multi-company** — one installation can serve several companies with tenant-scoped data and per-company settings.
- **Access control** — owner accounts, groups, granular permissions, and a refreshed, dark-mode-ready employee self-service portal.
- **Two admin experiences** — a first-party Livewire admin at `/app` and an optional Filament panel.

## Built with

| Layer | Technology |
| --- | --- |
| Backend | PHP 8.5+, Laravel 13 |
| Admin UI | Livewire 3, Blade, Tailwind CSS 4, Vite 8 |
| Optional admin panel | Filament 3 |
| Payroll engine | Go 1.24 (MySQL-backed, with a PHP fallback) |
| Data | MySQL 8 (SQLite for tests and the desktop build) |
| Queues and realtime | Database/Redis queues, Laravel Reverb |
| Desktop | NativePHP Desktop 2 (Electron + bundled PHP + SQLite) |
| Reporting | Maatwebsite Excel, FPDF/FPDI, PHPWord |

## Quick start

### Requirements

- PHP 8.5 or newer with the usual Laravel extensions (`bcmath`, `ctype`, `curl`, `dom`, `fileinfo`, `gd`, `intl`, `mbstring`, `openssl`, `pdo_mysql`, `tokenizer`, `xml`, `zip`)
- Composer 2
- Node.js 22 and npm
- Go 1.24 (only for the payroll engine)
- MySQL 8 (SQLite is used only by the automated tests)

### Install

```bash
git clone https://github.com/kawayanmedia/openhrph.git
cd hris

composer install
cp .env.example .env
php artisan key:generate

# Create and initialise the MySQL database
mysql -u root -p -e 'CREATE DATABASE hris CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;'
php artisan migrate

# Government or Private for the whole installation, and the payroll country
php artisan openhr:install

php artisan admin:create

npm ci
npm run build

# Optional: build the Go payroll engine
make -C payroll-engine build

php artisan serve
```

Open <http://127.0.0.1:8000/app/login> and sign in with the account created by `admin:create`.

> The database is created entirely from the migrations in `database/migrations`. They are guarded with `Schema::hasTable` and `Schema::hasColumn`, so the same command also upgrades an existing installation. `openhr:install` seeds the leave type and compensation catalogues, which are system-wide: one set of shared rows plus an optional sector tag, with per-company overrides.

For the full setup, including the payroll engine, queues, Vite dev server, and demo data, read [Local development](docs/developer-guide/local-development.md).

## Documentation

Start at the [documentation hub](docs/README.md).

The same content is published as a searchable site at
[kawayanmedia.github.io/openhrph](https://kawayanmedia.github.io/openhrph/), with a
light/dark theme and the workspace screenshots. To run it locally:

```bash
cd docs-site
npm ci
npm start
```

The site reads `docs/` directly, so there is nothing to keep in sync. See
[docs-site/README.md](docs-site/README.md) for the build and deployment details.

### I use OpenHR PH

- Start here: [Company to payslip walkthrough](docs/user-guide/end-to-end-walkthrough.md), [Getting started](docs/user-guide/getting-started.md), [Data import](docs/user-guide/data-import.md)
- People and organization: [Employees](docs/user-guide/employees.md), [Organization and positions](docs/user-guide/organization-and-positions.md), [Documents and workflow](docs/user-guide/documents-and-workflow.md)
- Time and attendance: [Attendance and requests](docs/user-guide/attendance-and-requests.md), [Timesheet management](docs/user-guide/timesheet-management.md), [Workforce corrections and approvals](docs/user-guide/workforce-corrections.md)
- Payroll and reports: [Payroll](docs/user-guide/payroll.md), [Reports and payslips](docs/user-guide/reports-and-payslips.md), [Workforce analytics](docs/user-guide/workforce-analytics.md)
- Performance and learning: [Performance](docs/user-guide/performance.md), [Learning and certifications](docs/user-guide/learning.md), [Employee self-service](docs/user-guide/employee-self-service.md)
- Retirement and compliance: [Retirement](docs/user-guide/retirement.md), [Compliance](docs/user-guide/compliance.md)
- Private sector: [HR overview](docs/user-guide/private-hr.md), [Recruitment](docs/user-guide/private-recruitment.md), [Employment](docs/user-guide/private-employment.md), [Separation](docs/user-guide/private-separation.md)
- Government sector: [HR overview](docs/user-guide/government-hr.md), [Plantilla and qualifications](docs/user-guide/government-plantilla.md), [Appointments](docs/user-guide/government-appointments.md), [Personnel actions](docs/user-guide/government-personnel-actions.md), [HRMPSB](docs/user-guide/government-hrmpsb.md), [Records](docs/user-guide/government-records.md), [Separation and retirement](docs/user-guide/government-separation-retirement.md)
- All HR guides: [HR, workforce and talent overview](docs/user-guide/hr-and-workforce.md)
- Settings and help: [Settings and access](docs/user-guide/settings-and-access.md), [Troubleshooting](docs/user-guide/troubleshooting.md)

### Release notes

- [HR platform expansion — November 2026](docs/releases/2026-11-hr-platform.md)

### I build, extend, or operate OpenHR PH

- [Architecture](docs/developer-guide/architecture.md)
- [HR platform modules](docs/developer-guide/hr-platform.md)
- [Local development](docs/developer-guide/local-development.md)
- [Admin panel](docs/developer-guide/admin-panel.md)
- [Payroll engine](docs/developer-guide/payroll-engine.md)
- [Testing](docs/developer-guide/testing.md)
- [Multi-tenancy](docs/tenancy.md)
- [Docker deployment](docs/deployment/docker.md)
- [AWS AMI deployment](docs/deployment/aws-ami.md)
- [GCP deployment](docs/deployment/gcp.md)
- [Azure deployment](docs/deployment/azure.md)
- [Operations](docs/deployment/operations.md)

## Testing

```bash
vendor/bin/pest                    # PHP unit and feature tests (SQLite)
make -C payroll-engine test        # Go payroll engine tests
npm run e2e                        # Playwright browser tests
npm run e2e:dark                   # Playwright dark-mode project
```

Browser tests run against `E2E_BASE_URL` (defaults to `APP_URL`) and use an isolated seeded company. They never touch real payroll data. See [Testing](docs/developer-guide/testing.md).

## Deployment

- **Docker** — build the application image and run the Compose stack: [Docker deployment](docs/deployment/docker.md).
- **AWS** — build a golden AMI with Packer and launch it with Terraform: [AWS AMI deployment](docs/deployment/aws-ami.md).
- **GCP** — build a golden image with Packer and launch it with Terraform: [GCP deployment](docs/deployment/gcp.md).
- **Azure** — build a managed image with Packer and launch it with Terraform: [Azure deployment](docs/deployment/azure.md).
- **Laravel Cloud / Vapor** — see [Laravel Cloud](docs/deployment/laravel-cloud.md) for the managed-hosting path.
- **Desktop app** — build a standalone macOS, Windows, or Linux app with NativePHP: [Desktop app](docs/deployment/desktop.md).
  It bundles SQLite, runs the parity-locked PHP payroll driver, and updates from GitHub Releases.

The Go payroll binary must exist on the server. The Docker image and the AWS, GCP, and Azure golden images build it automatically; other deployments must add `make -C payroll-engine build` to their release step. The desktop build does not use it.

## Security

Please do not open a public issue for a security problem. Read [SECURITY.md](SECURITY.md) for the disclosure process.

Before publishing a fork, review the [open-source release checklist](docs/security/open-source-checklist.md). It covers credential rotation, history cleanup, and PII checks.

## Contributing

Contributions are welcome. Read [CONTRIBUTING.md](CONTRIBUTING.md) for the development workflow, coding conventions, and pull-request checklist.

## License

Released under the [MIT License](LICENSE).

