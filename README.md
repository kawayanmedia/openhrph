# OpenHR PH

An open-source Human Resource Information System and Philippine payroll platform for growing organisations. It brings employee records, attendance, leave and overtime requests, payroll processing, statutory contributions, payslips, and reporting into one workspace..

> **Status:** the project is being prepared for public release. Please read [SECURITY.md](SECURITY.md) before deploying it or sharing a fork.

## What it does

- **People** — employee profiles, documents, certificates, training, schedules, compensation, loans, allowances, bonuses, and commissions.
- **Time and attendance** — biometric device imports, timesheets, attendance corrections, overtime, undertime, leave, official business, and schedule adjustments.
- **Payroll** — payroll groups, period previews, review, approval and locking, payslips, registers, and remittance reports.
- **Philippine statutory compliance** — effective-dated SSS, PhilHealth, Pag-IBIG, BIR withholding tax, and minimum-wage tables.
- **Multi-company** — one installation can serve several companies with tenant-scoped data and per-company settings.
- **Access control** — owner accounts, groups, granular permissions, and a separate employee self-service portal.
- **Two admin experiences** — a first-party Livewire admin at `/app` and an optional Filament panel.

## Built with

| Layer | Technology |
| --- | --- |
| Backend | PHP 8.3+, Laravel 13 |
| Admin UI | Livewire 3, Blade, Tailwind CSS 4, Vite 8 |
| Optional admin panel | Filament 3 |
| Payroll engine | Go 1.24 (MySQL-backed, with a PHP fallback) |
| Data | MySQL 8 (SQLite for tests) |
| Queues and realtime | Database/Redis queues, Laravel Reverb |
| Reporting | Maatwebsite Excel, FPDF/FPDI, PHPWord |

## Quick start

### Requirements

- PHP 8.3 or newer with the usual Laravel extensions (`bcmath`, `ctype`, `curl`, `dom`, `fileinfo`, `gd`, `intl`, `mbstring`, `openssl`, `pdo_mysql`, `tokenizer`, `xml`, `zip`)
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

php artisan admin:create

npm ci
npm run build

# Optional: build the Go payroll engine
make -C payroll-engine build

php artisan serve
```

Open <http://127.0.0.1:8000/app/login> and sign in with the account created by `admin:create`.

> The database is created entirely from the migrations in `database/migrations`. They are guarded with `Schema::hasTable` and `Schema::hasColumn`, so the same command also upgrades an existing installation.

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

- [Company to payslip walkthrough](docs/user-guide/end-to-end-walkthrough.md)
- [Getting started](docs/user-guide/getting-started.md)
- [Employees](docs/user-guide/employees.md)
- [Attendance and requests](docs/user-guide/attendance-and-requests.md)
- [Payroll](docs/user-guide/payroll.md)
- [Reports and payslips](docs/user-guide/reports-and-payslips.md)
- [Settings and access](docs/user-guide/settings-and-access.md)
- [Troubleshooting](docs/user-guide/troubleshooting.md)

### I build, extend, or operate OpenHR PH

- [Architecture](docs/developer-guide/architecture.md)
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

The Go payroll binary must exist on the server. The Docker image and the AWS, GCP, and Azure golden images build it automatically; other deployments must add `make -C payroll-engine build` to their release step.

## Security

Please do not open a public issue for a security problem. Read [SECURITY.md](SECURITY.md) for the disclosure process.

Before publishing a fork, review the [open-source release checklist](docs/security/open-source-checklist.md). It covers credential rotation, history cleanup, and PII checks.

## Contributing

Contributions are welcome. Read [CONTRIBUTING.md](CONTRIBUTING.md) for the development workflow, coding conventions, and pull-request checklist.

## License

Released under the [MIT License](LICENSE).

