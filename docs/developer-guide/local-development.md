# Local development

This guide gets a working OpenHR PH environment on your machine.

## Requirements

| Tool | Version | Notes |
| --- | --- | --- |
| PHP | 8.3 or newer | 8.4 is also supported; enable `bcmath`, `gd`, `intl`, `mbstring`, `pdo_mysql`, `zip` |
| Composer | 2.x | |
| Node.js | 22.x | Vite 8 needs a modern Node release |
| npm | 10.x or newer | |
| MySQL | 8.x | Production database; SQLite is used by tests |
| Go | 1.24.x | Only needed for the payroll engine |
| Git | Any recent version | |

Optional: Redis for cache/queues, Laravel Reverb for WebSockets, and Playwright browsers for E2E tests.

## 1. Clone and install PHP dependencies

```bash
git clone https://github.com/kawayanmedia/openhrph.git
cd hris

composer install
cp .env.example .env
php artisan key:generate
```

If `php` is not on your PATH, use the absolute path to your PHP binary for the commands below.

## 2. Create the database

Create a MySQL database:

```bash
mysql -u root -p -e 'CREATE DATABASE hris CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;'
```

Set the `DB_*` values in `.env`:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=hris
DB_USERNAME=root
DB_PASSWORD=
```

Apply the migrations:

```bash
php artisan migrate --force
```

The migrations in `database/migrations` create the full schema, including the optional Filament tables. They are guarded with `Schema::hasTable` and `Schema::hasColumn`, so rerunning them on an existing development database only applies the outstanding changes.

The automated test suite uses in-memory SQLite via `phpunit.xml`; you do not need a separate test database for the default suite.

## 3. Create your admin account

```bash
php artisan admin:create
```

The command asks for a name, email, password, and company name. It creates the company, the owner user, and the company membership. It never prints the password.

You can pass options for automation:

```bash
php artisan admin:create \
  --name="Local Admin" \
  --email=admin@example.com \
  --password='change-me-now' \
  --company-name="Local Company" \
  --no-interaction
```

Use `--force` to update an existing user with the same email.

The default `db:seed` seeds a demo tenant with an `admin@example.com` / `password` login plus the organization, payroll, government and statutory reference data. The seeders are idempotent, so running `php artisan db:seed --force` again is safe. Use `admin:create` when you need a different owner account.

## 4. Build front-end assets

```bash
npm ci
npm run build
```

For development with hot reload:

```bash
npm run dev
```

If you see “Unable to locate file in Vite manifest”, run `npm run build` or start `npm run dev`.

## 5. Build the payroll engine

```bash
make -C payroll-engine build
php artisan payroll:engine --check
```

The binary is written to `bin/payroll-engine` (git-ignored). Useful environment variables:

| Variable | Purpose |
| --- | --- |
| `PAYROLL_ENGINE_BINARY` | Override the binary path |
| `PAYROLL_ENGINE_DSN` | Override the MySQL DSN |
| `PAYROLL_ENGINE_DRIVER` | `go` (default) or `php` for the fallback |
| `PAYROLL_ENGINE_INLINE_THRESHOLD` | Employee count below which payroll runs inline (default 50) |
| `PAYROLL_ENGINE_TIMEOUT` | Process timeout in seconds (default 600) |

`db:seed` imports the effective-dated statutory tables through `payroll:statutory:import`. To refresh them manually, run `php artisan payroll:statutory:import` after migrating. Use `--fresh` only when you intend to replace all statutory rows.

## 6. Run the application

The fastest option is the Composer dev script, which starts the HTTP server, queue listener, log viewer, and Vite together:

```bash
composer run dev
```

Or run the pieces yourself:

```bash
php artisan serve
php artisan queue:work
php artisan schedule:work
npm run dev
```

Open [http://127.0.0.1:8000/app/login](http://127.0.0.1:8000/app/login). The `.env.example` uses `http://localhost:8000` for the app and `http://portal.localhost:8000` for the employee portal; modern browsers resolve `*.localhost` to loopback.

## 7. Storage and caches

```bash
php artisan storage:link
php artisan optimize:clear
```

If uploaded files do not appear, check `storage/app/public`, the `public/storage` symlink, and the `FILESYSTEM_DISK`/`MEDIA_DISK` values.

## Queues and realtime

The default queue is the database queue:

```bash
php artisan queue:work --sleep=3 --tries=3
```

The scheduler:

```bash
php artisan schedule:work
```

Reverb is optional. To enable broadcasting, configure the `REVERB_*` variables, set `BROADCAST_CONNECTION=reverb`, run `php artisan reverb:start`, and proxy WebSockets through your web server.

## Tests

```bash
vendor/bin/pest
make -C payroll-engine test
npm run e2e
npm run e2e:dark
```

See [Testing](testing.md) for the full workflow.

## Common commands

```bash
php artisan route:list --path=app
php artisan about
php artisan config:show admin
php artisan payroll:preview 24
php artisan payroll:statutory:import
php artisan testing:seed-e2e-company
php artisan testing:purge-e2e-company
```

## Troubleshooting

If something fails during setup, read [Troubleshooting](troubleshooting.md). The most common causes are the PHP extensions, the database import order, the Vite manifest, and the payroll binary path.
