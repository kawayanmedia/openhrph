# Payroll engine

Payroll runs are computed by a Go binary that reads MySQL directly and writes the payroll details in one transaction. PHP keeps a fallback engine for debugging and emergencies.

## Why a separate engine?

The payroll calculation is the most sensitive part of the system. The Go engine:

- loads a payroll, its employees, attendance, adjustments, and statutory tables in one place;
- computes every payslip deterministically;
- writes the results in a transaction;
- returns a JSON summary to PHP;
- keeps statutory rates in effective-dated tables so historical runs stay reproducible.

## Layout

```text
payroll-engine/
  cmd/payroll-engine/      CLI entry point and JSON output
  internal/db/             MySQL loading, revisions, audit events, YTD writes
  internal/model/          Input and output types
  internal/money/          Integer-centavo arithmetic
  internal/payroll/        Regular, 13th-month, final-pay and annual-adjustment runs
  internal/rules/          Day types, premiums, overtime, night differential
  internal/statutory/      PH calculators plus the generic component calculator
  Makefile                 build/test/fmt/vet
```

## Build and test

```bash
make -C payroll-engine build
make -C payroll-engine test
php artisan payroll:engine --check
```

The binary is written to `bin/payroll-engine` (git-ignored). `payroll:engine --check` reports the configured driver, binary path, version, and database connectivity, and exits non-zero if the engine is not ready.

Useful variables:

| Variable | Default | Purpose |
| --- | --- | --- |
| `PAYROLL_ENGINE_DRIVER` | `go` | `php` selects the fallback engine |
| `PAYROLL_ENGINE_BINARY` | `base_path('bin/payroll-engine')` | Binary path |
| `PAYROLL_ENGINE_DSN` | resolved from the default MySQL connection | Explicit MySQL DSN |
| `PAYROLL_ENGINE_TIMEOUT` | `600` | Process timeout in seconds |
| `PAYROLL_ENGINE_INLINE_THRESHOLD` | `50` | Payrolls up to this employee count run inline; larger ones are queued |

The Go CLI accepts:

```text
--payroll-id=<id>   required
--dsn=<dsn>         optional; otherwise DB_* environment variables
--dry-run           compute and report without writing
--reopen            reopen an approved or closed payroll
--reason=<text>     required with --reopen; recorded in the audit trail
--actor=<user id>   user id recorded on the audit event
--force             deprecated alias for --reopen; still requires --reason
--version           print the engine version
--timeout=<duration> maximum run duration (default 10m)
```

## PHP integration

`App\Services\Payroll\Engine\EngineManager` chooses the engine from `config('payroll.engine.driver')`.

- `GoPayrollEngine` runs the binary with Symfony Process.
- `PhpPayrollEngine` is the fallback driver. It is parity-locked to the Go
  engine: the earning rules live in `App\Services\Payroll\Computation`, the
  statutory calculators in `App\Services\Government`, and both are pinned to the
  Go output by a shared corpus, so it returns the same payslips.
- `EngineResult` normalizes the JSON response for the Livewire payroll page.

The Go wrapper passes the DSN resolved from the Laravel database config. It only exposes `PATH`, `HOME`, and `TZ` to the child process, so secrets are not placed in the environment.

## Statutory services

Each agency is a separate, framework-free namespace under `App\Services\Government`. A service
takes its rates and brackets in the constructor and returns a centavo-exact result object, so a
historical run stays reproducible after a rate change and the code can be reused outside this
application.

| Namespace | Service | Schedule |
| --- | --- | --- |
| `Sss` | `SssContributionService` | `SssSchedule::preset2025()`, `SssBracket` rows |
| `PhilHealth` | `PhilHealthContributionService` | `PhilHealthSchedule` |
| `PagIbig` | `PagIbigContributionService` | `PagIbigSchedule` |
| `Bir` | `WithholdingTaxService` | `WithholdingTaxTable::train($frequency)`, `TaxBracket` rows |

