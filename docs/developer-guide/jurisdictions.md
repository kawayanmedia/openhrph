# Jurisdictions (multi-country)

A jurisdiction is a country plugin. It owns that country's law and process:
statutory contributions, withholding tax, payroll earning rules, holidays,
leave entitlements, identifiers, documents and reports. The installation uses
**one country**; every company in it shares that jurisdiction.

The three axes are deliberately separate:

| Axis | Example | Owner |
| --- | --- | --- |
| Jurisdiction | Philippines (`PH`) | `app/Jurisdictions/Philippines` |
| Sector | Government / Private | `App\Sectors` + the jurisdiction's `sectorProfile()` |
| Tenant | A company | `company_id` values and overrides |

Never add `if ($country === 'PH')` to payroll, menus, settings or reports.
Resolve the active country through `JurisdictionManager` or
`Installation::jurisdiction()`.

## Layout

```text
app/Jurisdictions/
├── Contracts/                 the stable plugin contracts
├── Support/                   registry, manager, policy value objects
├── Generic/                   config/table-driven fallback
└── Philippines/
    ├── PhilippinesJurisdiction.php
    ├── PhilippinesServiceProvider.php
    ├── Statutory/             SSS, PhilHealth, Pag-IBIG, GSIS, BIR calculators
    │   └── Schemes/           SSS/GSIS social-insurance strategy
    ├── Payroll/               non-statutory earning rules
    ├── Tax/                   BIR withholding and benefit rules
    ├── Holidays/              calendar provider + data/calendar.php
    ├── Leave/                 statutory leave provider
    ├── Catalogues/            employment, compensation, deductions
    ├── Identifiers/           TIN, SSS, GSIS, PhilHealth, HDMF
    ├── Documents/             HR document checklists
    ├── Reports/               remittances, BIR forms, payslip PDF
    ├── Settings/              country settings fields
    ├── Admin/                 PH statutory agency resources and menu
    ├── Government/            public-sector module
    │   ├── Models/            PDS, NOSA, plantilla, salary grade, service record
    │   ├── Admin/             government screens and menu
    │   ├── Console/Commands/  government-only commands
    │   ├── Support/           readiness, employee fields
    │   ├── Reports/           GSIS premium and COA register
    │   └── Database/Seeders/  salary-grade and plantilla reference data
    ├── Private/               private-sector feature pack
    ├── Console/Commands/      PH-only commands shared by both sectors
    ├── Config/                PH sector overlay and statements
    ├── Importers/             statutory and holiday importers
    └── routes.php             PH-only routes
```

## Contracts

`App\Jurisdictions\Contracts\Jurisdiction` composes the smaller contracts:

- `PayrollPolicy` — overtime, night differential, day-type premiums, 13th-month
  exclusion, minimum-wage exemption, separation pay, special runs.
- `StatutoryDriver` — contribution and tax entry point (`ph`, `generic`, or a
  dedicated country driver).
- `TaxPolicy` — withholding and benefit-exclusion rules.
- `HolidayProvider` — calendar rows and holiday-type mapping.
- `LeavePolicyProvider` — statutory leave types and rules per sector.
- `IdentifierProvider` — employee and company identifiers.
- `DocumentProvider` — HR document checklist.
- `ReportProvider` — remittance formats, commands, payslip view, accounts.
- `SettingsProvider` — country settings fields.
- `JurisdictionCatalogue` — employment, compensation and deduction catalogues.
- `StatutoryImporter` — effective-dated statutory data import.

## Add a country

```bash
php artisan openhr:make-jurisdiction MY
```

The command creates the module, a payroll policy, a holiday provider and a
calendar stub. Then:

1. Implement `MyJurisdiction` and override only the providers that differ.
2. Put rates in effective-dated tables. A table-driven country can use the
   generic component tables:

   ```bash
   php artisan payroll:statutory:import --country=MY --file=my-components.json
   php artisan payroll:statutory:verify 2026-11-30 --country=MY
   ```

3. Add the holiday calendar under `Holidays/data/calendar.php`, then:

   ```bash
   php artisan payroll:holidays:import --year=2026
   ```

4. Add the country profile to `config/payroll.php` (currency, locale, timezone,
   driver, suggested components) if it is not already there.
5. Run the contract check:

   ```bash
   php artisan openhr:jurisdiction:check MY
   php artisan openhr:jurisdictions
   ```

Modules under `app/Jurisdictions` are discovered automatically. A Composer
package can register a jurisdiction from its own service provider through
`JurisdictionRegistry::register()`.

## Payroll policy and reproducibility

`PayrollPolicy` is written to `payrolls.rules_snapshot` when a payroll is
created, together with `payrolls.country_code` and `payrolls.policy_version`.
Both engines read the snapshot:

- the PHP fallback engine (`App\Services\Payroll\Engine\Legacy`);
- the Go engine (`payroll-engine/internal/db/load.go`).

Changing a rate or the installation country later does not change a historical
payroll. Legacy payrolls without a snapshot use the PH baseline.

The Go engine consumes the same policy object. A country that needs a formula
the generic component tables cannot express adds a `statutory.Driver`
implementation in `payroll-engine/internal/statutory` and registers it. That
requires rebuilding the binary; table-driven countries need no Go change.

## Sectors

`SectorManager` now asks the active jurisdiction for its sector profile
(`Jurisdiction::sectorProfile()`). The country keeps its Government/Private
differences in its own `Config/sector.php`; `config/sectors.php` only carries
the installation default.

## Verification

```bash
php artisan test --compact
make -C payroll-engine test
php artisan payroll:engine --parity
```

The parity corpus pins PHP and Go to the same payslip. Regenerate it only after
an intentional rule change:

```bash
PARITY_WRITE=1 go test ./internal/payroll -run TestParityCorpus -count=1
```
