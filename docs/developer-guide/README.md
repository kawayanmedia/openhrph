---
slug: /developer-guide
description: Architecture, conventions, and extension points for developers building and operating OpenHR PH.
---

# Developer guide

This guide is for developers, integrators, and system administrators who build, extend, or operate OpenHR PH. Start here, then follow the reading order that matches your task.

## Recommended reading order

1. [Architecture](architecture.md) — how the application is put together.
2. [Local development](local-development.md) — get a working environment.
3. [Admin panel](admin-panel.md) — how the Livewire admin works and how to extend it.
4. [Payroll engine](payroll-engine.md) — the Go engine and statutory tables.
5. [Testing](testing.md) — Pest, Playwright, and Go tests.
6. [Multi-tenancy](../tenancy.md) — tenant resolution and scoping rules.
7. [Troubleshooting](troubleshooting.md) — common setup and runtime failures.
8. [AWS AMI deployment](../deployment/aws-ami.md) — production infrastructure.

## Repository map

| Path | What lives there |
| --- | --- |
| `app/` | Application code: Livewire admin, models, policies, services, jobs, console commands, and Filament integrations |
| `app/Livewire/Admin/` | First-party admin panel: resources, fields, filters, actions, metrics, lenses, payroll, settings |
| `app/Livewire/Portal/` | Employee self-service portal |
| `app/Support/Tenancy/` | Tenant context, scopes, cache helpers, and exceptions |
| `app/Services/Payroll/` | PHP payroll engine fallback and the Go engine wrapper |
| `bootstrap/` | Laravel bootstrap, middleware, and health route |
| `config/` | Application configuration, including `admin.php`, `payroll.php`, and `tenancy.php` |
| `database/migrations/` | Application migrations; the only source of truth for the schema |
| `database/seeders/` | Seeders, including the plantilla seeder |
| `database/factories/` | Model factories |
| `deploy/aws/` | Packer AMI and Terraform stack |
| `docs/` | User, developer, deployment, and security documentation |
| `e2e/` | Playwright browser tests |
| `payroll-engine/` | Go payroll engine, statutory rules, and tests |
| `public/` | Web root, compiled assets, and public uploads |
| `resources/css/` | Tailwind CSS and the `ui-*` design system |
| `resources/js/` | Vite entry point |
| `resources/views/` | Blade layouts, Livewire views, and components |
| `routes/` | Web, API, admin, portal, channel, and integration routes |
| `storage/` | Logs, framework cache, sessions, and uploaded files (not all tracked) |
| `tests/` | Pest/PHPUnit unit and feature tests |

## Conventions

- PHP follows the project's Pint configuration and the rules in `AGENTS.md`.
- The admin panel uses its own `Resource`/`Field`/`Action` classes, not Filament classes.
- Tenant-owned models use `BelongsToTenant`; see [Multi-tenancy](../tenancy.md).
- UI work uses the `ui-*` classes in `resources/css/app.css`.
- Add tests for behavior changes. See [Testing](testing.md).

## Where to make a change

| Task | Start here |
| --- | --- |
| Add or change an admin CRUD screen | `app/Livewire/Admin/Resources/` |
| Add a field type | `app/Livewire/Admin/Fields/` and `resources/views/livewire/admin/fields/` |
| Add a filter | `app/Livewire/Admin/Filters/` |
| Add a row/toolbar action | `app/Livewire/Admin/Actions/` |
| Add a dashboard metric | `app/Livewire/Admin/Metrics/` |
| Change the admin menu | `config/admin.php` and `app/Livewire/Admin/Support/Navigation.php` |
| Change payroll rules | `payroll-engine/internal/` and `config/payroll.php` |
| Change tenancy behavior | `app/Support/Tenancy/` and `docs/tenancy.md` |
| Change UI tokens | `resources/css/app.css` |
| Change deployment | `deploy/aws/` and `docs/deployment/` |
