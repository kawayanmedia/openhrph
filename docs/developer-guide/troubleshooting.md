# Developer troubleshooting

Use this page when the application or its tooling fails during setup, development, or deployment.

## First diagnostics

```bash
php -v
php -m
php artisan about
php artisan route:list --path=app
php artisan config:show database
php artisan config:show admin
tail -n 200 storage/logs/laravel.log
```

If you use a non-default PHP binary, put it on `PATH` or call it explicitly.

## Composer or package installation fails

- Confirm PHP meets `composer.json` (`^8.3`).
- Run `composer install --no-interaction -vvv` for a detailed error.
- Do not commit `auth.json`.
- If the vendor directory is stale, remove `vendor/` and `composer.lock` only when you intentionally want to resolve fresh versions; normally keep the lock file.

## PHP extension missing

Install the extension for your PHP version, then restart PHP-FPM or your local server. The application commonly needs:

`bcmath`, `ctype`, `curl`, `dom`, `fileinfo`, `gd`, `intl`, `mbstring`, `openssl`, `pdo_mysql`, `tokenizer`, `xml`, `zip`.

Check with:

```bash
php -m | sort
```

## “No application encryption key has been specified”

```bash
php artisan key:generate
```

For an existing environment, restore the original `APP_KEY` from your secret manager. Rotating it can invalidate encrypted data and sessions.

## Database connection fails

```bash
php artisan config:show database
php artisan tinker --execute 'DB::connection()->getPdo();'
```

Check `DB_CONNECTION`, host, port, database, username, and password. For MySQL, confirm the server is running and the database exists.

## Migration fails with “Table already exists” or “Duplicate column”

The database was created before the SQL dumps were retired, and one of its objects is missing from the `migrations` history. The migrations are guarded with `Schema::hasTable` and `Schema::hasColumn`, so this only happens when an object was created outside the migrations.

1. Back up the database.
2. Confirm the failing migration's changes are already present.
3. Record it as applied:

```bash
php artisan tinker --execute 'DB::table("migrations")->insert(["migration" => "2026_10_01_000100_create_organization_tables", "batch" => ((int) DB::table("migrations")->max("batch")) + 1]);'
```

4. Rerun `php artisan migrate --force`.

Never mark a migration as applied unless its tables, columns, and indexes exist. Replace the example migration name with the one that failed.

## Sessions, cache, or queues fail

The migrations should provide `sessions`, `cache`, `cache_locks`, and `jobs`.

```bash
php artisan migrate --force
php artisan config:clear
php artisan optimize:clear
```

If you use Redis, install the PHP Redis extension or set `REDIS_CLIENT=predis` and install `predis/predis`.

## Vite manifest error

“Unable to locate file in Vite manifest” means the compiled assets are missing.

```bash
npm ci
npm run build
```

For development, run `npm run dev` and keep it running. If you changed `vite.config.mjs`, restart Vite.

## Dark-mode styles are missing

- Confirm `resources/css/app.css` is included in the Vite inputs.
- Rebuild assets.
- The theme uses the `dark` class on `<html>`, set by the header toggle and the inline script in the layouts.
- Check that your Blade view uses the `dark:` variants or the `ui-*` classes.

## Tenant errors

`TenantNotResolvedException` or empty results usually mean no company is active.

- Sign in through a guard the request resolves (`web` or `portal`).
- Confirm the user has a `company_id` or a `company_user` membership.
- For console commands, wrap work in `TenantContext::run($companyId, fn () => ...)` or use `withoutTenant()` for intentional platform queries.
- Do not read `request()->user()->company_id` directly; use `TenantContext`.

See [Multi-tenancy](../tenancy.md).

## Queue jobs are not processing

```bash
php artisan queue:work --sleep=3 --tries=3
php artisan queue:failed
```

- Confirm `QUEUE_CONNECTION=database`.
- Confirm the `jobs` table exists.
- Check `storage/logs/laravel.log`.
- In production, confirm the worker service is running.
- After a deploy, restart the worker so it loads the new code.

## Payroll engine check fails

```bash
php artisan payroll:engine --check
ls -l bin/payroll-engine
make -C payroll-engine build
```

- Confirm the binary is executable.
- Confirm the database is reachable.
- Confirm `PAYROLL_ENGINE_BINARY` points to the correct path.
- Set `PAYROLL_ENGINE_DRIVER=php` only as a temporary fallback. It is parity-locked to the Go engine; run `php artisan payroll:engine --parity` to confirm the driver reproduces the committed corpus.

## Reverb or WebSockets fail

- Confirm `REVERB_APP_ID`, `REVERB_APP_KEY`, and `REVERB_APP_SECRET`.
- Confirm `BROADCAST_CONNECTION=reverb`.
- Run `php artisan reverb:start`.
- Proxy the WebSocket port through Nginx or your load balancer.
- Restart the queue worker after changing broadcast configuration.

## Playwright cannot reach the app

- Start the app or set `E2E_BASE_URL` to a reachable environment.
- Confirm the URL responds: `curl -I "$E2E_BASE_URL/app/login"`.
- Run `npx playwright install` once per machine.
- The global setup seeds an isolated company; if seeding fails, run `php artisan testing:seed-e2e-company` manually and read the error.
- If tests fail with stale data, run `php artisan testing:purge-e2e-company` and retry.

## Storage or uploads fail

```bash
php artisan storage:link
ls -la storage/app/public public/storage
```

- Confirm the web server user can write to `storage` and `bootstrap/cache`.
- Check `FILESYSTEM_DISK` and `MEDIA_DISK`.
- If using S3, confirm the bucket, region, credentials, and IAM permissions.
- In the AWS image, the instance role supplies S3 access; do not put long-lived keys in `.env`.

## Behind an ALB, redirects or secure cookies are wrong

Set:

```dotenv
TRUSTED_PROXIES=*
SESSION_SECURE_COOKIE=true
```

The application reads `TRUSTED_PROXIES` in `bootstrap/app.php`. Only use `*` when the app is not directly reachable from the internet.

## Still stuck

Collect:

- the exact command and output
- PHP, MySQL, Node, and Go versions
- the relevant `storage/logs/laravel.log` lines
- whether the problem happens locally, in CI, or only in production

Remove secrets and personal data before sharing logs.
