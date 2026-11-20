---
slug: /deployment/desktop
description: Build OpenHR PH as a standalone desktop application with NativePHP for macOS, Windows, and Linux.
---

# Desktop app (NativePHP)

OpenHR PH can be packaged as a single desktop application with [NativePHP Desktop 2](https://nativephp.com/docs/desktop/2).
The app bundles a static PHP runtime, Laravel, and SQLite, so users install it and sign in — there is no server, MySQL
instance, queue worker, or scheduler to run.

The desktop build is **opt-in**: the web deployment keeps MySQL and the Go payroll engine. The desktop profile is
applied only when the app is running inside Electron.

## What differs from the server deployment

| Area | Web deployment | Desktop build |
| --- | --- | --- |
| Database | MySQL 8 | SQLite in the per-user app-data folder |
| Payroll engine | Go binary (`bin/payroll-engine`) | PHP driver (`App\Services\Payroll\Engine\PhpPayrollEngine`) — parity-locked to the Go engine |
| Sessions | Database | File |
| Queue | Redis/database worker | Database queue, with NativePHP's built-in worker |
| Scheduler | `schedule:run` cron | NativePHP runs `schedule:run` every minute |
| Mail | SMTP | Log driver (nothing leaves the machine) |
| Broadcasting | Reverb / Pusher | Log driver |
| Registration | Optional public demo | Disabled — the setup wizard creates the owner |
| Uploaded files | `public` disk + `storage:link` | `public` disk served by Laravel from the writable storage path |
| Updates | Deploy pipeline | GitHub Releases auto-updater |

The bundled PHP binary has no `pdo_mysql` (and no `exif`), so the desktop app can never connect to MySQL and phone-photo
EXIF rotation is skipped when generating employee thumbnails.

## Requirements

| Tool | Version |
| --- | --- |
| PHP | 8.3 or newer with `zip`, `gd`, `exif`, `intl`, `bcmath`, `pdo_sqlite` |
| Composer | 2.x |
| Node.js | 22 or newer |
| OS | macOS 12+, Windows 10+, or a current Linux distribution |

Build each platform on that platform. NativePHP cross-compiles in some combinations, but a native runner is the
supported path.

## Install and run locally

NativePHP is already a dependency. On a fresh checkout, install its runtime once:

```bash
composer install
php artisan native:install
npm install
```

`native:install` downloads the Electron project's Node dependencies and the matching PHP binary. It also
publishes `config/nativephp.php` and `app/Providers/NativeAppServiceProvider.php`; both are committed here, so the
installer leaves them in place.

Start the app in a native window:

```bash
php artisan native:run
```

For hot reload, run Vite in a second terminal (`npm run dev`), or use the combined script:

```bash
composer native:dev
```

NativePHP copies the application into a build directory, so changes to PHP/Blade need a restart of `native:run`. Vite
changes hot-reload.

### Development database

In development the app uses `database/nativephp.sqlite` and migrates it automatically on first launch. Force a clean
slate with:

```bash
php artisan native:migrate:fresh
```

The database file is ignored by Git.

## First run

The window opens the **setup wizard** at `/setup` while the database has no user accounts. It collects:

1. the installation sector (**Government** or **Private**) and payroll country (default **PH**);
2. the company name;
3. the owner's name, email address, and password.

The wizard then runs the same steps as the deployment scripts:

- `openhr:install` — sector, country, currency, locale, timezone, statutory tables, sector catalogues;
- `ReferenceDataSeeder` — system-wide leave types and compensation templates;
- `admin:create` — the company and the owner account;
- the company's employment types, deductions, and loan programmes;
- the sample workspace — `Head Office`, the `Human Resources` department, a default 8am–5pm schedule, the
  `Default Kinsenas Katapusan` semi-monthly payroll group with its pay dates, three sample employees
  (`D-0001`–`D-0003`), their attendance for the current cutoff, an open draft payroll and the company
  settings;
- NWPC wage orders (Private sector) and the holiday calendar.

When it finishes, the owner is signed in and lands on the dashboard with the same populated tables a demo
account gets on the hosted site. The sample rows are ordinary records: edit them, or delete the `D-000x`
employees once you add real ones.

The wizard is only registered for the desktop runtime: outside Electron, `/setup` returns 404. Once an owner exists,
opening `/setup` redirects to `/app/login`.

### Loading sample data later

An installation created before the sample workspace existed, or one that was emptied on purpose, can be filled from
**Getting started → Load sample data**. The action is desktop-only, is idempotent, and refuses to run when the
company already has employees outside the `D-000x` set, so it never mixes sample payroll rows into a real workspace.

## Where the data lives

NativePHP creates an app-data folder named after the slug of `APP_NAME` (`openhr-ph`; the development build uses
`openhr-ph-dev`). In production builds the database and storage live there; in development they stay in the project.

| OS | Path |
| --- | --- |
| macOS | `~/Library/Application Support/openhr-ph` |
| Windows | `%APPDATA%\openhr-ph` |
| Linux | `~/.config/openhr-ph` |

Changing `APP_NAME` creates a new folder; existing data is not moved automatically.

The folder contains:

- `database/database.sqlite` — all payroll data (WAL mode);
- `storage/app/public` — uploaded documents and employee photos;
- `storage/logs` — daily logs (NativePHP keeps three days in production builds).

**Backup** by copying the `database.sqlite` file (and its `-wal`/`-shm` companions) while the app is closed. The files
can be opened with any SQLite browser.

## Build a distributable

Bump the version for every release. NativePHP only runs migrations on users' machines when the version changes:

```dotenv
NATIVEPHP_APP_VERSION=1.1.0
```

Then build for the current platform:

```bash
php artisan native:build          # current OS and architecture
php artisan native:build mac arm64
php artisan native:build win x64
php artisan native:build linux x64
```

On macOS, set `CSC_IDENTITY_AUTO_DISCOVERY=false` to skip signing for a quick local build. Without it,
electron-builder signs with any development certificate it finds and waits on Apple's timestamp service.

Artifacts land in `nativephp/electron/dist` (ignored by Git). `config/nativephp.php` runs `npm run build` before
packaging, so the Vite manifest is always current.

The build copies the project and then removes development-only files and secrets:

- `.env` keys matched by `cleanup_env_keys` (`DB_*`, `MAIL_*`, `REDIS_*`, `PUSHER_*`, `REVERB_*`, `BUGSNAG_*`,
  `AWS_*`, `*_SECRET`, `*_API_KEY`, `*_TOKEN`, `*_PASSWORD`, and the signing credentials) are deleted from the
  bundled `.env`;
- `cleanup_exclude_files` removes tests, docs, deployment/E2E files, `payroll-engine`, `bin/*`, local SQLite files,
  logs, uploads, `public/storage`, and `node_modules`.

Keep that list up to date when adding new secrets or development artifacts.

A local build copies your `.env`, which is a development profile (`APP_DEBUG=true`), so it opens DevTools and shows
error pages. NativePHP's per-user paths only apply when `app.debug` is off, so a local build keeps its database and
storage inside the bundled app folder — fine for testing, but not for handing to users. Use the GitHub Actions
workflow (or set `APP_DEBUG=false` and `DESKTOP_DEBUG=true` in `.env`) for an installer that keeps data in the
per-user app-data folder.

## Auto-updates and GitHub Releases

The updater uses GitHub Releases (`config/nativephp.php` → `updater`). A release is published in two steps:

1. Create a draft release whose tag matches the version, for example version `1.1.0` → tag `v1.1.0`.
2. Run `php artisan native:publish mac arm64` (or `native:build … --publish`) on each platform.

NativePHP attaches the platform artifacts to the draft release. The app checks for updates on launch, downloads them in
the background, and applies them on quit. Publish the draft when every platform has uploaded.

### GitHub Actions

`.github/workflows/desktop.yml` builds all four targets:

| Runner | Platform | Architecture |
| --- | --- | --- |
| `macos-latest` | `mac` | `arm64` |
| `macos-latest` | `mac` | `x64` |
| `windows-latest` | `win` | `x64` |
| `ubuntu-latest` | `linux` | `x64` |

- **Manual run** — Actions → *Desktop app* → *Run workflow*. Builds are uploaded as workflow artifacts (14 days).
  Manual runs are debug builds unless the `debug` input is unchecked (see below).
- **Tag `v*`** — builds and publishes to a draft GitHub Release. Tag builds are not debug builds.

### Bundled environment

NativePHP copies `.env` into the build directory, but **electron-builder drops dotfiles from the package**, so the
installed app never actually receives it. Without an environment file the app has no `APP_KEY` and every request fails
with `MissingAppKeyException` behind a plain "500 Server Error" page.

The `desktop:export-env` Artisan command (run by `config/nativephp.php` → `prebuild`, and as an explicit workflow step)
exports the same settings to `bootstrap/desktop.env` — a regular file that ships — and
[`bootstrap/app.php`](/Volumes/sdcard/projects/kawayan/openhrph/openhrphmain/bootstrap/app.php) loads it when no `.env`
is present. The export applies the same cleanup as NativePHP (`cleanup_env_keys`, log channels), and it fails the build
when `APP_KEY` is missing instead of shipping a broken installer.

The workflow pins `APP_ENV=production` and `APP_DEBUG=false`: `.env.example` is a development profile, and shipping it
unchanged would point the app at MySQL and the project's own storage.

`APP_DEBUG` has to stay `false` even in a debug build. NativePHP treats `app.debug` as a development run: it then
keeps the SQLite database, uploads, sessions and logs inside the installed application folder instead of the per-user
app-data folder, which is read-only on Windows and Linux and replaced on update. The workflow writes a separate
`DESKTOP_DEBUG` flag into the bundled environment instead.

Set an optional `DESKTOP_APP_KEY` repository secret (generate one with `php artisan key:generate --show`) to use the
same key for every release. Without it each build generates a new key, and users are signed out after an update
because their session cookies no longer decrypt.

### Database on first launch

NativePHP runs `php artisan migrate` only when the bundled app version changes. A build shipped under the same version
as a previous run therefore keeps an empty database, and every page (including the setup wizard) fails on the missing
`cache` and `users` tables. `DesktopEnvironment::ensureDatabaseIsMigrated()` migrates the database when the `migrations`
table is absent, and CI builds get a unique prerelease version (`0.0.0-ci.<run number>`) so upgrades always re-run
pending migrations.

### Debug builds

The `debug` workflow input (on by default for manual runs) writes `DESKTOP_DEBUG=true` (`config/desktop.php`), and
`App\Services\Desktop\DesktopEnvironment::enableDebug()` turns Laravel's debug mode on at runtime, after NativePHP has
already resolved the per-user storage and SQLite paths. A debug build:

- shows a full exception page — message, class, file, line and stack trace — in the window;
- opens an Electron DevTools panel next to the window (NativePHP opens it whenever `app.debug` is on);
- logs to `storage/logs` in the app-data folder at debug level, where NativePHP normally forces `LOG_LEVEL=warning`.

Ignition and Debugbar are `require-dev` and pruned from the bundle (`composer install --no-dev`), so the error page is
the framework's plain exception page and no debug toolbar is injected.

Tag `v*` builds default to a non-debug build. To publish a debug build, dispatch the workflow manually with *publish*
and *debug* both checked — the stack traces will be visible to whoever installs that release.

### Code signing

Unsigned builds run only on the machine that built them (macOS shows a "damaged" warning elsewhere), and macOS
auto-updates require a signed and notarised app. Add these repository secrets to sign in CI:

| Secret | Purpose |
| --- | --- |
| `CSC_LINK`, `CSC_KEY_PASSWORD` | macOS signing certificate (base64 `.p12` and its password) |
| `NATIVEPHP_APPLE_ID`, `NATIVEPHP_APPLE_ID_PASS`, `NATIVEPHP_APPLE_TEAM_ID` | Apple notarisation |
| `AZURE_TENANT_ID`, `AZURE_CLIENT_ID`, `AZURE_CLIENT_SECRET` | Azure Trusted Signing (Windows) |
| `NATIVEPHP_AZURE_PUBLISHER_NAME`, `NATIVEPHP_AZURE_ENDPOINT`, `NATIVEPHP_AZURE_CERTIFICATE_PROFILE_NAME`, `NATIVEPHP_AZURE_CODE_SIGNING_ACCOUNT_NAME` | Azure Trusted Signing profile |

Without `CSC_LINK` the workflow sets `CSC_IDENTITY_AUTO_DISCOVERY=false` and produces unsigned artifacts for testing.

## Desktop limitations

- **Go payroll engine** — not available. The PHP engine is the parity-tested fallback; `php artisan payroll:engine`
  is a Go-binary check and is not used by the desktop UI.
- **Email** — the log mailer is used, so invitations and password-reset emails are written to the log instead of sent.
- **AI assistant** — needs a network connection and a configured provider in `config/ai.php`; without one, requests
  fail gracefully and the rest of the app keeps working.
- **EXIF** — the bundled PHP has no `exif` extension, so uploaded photos are not auto-rotated.
- **Single machine** — there is no multi-device sync. Copy the SQLite file to move an installation.

## Troubleshooting

| Symptom | Fix |
| --- | --- |
| Window opens to a 500 error | Build with the `debug` input checked to see the exception and stack trace in the window and in DevTools; logs are in `storage/logs` (dev) or the app-data `storage/logs` (packaged). Run `php artisan native:debug` when opening an issue |
| 500 with no details and `MissingAppKeyException` in `laravel.log` | The packaged app is missing its environment file. Run `php artisan desktop:export-env` and confirm `bootstrap/desktop.env` contains `APP_KEY` before building; the CI step fails when it does not |
| 500 with `no such table: cache` (or `users`) | The app-data database was never migrated. The bundled version did not change since the last run; build with a new `version` (CI uses `0.0.0-ci.<run number>`), or delete the app-data folder and reopen the app |
| The setup wizard does not appear | Run `php artisan native:migrate`; the `users` table must exist |
| Workspace tables are empty after setup | Re-run the sample workspace from **Getting started → Load sample data**, or start over with `php artisan native:migrate:fresh` |
| Want to start over | `php artisan native:migrate:fresh` in development. In a packaged app, quit and move the app-data folder aside |
| Build is stale | `php artisan native:reset` then rebuild |
| Wrong PHP binary | Set `NATIVEPHP_PHP_BINARY_PATH` to a custom binary folder; NativePHP expects `{os}/{arch}/php` |
| `Electron.app` is missing after `native:install` | Some npm versions skip Electron's install script. Run `node vendor/nativephp/desktop/resources/electron/node_modules/electron/install.js` once |
| Update never appears | Confirm the release is published (not draft), the tag matches `NATIVEPHP_APP_VERSION`, and the build is signed |

See `php artisan native:debug` output when opening an issue.
