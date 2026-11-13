# Contributing to OpenHR PH

Thank you for helping improve the project. This guide covers the local workflow, coding conventions, and the checks we expect before a pull request is merged.

## Before you start

- Read the [documentation hub](docs/README.md) and the [developer guide](docs/developer-guide/README.md).
- Search existing issues and pull requests before opening a new one.
- For a large change, open an issue first so the approach can be discussed.
- Do not include production data, employee information, credentials, or customer names in issues, commits, tests, or screenshots.

## Local setup

```bash
git clone https://github.com/kawayanmedia/openhrph.git
cd hris

composer install
cp .env.example .env
php artisan key:generate
```

For a fresh MySQL database, create the database and run the migrations:

```bash
mysql -u root -p -e 'CREATE DATABASE hris CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;'
php artisan migrate
```

The migrations create the full schema. They are guarded, so rerunning them on an existing development database only applies the outstanding changes.

The automated test suite uses in-memory SQLite; run the full application against MySQL.

Create your first admin account:

```bash
php artisan admin:create
```

Build the front end and the payroll engine:

```bash
npm ci
npm run build
make -C payroll-engine build
```

Run the app:

```bash
composer run dev
# or
php artisan serve
```

See [Local development](docs/developer-guide/local-development.md) for queues, Reverb, Playwright, and troubleshooting.

## Branching and commits

- Branch from `main` and use a descriptive name: `fix/payroll-lock`, `feat/employee-import`, `docs/aws-ami`.
- Keep each pull request focused on one problem.
- Write commit messages in the imperative mood, for example `Fix currency input padding`.
- Do not commit generated assets unless the project already tracks them for that area.
- Do not commit `.env`, `auth.json`, database dumps, logs, or test artifacts.

## Coding conventions

### PHP

- Follow the existing Laravel and Livewire structure.
- Use explicit return types and parameter types.
- Use constructor property promotion where it improves clarity.
- Use curly braces for all control structures.
- Prefer PHPDoc array shapes over inline comments for complex data.
- Use named routes and the `route()` helper for links.
- Keep tenant-scoped queries inside the existing tenancy conventions.

### Front end

- Use the existing `ui-*` design-system classes in `resources/css/app.css`.
- Support light and dark mode.
- Keep controls keyboard-accessible and give every input a visible label.
- Preserve existing Livewire bindings and route names when restyling.

### Tests

- Add a regression test for every bug fix.
- Use the existing Pest/PHPUnit patterns.
- Use factories and the isolated E2E company for browser tests.
- Never point tests at a production database.

## Checks before opening a pull request

Run the smallest relevant set, then widen if needed:

```bash
vendor/bin/pint --dirty --format agent
vendor/bin/pest
make -C payroll-engine test
npm run build
npm run e2e
```

For deployment changes:

```bash
terraform fmt -check
terraform validate
packer validate deploy/aws/packer
shellcheck deploy/aws/**/*.sh
```

## Pull request checklist

- [ ] The change is focused and linked to an issue when one exists.
- [ ] Tests cover the behaviour, including the failure case.
- [ ] `vendor/bin/pint --dirty --format agent` passes.
- [ ] Relevant tests pass.
- [ ] `npm run build` succeeds for front-end changes.
- [ ] Light and dark mode were checked for UI changes.
- [ ] No secrets, personal data, or generated artifacts are included.
- [ ] Documentation was updated when behaviour or setup changed.
- [ ] The pull request describes the problem, the approach, and how it was verified.

## Reporting bugs

Use the bug report template and include:

- What you expected to happen
- What actually happened
- Steps to reproduce
- Version/commit, PHP and database versions
- Relevant logs with secrets and personal data removed

## Security issues

Do not open a public issue. Follow [SECURITY.md](SECURITY.md).
