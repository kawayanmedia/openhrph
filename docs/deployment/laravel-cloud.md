# Laravel Cloud and Vapor

OpenHR PH is a standard Laravel 13 application, so it can run on managed Laravel hosting. The main differences from the AWS AMI stack are the Go payroll binary, long-running queue jobs, and WebSockets.

> This project is not affiliated with Laravel Cloud. Confirm current runtime support, pricing, and limits before committing to a provider.

## Before you start

- Rotate any credentials that were previously committed. See the [open-source release checklist](../security/open-source-checklist.md).
- Decide how payroll will run. The Go engine is a local binary; managed PHP runtimes usually need a build step that compiles and ships `bin/payroll-engine`.
- Decide how media is stored. Use S3 or another shared disk, not ephemeral local storage.
- Confirm the runtime provides the PHP extensions in `composer.json` and `README.md` (`bcmath`, `gd`, `intl`, `pdo_mysql`, `zip`, etc.).

## Laravel Cloud

1. Create an application and connect the repository.
2. Add a MySQL database and a cache/queue service.
3. Set the environment variables from `.env.example`, using production values.
4. Add a build command that installs dependencies and builds assets:

   ```bash
   composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader
   npm ci && npm run build
   make -C payroll-engine build
   ```

5. Add a deploy command:

   ```bash
   php artisan migrate --force && php artisan config:cache && php artisan view:cache
   ```

6. Add a queue worker and the scheduler:

   ```bash
   php artisan queue:work --sleep=3 --tries=3 --timeout=600
   php artisan schedule:work
   ```

7. Deploy and check `/up`.
8. Configure the installation sector and country:

   ```bash
   php artisan openhr:install --sector=Private --country=PH --no-interaction
   ```

   Use `--sector=Government` for public-sector installations.

9. Create the first owner:

   ```bash
   php artisan admin:create
   ```

10. Configure a custom domain and HTTPS.
11. Seed the system-wide catalogues and import the statutory tables, wage orders, and the 2026 holiday calendar:

    ```bash
    php artisan db:seed --class='Database\Seeders\ReferenceDataSeeder' --force
    php artisan payroll:statutory:import --country=PH
    php artisan payroll:wage-orders:import
    php artisan payroll:holidays:import
    ```

    The imports are idempotent and can be added to the deploy command. Skip `payroll:wage-orders:import` for a Government installation: government compensation follows the DBM salary schedule. `payroll:holidays:import` is company-scoped, so run it after the first company exists.

## Hosted demo

The public demo runs the same application with self-service sign-up enabled:

| Variable | Value |
| --- | --- |
| `DEMO_MODE` | `true` |
| `DEMO_URL` | `https://openhrph.laravel.cloud` |
| `AUTH_VERIFICATION_EXPIRE` | Minutes a verification link stays valid (default `60`) |
| `DEMO_REGISTRATIONS_PER_HOUR` | Accounts one IP address may create per hour (default `5`) |

Visitors open `/register`, receive a verification email, and only reach `/app` after selecting the
link. Each sign-up creates its own company with sample employees, timesheets, and an open payroll,
so demo accounts never share data.

Before publishing a demo environment:

- Configure a working mail provider (SES, Postmark, Resend, or SMTP). The verification email is the only way in.
- Keep the queue worker running; the application already relies on queued mail and jobs elsewhere.
- Set `TRUSTED_PROXIES=*` when Cloudflare or a load balancer fronts the app, so registration
  throttling sees the real client IP.
- Leave `DEMO_MODE=false` on any installation that holds real payroll data. With demo mode off,
  `/register` and the verification routes return 404, and `admin:create` remains the only way in.

Demo companies are not removed automatically. Plan a retention window and delete old demo tenants
(for example with a scheduled database task) before the demo grows unattended.

## Vapor

The repository includes a starter `vapor.yml`, but it targets an older PHP runtime. Before using Vapor:

- Update the runtime to a PHP version supported by Laravel 13 and the provider.
- Add a build step for `make -C payroll-engine build`.
- Account for Lambda time limits. Payroll runs above the inline threshold are queued; make sure the queue worker timeout fits the platform.
- Provide a VPC connection if the database is private.
- Store media in S3.
- Use a persistent cache/session store.
- Configure WebSockets separately if you enable Reverb.

Vapor's Lambda model does not keep a long-running local filesystem or a single scheduler process, so verify every operational assumption before production.

## Managed hosting checklist

- [ ] PHP 8.3+ and required extensions
- [ ] MySQL 8
- [ ] HTTPS and secure cookies
- [ ] `APP_ENV=production`, `APP_DEBUG=false`
- [ ] `APP_KEY` set from a secret manager
- [ ] Queue worker and scheduler running
- [ ] `bin/payroll-engine` built and executable, or `PAYROLL_ENGINE_DRIVER=php` explicitly accepted
- [ ] S3 or shared storage for uploads
- [ ] Statutory tables imported
- [ ] First admin created with `admin:create`
- [ ] Database backups and restore tested
- [ ] Logs and error reporting configured without personal data
- [ ] Domain, DNS, and certificate verified
- [ ] `/up` monitored
