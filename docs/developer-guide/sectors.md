# Government and Private sectors

OpenHR PH is installed for **one sector**: a Government installation or a Private
installation. The installer records the sector, pins every company to it, and the
application selects the matching policy for payroll, catalogues, screens and
reports.

`Non-Profit` remains a valid value on `companies.type` for legacy data. It follows
the **Private** policy because the Labor Code applies; it is not a third sector.

```text
openhr:install --sector=Government --country=PH
openhr:install --sector=Private --country=PH
openhr:install --sector=Government --force   # change an existing installation
```

## Research: what differs

The table records what the software models. Rates and amounts are **not** kept
in this repository's sector config; they belong in the effective-dated
`statutory_*` tables so a historical payroll stays reproducible.

| Concern | Government | Private | Where it lives |
| --- | --- | --- | --- |
| Social insurance | GSIS, RA 8291. 9% employee / 12% employer are the **initial** rates on AMC up to the AMC limit, with a 2%/12% tier above it and Board authority to adjust. Current GSIS ceiling practice needs confirmation. | SSS, RA 11199: 15% total (10% employer / 5% employee), MSC ₱5,000–₱35,000. The statute's table ends at 2025; a 2026 circular must be checked. | `config/sectors.php` selects the agency; `statutory_gsis` / `statutory_sss_brackets` hold the rates |
| Health / housing | PhilHealth, RA 11223 Sec. 10: 5% for 2024–2025, floor ₱10,000, ceiling ₱100,000. Pag-IBIG rate and cap still need confirmation. | Same statutes. | `statutory_philhealth`, `statutory_pagibig` |
| Withholding tax | RA 10963: ₱90,000 cap on 13th month and other benefits, covering RA 6686 and PD 851 benefits. | Same. | `statutory_tax_brackets`; the cap is `CompensationCatalogue::OTHER_BENEFITS_EXCLUSION` |
| Remittance deadlines | GSIS: first 10 days; late interest at least 2% simple/month (RA 8291 Secs. 6(b), 7). | SSS: employer deducts and remits employee + employer share (RA 11199 Secs. 18–19). Deadline schedule still needs confirmation. | Remittance working papers |
| Mandatory identifiers | GSIS BP, TIN, PhilHealth, Pag-IBIG; plantilla/item number | SSS, TIN, PhilHealth, Pag-IBIG | `statutory.required_identifiers`, employee form |
| Base pay | Salary grade + step (DBM schedule). The 3-year step increment is pending confirmation (likely EO 201 s.2016 / EO 64 s.2024). | Contract/minimum wage | `salary_grades`, `employees.salary_grade/step` |
| Government allowances | Uniform/clothing up to ₱7,000/yr (GAA, e.g. RA 11975 Sec. 58; DBM BC 2024-1). Medical allowance exists (BC 2024-6; amount pending). PERA/RATA/hazard/subsistence/laundry need confirmation. | Not applicable | `config/sectors.php` compensation elements; `compensations` catalogue |
| Bonuses | Mid-year bonus: one month basic as of May 15 (EO 201 s.2016, DBM BC 2017-2). Year-end bonus + cash gift (DBM BC 2016-4; ₱5,000 confirmed only in the BC footnote). SRI (AO 40 s.2025 / BC 2025-3). CNA incentive (BC 2024-4, 2025-2, 2026-1; amount pending). PBB and loyalty cash gift pending. | 13th month, PD 851 Sec. 1, due by December 24. | `bonus.items`; `CompensationCatalogue` |
| Leave | Maternity 105 days (RA 11210); paternity 7 days (RA 8187, both sectors); solo parent up to 7 days (RA 11861, both sectors). CSC VL/SL/SPL/forced leave and monetization pending confirmation; VAWC 10 days pending confirmation. | SIL 5 days after 1 year (Labor Code Art. 95), with the small-establishment and paid-leave exceptions. Same maternity/paternity/solo parent laws. | `leave.types`; `leave_types` table |
| Working time | Labor Code Title I excludes government employees (Art. 82). Government OT/ND/holiday rules pending confirmation. | OT +25% (Art. 87); night differential +10% for 10 p.m.–6 a.m. (Art. 86); holiday pay pending. | `rules.night_differential`; payroll engine |
| Retirement / exit | GSIS pension formula (RA 8291 Secs. 9–13): 37.5% of revalued AMC + 2.5%/year beyond 15, cap 90%; separation/unemployment benefits defined. Terminal leave rules pending. | RA 7641: at least ½ month salary per year of service; a 6-month fraction counts as one year. | `rules.exit`; terminal leave command |
| HR records | PDS (CS Form 212), SALN (RA 6713), service record, plantilla, NOSA, appointment, CSC eligibility — exact form rules pending. | 201 file, contract, BIR 2316, clearances — exact rules pending. | `documents`; `payroll:documents` |
| Remittances / audit | GSIS, PhilHealth, Pag-IBIG, BIR, COA register — COA layout pending. | SSS R-3, PhilHealth, Pag-IBIG, BIR, DOLE reports — exact forms pending. | `reports`; `payroll:remittance` |
| Employment types | Permanent, Temporary, Casual, Contractual, Job Order, Contract of Service | Regular, Probationary, Project, Seasonal, Fixed-term, Part-time | `employment_types` catalogue |

