# Testing

OpenHR PH uses Pest 4 on top of PHPUnit 12 for PHP tests, Playwright for browser tests, and Go's testing package for the payroll engine.

## PHP tests

```bash
vendor/bin/pest
vendor/bin/pest tests/Feature/Admin
vendor/bin/pest tests/Feature/Admin/AdminEngineTest.php
vendor/bin/pest --filter='creates a record through the admin form'
```

`php artisan test --compact` is equivalent when you prefer Artisan.

### Test environment

`phpunit.xml` sets:

- `APP_ENV=testing`
- `DB_CONNECTION=sqlite`
- `DB_DATABASE=:memory:`
- `CACHE_STORE=array`
- `QUEUE_CONNECTION=sync`
- `SESSION_DRIVER=array`
- `TENANCY_STRICT=true`

`Tests\TestCase` forgets the tenant context before each test. Its `actingAs()` override sets the tenant context from the user's `company_id`, so tenant-scoped queries work as they do in production.

Many feature tests create only the tables they need with `Schema::create()` in `beforeEach`. Follow that pattern when a test targets a small slice of the schema. For a test that needs the full schema, use the imported MySQL schema or a dedicated migration-based setup; do not point tests at a real database.

### Factories and fixtures

Factories live in `database/factories`. Some are legacy and do not match the current model classes; check the factory before using it. Existing admin tests often use `(new Model)->forceCreate([...])`, which is explicit and avoids legacy factory assumptions.

Use synthetic data only. Never copy employee names, emails, government numbers, bank accounts, or payslips into a test or fixture.

### What to test

- Validation failures and success paths for form changes.
- Authorization: a user from another company must not read or write the record.
- Tenant scoping: a query without a tenant must fail closed.
- Payroll rules: add Go tests for the statutory arithmetic, and PHP tests for what the engine reads and how it is invoked (see below).
- UI behavior that is easy to regress: required labels, action modals, filters, CSV exports.
- `admin:create`: new company, duplicate email, and `--force` update.

### Payroll tests on the PHP side

The Go engine computes pay, so PHP tests cover the contract around it:

| File | What it proves |
| --- | --- |
| `tests/Feature/Admin/PayrollStatutoryImportTest.php` | `payroll:statutory:import` seeds exactly what SSS Circular 2024-006, RA 11223, RA 9679 and BIR RR 29-2025 print — 61 contiguous SSS bands, the EC step at MSC ₱15,000, the WISP split above ₱20,000, the BIR band bases to the centavo, the nine de minimis ceilings and the 30% overtime meal percentage, and the source records. Also proves a re-run is idempotent and that importing a newer effectivity closes the predecessor schedule. |
| `tests/Unit/Payroll/GoPayrollEngineTest.php` | The process boundary: the binary is required, a preview passes `--dry-run` and a process passes `--force`, the DSN and payroll id are forwarded, engine stderr becomes the exception message, invalid JSON is rejected, and the version check degrades to `null`. |
| `tests/Unit/Payroll/EngineResultTest.php` | The engine payload is mapped without losing a value, with safe defaults for a partial response. |

`tests/Feature/Admin/PayrollComplianceTest.php` covers the journal entry on approval, its reversal on reopen, and the register and remittance exports.

Two things the PHP suite does not cover: the Go loader (`internal/db/load.go`) against seeded fixtures, and the database-level guarantees (concurrency, rollback, one-run-per-payroll). Treat those as open gaps rather than covered behaviour.

Expected values in payroll tests come from the circulars and the Labor Code, not from the code under test, and `docs/payroll-audit/independent_recalc.py` reproduces them independently:

```bash
python3 docs/payroll-audit/independent_recalc.py
```

## Browser tests

Playwright lives in `e2e/`. The global setup runs:

```bash
php artisan testing:seed-e2e-company
```

It creates three isolated companies and writes `storage/e2e-fixtures.json`:

- the pre-seeded company with a payroll group, employees, attendance, and a draft payroll used by the login, settings, office, and payroll specs;
- `E2E Onboarding Co`, a bare tenant with only an owner account, used by `e2e/onboarding-payroll.spec.ts` to exercise the full setup journey;
- `E2E Attendance Co`, a semi-monthly tenant with a day, night and split shift, three portal employees, two open cut-offs for November and one open payroll per cut-off, used by `e2e/attendance-payroll.spec.ts`.

The onboarding spec creates the office, employment type, department, payroll group, employee, timesheets, recurring allowances, fixed and percentage deductions, a company loan, the payroll run, approval, and the payslip through the browser. The tests never touch real payroll data.

The attendance spec signs each employee into the portal and punches time in and out for every weekday of November, then files an attendance correction and two overtime requests, has HR approve them, and runs both cut-offs through preview, processing, review, approval and payslips. It asserts the statutory premiums from the rate catalogue: overtime at 1.25×, night differential at 1.10× and no absence line for a fully punched period.

### Shifting the clock for multi-day journeys

The portal records the punch moment as "now", so the attendance spec shifts the application clock per request. A non-local environment rejects the header with `403`:

```text
X-E2E-Now: 2026-11-04T08:20:00+08:00
```

`App\Http\Middleware\SetE2eClock` applies the value with `Carbon::setTestNow()` and restores the real clock in a `finally` block, so no state leaks between requests or Octane workers. The middleware runs inside the session middleware, which keeps session cookies on their real expiry. Use `setClock()` and `clearClock()` from `e2e/support/clock.ts` rather than setting the header by hand.

```bash
npm run e2e
npm run e2e:ui
npm run e2e:dark
```

The base URL comes from `E2E_BASE_URL`, then `APP_URL`, then `https://openhrph.test`. The dark project runs only `*.dark.spec.ts`; the light project ignores those files.

Clean up the isolated data:

```bash
php artisan testing:purge-e2e-company
```

Playwright starts the browser tests against a running application. Start your local server or point `E2E_BASE_URL` at a test environment with a disposable database.

## Go payroll engine tests

```bash
make -C payroll-engine test
```

The Go tests cover statutory brackets, tax, contributions, and rule edge cases. Add a test whenever you change a rate, threshold, or calculation path.

## Add a regression test

1. Reproduce the bug in a focused test.
2. Assert the current failure.
3. Make the smallest fix.
4. Re-run the focused test.
5. Run the wider suite if the change touches shared behavior.

## Before a pull request

```bash
vendor/bin/pint --dirty --format agent
vendor/bin/pest
make -C payroll-engine test
npm run build
npm run e2e
```

For deployment changes, also run:

```bash
terraform fmt -check
terraform validate
packer validate deploy/aws/packer
```