```php
$sss = (new SssContributionService(SssSchedule::preset2025()))->compute(2_500_000);

$sss->employeeShare(); // ₱1,250.00, amounts are centavos
```

`ContributionSchedule` decides when a monthly amount is due (`every_pay`, `first_pay`, `last_pay`),
and `PayFrequency` supplies the periods per month and year. `App\Support\Money` holds the shared
half-away-from-zero centavo arithmetic. The payroll engine loads the effective-dated rows into
these value objects; the calculators never touch the database.

## Countries and statutory drivers

The installer records the installation country in `installation_settings`. The country profile in
`config/payroll.php` selects a statutory driver:

- `ph` keeps the built-in Philippine calculators and the agency tables. Output is parity-locked
  and unchanged.
- `generic` reads `statutory_components` and `statutory_component_bands`, so a new jurisdiction
  can be configured without code changes.

The PHP driver and the Go binary both resolve the country the same way and compute the same
components, so switching `PAYROLL_ENGINE_DRIVER` never changes a payslip.

A generic component declares how it is calculated:

| Field | Values |
| --- | --- |
| `category` | `contribution`, `tax`, `levy`, `fund` |
| `applies_to` | `employee`, `employer`, `both` |
| `calculation` | `percentage`, `bracket`, `fixed` |
| `base` | `monthly_basic`, `monthly_gross`, `period_basic`, `period_gross`, `taxable` |
| `schedule` | `every_pay`, `first_pay`, `last_pay` |
| `sector` | `any`, `government`, `private` |

- A percentage is clamped to `min_base`/`max_base`, multiplied by the rate and capped by
  `max_employee`/`max_employer`.
- A bracket uses `base_centavos + rate × (base − from_centavos) + employee/employer_amount`
  within the matching band, which covers both flat-band contributions and progressive taxes.
- Monthly bases are divided across the cut-offs; period bases are taken as they are.
- Non-tax components with `reduces_taxable_income` run before the `tax` component.
- Components are applied in `sort_order`.

Company overrides are supported by inserting a component with the same `code` and a `company_id`.
Rows are effective-dated, and a payroll run only reads rows whose effectivity covers its date.

Special runs (13th month, final pay and annual adjustment) use the same country driver. A generic
country applies its configured tax component to the taxable amount and does not apply the
Philippine ₱90,000 benefit exclusion.

Each run also writes itemised `payroll_detail_deductions` rows. PH payrolls keep their dedicated
`payroll_details` columns and are mapped to the same ledger, so the payslip and remittance reports
read one shape for every country.

## Statutory tables

Statutory data lives in versioned, effective-dated tables. Every row carries `country_code`:

- `statutory_sss_brackets`
- `statutory_philhealth`
- `statutory_pagibig`
- `statutory_gsis`
- `statutory_tax_brackets`
- `statutory_min_wage`
- `statutory_de_minimis`
- `statutory_sources`
- `statutory_components`
- `statutory_component_bands`
- `payroll_detail_deductions`

Amounts are stored in centavos to avoid floating-point drift. Rows are effective-dated; a payroll
run uses the rows whose effectivity covers the payroll date.

Import or refresh the schedules:

```bash
php artisan payroll:statutory:import --country=PH
php artisan payroll:statutory:import --country=PH --fresh
```

For a generic country, provide a JSON definition:

```json
{
  "effective_from": "2026-01-01",
  "source_url": "https://example.gov/statutory-rates",
  "components": [
    {
      "code": "social",
      "name": "Social insurance",
      "category": "contribution",
      "applies_to": "both",
      "calculation": "percentage",
      "base": "monthly_basic",
      "employee_rate": 0.05,
      "employer_rate": 0.05,
      "reduces_taxable_income": true,
      "sort_order": 10,
      "bands": []
    },
    {
      "code": "income_tax",
      "name": "Income tax",
      "category": "tax",
      "applies_to": "employee",
      "calculation": "bracket",
      "base": "taxable",
      "sort_order": 900,
      "bands": [
        {"from_centavos": 0, "to_centavos": 999999, "employee_rate": 0},
        {"from_centavos": 1000000, "employee_rate": 0.10}
      ]
    }
  ]
}
```