### Verification status

Items marked "pending" above were not confirmed against the primary text during
the initial research. Do **not** turn them into active seeds until an owner reads
the issuance and records it in `statutory_sources`. The following sources have
already been checked:

- [RA 8291](https://lawphil.net/statutes/repacts/ra1997/ra_8291_1997.html)
- [RA 11199](https://lawphil.net/statutes/repacts/ra2019/ra_11199_2019.html)
- [RA 11223](https://lawphil.net/statutes/repacts/ra2019/ra_11223_2019.html)
- [PD 442 (Labor Code)](https://lawphil.net/statutes/presdecs/pd1974/pd_442_1974.html)
- [PD 851](https://lawphil.net/statutes/presdecs/pd1975/pd_851_1975.html)
- [RA 7641](https://lawphil.net/statutes/repacts/ra1992/ra_7641_1992.html)
- [RA 6686](https://lawphil.net/statutes/repacts/ra1988/ra_6686_1988.html)
- [RA 10963](https://lawphil.net/statutes/repacts/ra2017/ra_10963_2017.html)
- [RA 11210](https://lawphil.net/statutes/repacts/ra2019/ra_11210_2019.html)
- [RA 8187](https://lawphil.net/statutes/repacts/ra1996/ra_8187_1996.html)
- [RA 11861](https://lawphil.net/statutes/repacts/ra2022/ra_11861_2022.html)
- [DBM BC 2017-2 (mid-year bonus)](https://www.dbm.gov.ph/wp-content/uploads/Issuances/2017/Budget%20Circular/BUDGET%20CIRCULAR%20NO.%202017-2.pdf)
- [DBM BC 2016-4 (year-end bonus and cash gift)](https://www.dbm.gov.ph/wp-content/uploads/Issuances/2016/Budget%20Circular/BUDGET%20CIRCULAR%20NO.%202016%20-%204.pdf)
- [DBM BC 2025-3 (SRI)](https://www.dbm.gov.ph/wp-content/uploads/Issuances/2025/Budget-Circular/BUDGET-CIRCULAR-NO-2025-3.pdf)
- [DBM BC 2024-1 (uniform/clothing)](https://www.dbm.gov.ph/wp-content/uploads/Issuances/2024/Budget-Circular/BUDGET-CIRCULAR-NO-2024-1-DATED-APRIL-4-2024.pdf)
- [DBM budget circular index](https://www.dbm.gov.ph/index.php/budget-circulars)

Still open: CSC Omnibus Rules on Leave (MC 41 s.1998), CSC/DBM overtime and night
differential rules, Pag-IBIG Circular 460, PERA via DBM NBC 599, step increment
(EO 201/EO 64), loyalty award, PBB guidelines, RA 9262 Sec. 43, RA 1616/RA 660
retirement, BIR forms, CS Form 212, SALN, and COA payroll audit rules.

## Architecture

```text
app/Enums/Sector.php                       Government | Private
app/Sectors/SectorManager.php              current / for / forCompany
app/Sectors/Contracts/SectorPolicy.php     the contract consumers depend on
app/Sectors/ConfigurableSector.php         config-backed implementation
app/Sectors/GovernmentSector.php           public-sector policy
app/Sectors/PrivateSector.php              private-sector policy
app/Sectors/Support/SectorProfile.php      typed config DTO
app/Sectors/Support/FeatureSet.php         named capabilities
app/Sectors/Catalogues/…                   employment, leave, document, sector seeders
config/sectors.php                         every declarative difference
```

`SectorManager` resolves in this order:

1. `installation_settings.sector` (written by `openhr:install`);
2. the legacy `IS_GOVERNMENT` environment flag;
3. `config('sectors.default')` (Private).

Payroll resolves **per company** so legacy rows keep working. The installer pins
every company to the installation sector, so the two normally agree.

```php
use App\Sectors\SectorManager;

$policy = app(SectorManager::class)->current();

$policy->sector();             // Sector::Government
$policy->supports('plantilla');// true
$policy->leaveTypes();         // catalogue rows
$policy->remittances();        // ['remittances' => [...], ...]
```

Sector-tagged resources and menu items are hidden from the other sector:

```php
// app/Livewire/Admin/Resources/PlantillaItem.php
public static ?string $sector = 'Government';
```

```php
// config/admin.php
['type' => 'resource', 'key' => 'plantilla-item', 'sector' => 'Government'],
```

### Payroll and the Go engine

`App\Services\Payroll\Computation\Config` carries both `sector` and the legacy
`is_government` flag. `is_government` stays in the JSON contract so older parity
fixtures keep working; the Go engine derives `Sector` from the company type.

The PHP calculators live under `App\Services\Statutory\Philippines`. That
namespace is **Philippine statutory law**, not public-sector policy. Do not put
Government-only HR rules there.

### Catalogues

`App\Sectors\Catalogues\SectorCatalogue` is the single entry point used by the
installer and the demo seeders:

- employment types;
- leave types (CSC set vs Labor Code set);
- deduction templates;
- loan programmes (GSIS vs SSS/Pag-IBIG);
- the global compensation catalogue, filtered by the `sector` column.

`DocumentCatalogue` is read-only: it returns the checklist for
`payroll:documents`. Uploaded-document storage is not part of this layer.

## Commands

| Command | Sector | Purpose |
| --- | --- | --- |
| `payroll:step-increments` | Government | Report or apply the next salary step |
| `payroll:loyalty-awards` | Government | Report or create the loyalty cash gift assignment |
| `payroll:terminal-leave` | Government | Compute terminal leave for separated employees |
| `payroll:documents` | both | Print the sector document/identifier checklist |
| `payroll:remittance --format=sector` | both | Write the sector's remittance set |
| `payroll:remittance --format=coa` | Government | COA-style payroll register working paper |

All write-capable commands are dry runs unless `--apply` is passed. They record
to `hr_audit_events`.

## Adding a sector

1. Add a case to `App\Enums\Sector`.
2. Add a profile to `config/sectors.php` with the same keys as the others.
3. Add a class extending `ConfigurableSector` and return it from
   `SectorManager::make()`.
4. Extend `openhr:install` if the new sector needs a new installer choice.
5. Add the sector to the resource/menu tags and to the tests in
   `tests/Unit/SectorTest.php` and `tests/Feature/Admin/SectorCatalogueTest.php`.

The profile-completeness test fails when the new profile omits a key, which keeps
the three profiles structurally aligned.