```bash
php artisan payroll:statutory:import --country=XX --file=path/to/components.json
php artisan payroll:statutory:verify 2026-11-30 --country=XX
```

Administrators can maintain the same rows under **Settings → Country & statutory** and the
**Statutory components** / **Statutory bracket rows** resources. A rate change is effective-dated,
so keep the old rows to reproduce historical payrolls.

`--fresh` deletes all statutory rows before importing. Use it only when you intend to replace the whole schedule. Every imported row records its source in `statutory_sources`.

When a newer schedule is imported, the previous open-ended row is closed by setting `effective_to` to the day before the new `effective_from`. At load time the engine fails with `statutory table does not cover the payroll date` instead of silently applying a stale table.

The defaults in `config/payroll.php` cover SSS, PhilHealth, Pag-IBIG, and BIR withholding tax. Re-verify them against the latest circulars when a new issuance lands.

Additional company settings (`settings` table) that the engine reads:

| Key | Values | Purpose |
| --- | --- | --- |
| `sss_base` | `basic` (default), `gross`, `period_gross` | What the SSS contribution is assessed on. |
| `phil_health_base` | same | PhilHealth premium base. |
| `hdmf_base` | same | Pag-IBIG fund salary base. |
| `pay_unworked_regular_holidays` | `1` / `0` | Pay 100 % for unworked regular holidays (Article 94). |
| `days_year_no_weeked` | `261` | Monday–Friday divisor. |
| `auto_absent_no_timesheet` | `1` / `0` | Treat missing attendance as absent. |

The employee benefit switches (`rest_day_pay`, `regular_holiday_pay`, `special_holiday_pay`, `premium_holiday_pay`, `regular_holiday_special_holiday_pay`, `overtime_pay`, `night_differential`, `deminimis`) gate each earning. A suppressed premium is reported as a payslip warning so payroll can review it.

## Payroll flow

1. **Create/open payroll** — the payroll record holds the group, period, cut-off, and status.
2. **Preview** — `--dry-run`; computes and returns totals without writing.
3. **Process** — writes `payroll_details`; status moves to `running`, then `processed`.
4. **Review** — inspect the register for pay, deductions, statutory contributions, and warnings.
5. **Approve & lock** — status becomes `approved`; the payroll journal entry is posted.
6. **Payslips** — generated from the processed/approved details.

Re-processing snapshots the current rows into `payroll_detail_revisions` and records a `payroll_audit_events` entry before replacing `payroll_details`. An approved or closed run can only be re-processed through `--reopen --reason`, which the UI also requires. The engine stores the engine version, an input hash and the per-employee warnings on the run.

## Run types

`payrolls.type` selects the computation:

| Type | Computation |
| --- | --- |
| `regular` | Cut-off payroll: attendance, premiums, earnings, statutory contributions and withholding. |
| `thirteenth_month` | 1/12 of the employee's YTD basic salary, with the ₱90,000 exclusion aggregated with other benefits. |
| `final_pay` | Final period pay, pro-rated 13th month, leave conversion and separation pay for separated employees. |
| `annual_adjustment` | Annualised withholding tax less the tax already withheld during the year. |

Create them with:

```bash
php artisan payroll:special {group} thirteenth_month --date=2025-12-24 --process
php artisan payroll:special {group} final_pay --separation-reason=retrenchment --years-of-service=5 --process
php artisan payroll:annualise {group} --year=2025 --process
```

`employee_payroll_ytd` holds the per-employee annual ledger (basic earned, gross, taxable income, tax withheld, contributions, 13th month and other benefits) and is updated in the same transaction as the payroll.

## Reporting and accounting

```bash
php artisan payroll:register {payroll} --path=register.csv
php artisan payroll:remittance {payroll} --path=remittance.csv
php artisan payroll:bir-2316 {group} --year=2025 --path=bir-2316.csv
```

Approving a payroll posts `journal_entries` / `journal_entry_lines` with the account mapping in `config('payroll.accounting.accounts')`. Reopening reverses it. The register reconciles to the journal because both are derived from the same `payroll_details` columns:

- `basic_salary` is the period basic;
- `regular_pay` is attendance pay including premiums;
- `total_employee` is the employee statutory contribution total;
- `loans` and `incentive` hold non-statutory deductions and compensations.

Each compensation line is also broken out by its BIR tax class into
`representation_allowance`, `transportation_allowance`, `cola`,
`fixed_housing_allowance`, `other_taxable_regular`, `other_taxable_supplementary`,
`profit_sharing`, `fees` and `hazard_pay`. Those columns are a breakdown of the
`incentive` total (and of the allowance/bonus/commission totals), so they are
never added to it. `App\Models\Compensation::TAX_CLASS_COLUMNS` is the single
source of truth for the mapping; a tax class with no dedicated column stays in
the `compensations` JSON only.

The catalogue itself is system-wide. `compensations` rows with `company_id = NULL` are the shared
catalogue maintained by `App\Support\Payroll\CompensationCatalogue::sync()` (run it after an upgrade
with `php artisan payroll:compensations:sync`). A company that needs a different amount or ceiling
gets a row with its own `company_id` and the same `code`; the engines resolve the assignment by
`compensation_id`, so the override is picked up automatically while other companies keep the shared
values.

Small payrolls run inline. Payrolls above the inline threshold are queued, so keep a queue worker running.

## Fallback engine

Set `PAYROLL_ENGINE_DRIVER=php` only when the Go binary is unavailable. The PHP
driver is slower and should not be the production default. The AWS image builds
the Go binary automatically.

The PHP driver is not a second implementation of the payroll rules. It runs the
same computation as the Go engine through `App\Services\Payroll\Computation`
and writes `payroll_details` with the same column mapping, revisions, audit event
and year-to-date updates.

### Parity corpus

The two runtimes are held together by a committed corpus:
`payroll-engine/internal/payroll/testdata/parity/*.json`. Each file holds a
scenario input and the payslip the Go engine computed for it.

- `go test ./internal/payroll -run TestParityCorpus` fails when the Go engine no
  longer reproduces a fixture.
- `tests/Unit/Payroll/EngineParityTest.php` fails when the PHP driver diverges.
- `php artisan payroll:engine --parity` reports the same check from the CLI.

After an intentional rule change, rebuild the corpus from the Go engine and
re-run both suites:

```bash
PARITY_WRITE=1 go test ./internal/payroll -run TestParityCorpus -count=1
go test ./internal/payroll -count=1
php artisan test --compact tests/Unit/Payroll
```

The parity contract covers the money arithmetic (integer centavos, half away
from zero), the statutory tables, the day-type premiums, and every run type
(regular, 13th month, final pay, annual adjustment).

`process:payroll` is a deprecated standalone calculator from before the engine
split. It is not used by the application and does not share the parity contract.
Process payrolls with the admin payroll page, `payroll:preview`, or
`payroll:special` / `payroll:annualise` instead.

## Troubleshooting

```bash
ls -l bin/payroll-engine
php artisan payroll:engine --check
php artisan payroll:preview 24
```

If the binary is missing, rebuild it:

```bash
make -C payroll-engine build
```

If the database connection fails, check `DB_*` and `PAYROLL_ENGINE_DSN`. If a payroll is approved, reopen it before processing again.

If a queued payroll stays in `running`, check the queue worker and the Laravel log. The engine is transactional; a failed run does not partially write the register.
