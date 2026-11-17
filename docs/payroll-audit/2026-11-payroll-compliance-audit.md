# Philippine Payroll Compliance Audit — OpenHR PH Payroll Engine

**Audit date:** 4 November 2026
**2026 full-cycle update:** 10 November 2026
**2026 remediation implementation:** 10 November 2026
**Application:** OpenHR PH (Laravel 13.39, PHP 8.4, Laravel Octane) with a Go payroll engine (`payroll-engine`, module `github.com/kawayanmedia/openhrph-payroll-engine`)
**Production calculation path:** `PayrollRun` (Livewire) → `App\Jobs\ProcessPayrollJob` → `App\Services\Payroll\Engine\GoPayrollEngine` → `bin/payroll-engine`
**Prepared by:** AI assistant using VS Code / Copilot SDK. This is not a CPA report; see [Professional confirmation required](#10-professional-confirmation-required).

---

## 1. Executive summary

> **Reading this section.** The findings below describe the engine as first audited on 4 November 2026. Most were fixed in the same change and are marked **Closed** in Appendix C; the 2026 full-cycle findings are in Section 14. Where this summary conflicts with Appendix C or Section 14, the later sections prevail.

The payroll engine's statutory arithmetic is **structurally sound and, after this audit's corrections, agrees with an independent recalculation to the centavo** for the scenarios tested. Integer-centavo money handling, effective-dated statutory tables, a per-run advisory lock and a single-transaction write are good foundations.

It is **not compliant as shipped**. Three defect families were severe enough to misstate pay for ordinary employees:

1. **No premium was ever applied.** The engine keyed premium day types on values (`rest_day`, `regular_holiday`, …) that never occur in `timesheets.day_type`; the database stores `rd`, `rh`, `sh`, `rd-rh`, `2rh`, `rd-2rh`, `rh-sh`, `rd-rh-sh`. Every rest day, special day and holiday was therefore paid at **100%** instead of 130%–390%.
2. **Absences were deducted twice** — an absent day earned nothing through attendance and was then subtracted again from the gross.
3. **The 2025 SSS schedule was offset by ₱250 per band and Employees' Compensation was flat ₱10**, under-crediting salary credits and understating the employer EC share for everyone above ₱14,500 MSC.

All three are **fixed in this change** and covered by law-based regression tests (section 7).

Five material gaps remain open and need a product decision rather than a one-line patch:

- The engine **ignores every configured allowance, compensation, bonus, commission, deduction and loan** (`employees_allowances`, `employees_compensations`, `employees_bonuses`, `employees_commissions`, `employees_deductions`, `employees_loans`). Gross pay is attendance + nothing else; net pay contains statutory deductions only. The live database holds 1 allowance, 4 compensations, 3 deductions, 3 loans, 5 bonuses and 4 commissions, so this is not theoretical.
- **Monthly-paid employees are paid per attended hour**, so a semi-monthly payslip moves with the number of working days (10 vs 11 vs 12) instead of being a fixed half-month rate, and **unworked regular holidays are unpaid** (Article 94 requires 100% of the daily wage for covered employees).
- **Night-shift overtime is underpaid** (12.5% of the hourly rate instead of 137.5%) because the timesheet editor stores OT hours in `actual_nt_ot` and leaves `actual_ot` at zero.
- **Minimum wage earners are taxed**; the `employees.is_minimum_wage` flag is never read, so statutory minimum wage, holiday/OT/NSD pay and 13th-month pay of MWEs are not excluded from taxable income.
- **13th-month pay, final pay, separation pay and year-end annualisation are not implemented** in the production engine. Only an unused Go helper (`rules.ThirteenthMonth`) and a `Bonus` model constant exist.

There are also no journal entries, no payroll register export, no remittance reconciliation, and no immutable snapshot of approved runs: re-processing hard-deletes `payroll_details` (bypassing `SoftDeletes`) and, with `--force`, moves an approved payroll back to `processed`.

**Second pass (4 November 2026).** The Go suite now covers the scenarios the first pass left untested, including the flows that Appendix C reports as implemented (section 7). Two defects surfaced and remain open, each pinned by a pending test with its independently derived expected value: **F-25**, where a daily-paid minimum wage earner is exempted on one day of minimum wage instead of the period's, leaving exempt pay inside taxable income; and **F-26**, where final pay pro-rates 13th-month pay on the notional period basic and overpays daily-paid leavers. Proration for a mid-period hire is still an undefined policy (section 10, item 7).

**Bottom line:** the calculator is now arithmetically correct for the rules it implements, but the payroll *product* is incomplete for Philippine compliance. Do not treat this system as a compliant payroll of record until findings F-02, F-04, F-08, F-09, F-10, F-12 and F-19 are closed.

**2026 full-cycle update (10 November 2026).** The engine still passes its arithmetic tests, and a 2026 sample recomputation agrees with the stored payroll to the centavo against the 2025 SSS schedule and the 2023-onwards TRAIN withholding table (section 15.2). 2026 *readiness* is nonetheless not established:

- no 2026 PhilHealth, Pag-IBIG/HDMF or SSS rate issuance was retrieved and loaded; the open-ended 2024/2025 rows silently cover 2026 payroll dates (F-27);
- the minimum-wage baseline is a 2023 regional table and the loader uses the highest rate for the company rather than the employee's region and industry — NCR non-agriculture is ₱610.00 in the database against ₱755.00 under Wage Order NCR-28 effective 26 September 2026 (F-28);
- minimum-wage-earner flags are not validated: two employees flagged as MWEs earn ₱40,000 and ₱100,000 a month, and the higher earner receives a ₱6,633.75 semi-monthly exemption (F-29);
- all four processed 2026 payrolls preview with **no attendance recorded; paid on basic salary** (F-30);
- 16 of 17 active employees have a blank TIN or the literal value `1`, and 12 of 17 are missing SSS, PhilHealth and Pag-IBIG numbers, so BIR 2316 and statutory remittances cannot be filed accurately (F-32);
- only 2023 holidays exist in the holiday table, with no 2024–2026 calendar loaded (F-33);
- remittance and BIR 2316 outputs are working summary CSVs, not agency-prescribed forms (F-31);
- the de minimis catalogue uses flat monthly ceilings that do not all match RR 11-2018 — clothing is ₱250/month against ₱6,000/year, medical cash is ₱300/month against ₱250/month or ₱1,500/semester, and the meal/medical-assistance bases differ (F-37).

The full end-to-end procedure a CPA reviewer should follow is in section 12, the advisory sign-off checklist is in section 13, and the new findings and documentation-only remediation are in section 14. **Do not process a 2026 payroll for reliance until F-27, F-28, F-29, F-30, F-32, F-33 and F-37 are closed or formally accepted with a compensating control.**

**Implementation update (10 November 2026).** The code fixes for F-25 to F-37 are implemented and verified; see Appendix F. The 2026 rate values remain **provisional**, and the production data corrections (16 invalid TINs, 12 missing agency numbers, 2 invalid MWE flags, office/employee region/industry) are still required before the payroll is relied upon.

---

## 2. Scope, method and evidence

| Item | Detail |
| --- | --- |
| Code reviewed | `payroll-engine/**` (all Go packages), `app/Services/Payroll/**`, `app/Services/Attendance/Attendance.php`, `app/Models/Timesheet.php`, `app/Models/Rate.php`, `app/Console/Commands/PayrollStatutoryImport.php`, `app/Jobs/ProcessPayrollJob.php`, `app/Livewire/Admin/Payroll/PayrollRun.php`, `app/Gateways/PayslipGateway.php`, `config/payroll.php` |
| Schema reviewed | `timesheets`, `employees`, `payrolls`, `payroll_groups`, `payroll_details`, `payroll_details_pays`, `rates_new`, `statutory_*` |
| Runtime evidence | Read-only queries against the configured development database (`kawayan_hris`) |
| Independent recalculation | Python `Decimal` implementation of the statutory tables and DOLE multipliers, written from the official sources — never from engine output (`independent_recalc.py`, section 6) |
| Tests | `go test ./... -count=1`, `php artisan test --compact` |
| Data privacy | No employee names or government numbers reproduced; identifiers are internal row ids only |

### Official sources used

| Source | Issuance / effectivity | Used for |
| --- | --- | --- |
| SSS Circular No. 2024-006, *Schedule of SSS Contributions Effective January 2025* (dated 19 December 2024; published in a newspaper of general circulation, effective January 2025) — https://www.sss.gov.ph/sss-contribution-table/ | Effective Jan 2025 | MSC bands, 5%/10% split, ₱20,000 regular cap, MPF/WISP, EC ₱10/₱30 |
| RA 11199 (Social Security Act of 2018) | 2019 | Statutory basis of the 15% schedule |
| RA 11223 (Universal Health Care Act), Sec. 10 | 2019; rate schedule to 2025 | PhilHealth 5%, ₱10,000 floor, ₱100,000 ceiling |
| RA 9679 (HDMF Law of 2009), Sec. 7 — https://lawphil.net/statutes/repacts/ra2009/ra_9679_2009.html | 2009 | Pag-IBIG 1%/2%, employer 2%, adjustable maximum fund salary |
| HDMF Circular No. 460, *Guidelines on the Pag-IBIG Fund's Implementation of Increase in the MFS Effective February 2024* | Effective Feb 2024 | Maximum fund salary ₱10,000 (content itself unreachable — see limitations) |
| RA 10963 (TRAIN), Sec. 9 (amending NIRC Sec. 32(B)(7)(e)) and Sec. 5 (amending NIRC Sec. 24, the 2023-onwards annual table) — https://lawphil.net/statutes/repacts/ra2017/ra_10963_2017.html | 2018 / 2023 | BIR bracket values and the ₱90,000 13th-month/other-benefits exclusion |
| BIR RR 11-2018, Annex F (semi-monthly withholding table) as reflected in the TRAIN schedule | 2018 / 2023 | Semi-monthly and monthly withholding brackets |
| DOLE, *Handbook on Workers' Statutory Benefits* (2023), pp. 14–25 (in-repo: `docs/2023-Handbook-on-Workers-Statutory-Benefits.pdf`) | 2023 edition | Holiday pay (Art. 94), premium pay (Arts. 91–93), overtime (Art. 87), night shift differential (Art. 86), the DOLE 1.3/1.5/2.6/3.0/3.9 guide table and the NSD/OT combinations |
| PD 851 as amended by MO 28 | 1975 / 1986 | 13th-month pay basis (1/12 of basic salary earned in the calendar year) |

### Research limitations (explicitly unresolved)

- `pagibigfund.gov.ph` serves a reCAPTCHA interstitial to automated clients; the text of **Circular No. 460 could not be retrieved**. The circular's title (found via search index) confirms an MFS increase effective February 2024, and the ₱10,000 figure used by the code matches the widely published schedule, but this is **secondary evidence** and is marked `NEEDS PROFESSIONAL CONFIRMATION`.
- No **2026** issuance was found on the SSS or PhilHealth circular indexes for contribution rates. The engine applies the 2025 SSS table and the 2024/2025 PhilHealth table to 2026 payroll dates because those rows are open-ended (`effective_to = NULL`). Whether the 5% PhilHealth premium continues unchanged in 2026 must be confirmed.
- `dole.gov.ph` and `officialgazette.gov.ph` block automated retrieval (HTTP 403); labor-standard citations rely on the official DOLE handbook already in the repository.
- Regional minimum wage and the MWE classification are region- and issuance-specific; no wage-order table is loaded by the engine (only `statutory_min_wage`, which is empty — see F-08).

---

## 3. How the payroll is calculated today

```
timesheets (day_type, actual_regular/ot/nt/nt_ot in hours x100)
        │
        ├─ employees.basic_salary (centavos), daily_rate
        │
        ▼
db.Load ── payroll_groups (frequency, cut-off, days_year, hours_day, schedules)
        └─ statutory_* tables effective-dated on payrolls.payroll_date
                (fallback: hard-coded 2025/2024 tables in Go and config)
        ▼
payroll.Compute (one payslip per employee)
   daily  = round(monthly x 12 / days_year)
   hourly = round(daily / hours_per_day)
   RegularPay = Σ hourly x hours x dayMultiplier
   OT         = Σ hourly x otHours x dayMultiplier x 1.25
   Night      = Σ hourly x nightHours x dayMultiplier x 0.10
   Gross      = Regular + OT + Night + NightOT + Allowances + Incentives
   SSS / PhilHealth / Pag-IBIG on monthly basic, divided by 1 or 2 by schedule
   Taxable    = Gross − non-taxable − employee statutory contributions
   Tax        = BIR semi-monthly|monthly table
   Net        = Gross − (SSS + WISP + PhilHealth + Pag-IBIG + tax + other deductions)
        ▼
db.Write  (DELETE payroll_details, INSERT all rows, UPDATE payrolls totals,
           status = 'processed') — single transaction + MySQL advisory lock
        ▼
payslip PDF (PayslipGateway, docs/payslip.pdf template)
```

Statutory amounts are assessed on **`employees.basic_salary`**, not on compensation actually earned in the period. Attendance only affects gross earnings.

---

## 4. Compliance matrix

Status legend: **PASS**, **FAIL**, **PARTIAL**, **NOT TESTED**, **NEEDS PROFESSIONAL CONFIRMATION**.

| # | Rule / requirement | Employee category | Authority & effectivity | Current behaviour | Status | Gap / action |
|---|---|---|---|---|---|---|
| 1 | Premium for work on a rest day = 130% | All covered | Labor Code Art. 93; DOLE handbook p. 20 | `rd` now maps to 1.30 | PASS (fixed) | F-01 |
| 2 | Special non-working day worked = 130% | All covered | Art. 95; DOLE p. 20 | `sh` → 1.30 | PASS (fixed) | F-01 |
| 3 | Special day falling on rest day = 150% | All covered | DOLE p. 20 | `rd-sh` → 1.50 | PASS (fixed) | F-01 |
| 4 | Regular holiday worked = 200% | Covered employees | Art. 94; DOLE p. 15 | `rh` → 2.00 | PASS (fixed) | F-01 |
| 5 | Regular holiday on rest day = 260% | Covered | DOLE p. 16 | `rd-rh` → 2.60 | PASS (fixed) | F-01 |
| 6 | Double regular holiday = 300% / on rest day 390% | Covered | DOLE p. 24 | `2rh` → 3.00; `rd-2rh` → 3.90 | PASS (fixed) | F-01 |
| 7 | Regular + special holiday = 260% / on rest day 300% | Covered | DOLE p. 24 | `rh-sh` → 2.60; `rd-rh-sh` → 3.00 | PASS (fixed) | F-01 |
| 8 | Unworked regular holiday = 100% of daily wage | Covered, incl. daily-paid (with the day-before condition) | Art. 94; DOLE p. 14–15 | No pay unless hours > 0 | FAIL | F-04 |
| 9 | Overtime on ordinary day = 125% | Covered | Art. 87; DOLE p. 21 | `× 1.25` | PASS | — |
| 10 | OT premium on premium days (169/195/260/338/390/507%) | Covered | DOLE pp. 21–25 | `dayMultiplier × 1.25` | PASS | — |
| 11 | Night shift differential = 10% for 22:00–06:00 | Covered | Art. 86; DOLE p. 23 | `hourly × nightHours × dayMultiplier × 0.10` | PARTIAL | F-03 (night OT) |
| 12 | NSD on OT hours = 10% of the OT rate | Covered | DOLE p. 25 | Adds `×1.25×0.10` for `actual_nt_ot`, but the base OT pay is missing when `actual_ot` is 0 | FAIL | F-03 |
| 13 | Overtime only after the qualification threshold | Company policy (`minutes_qualified_overtime`) | Company setting | Enforced when the timesheet is entered, not by the engine | PASS | — |
| 14 | Paid leave pays the daily rate | All | Company policy / CBA | Pays `daily` for `is_paid_leave` or `pl` | PARTIAL | F-13 (flag not set by all entry paths) |
| 15 | Unpaid absence reduces pay once | All | No work, no pay | Absent days earn nothing **and** the amount is subtracted from gross | FAIL (fixed) | F-05 |
| 16 | Monthly-paid employees receive a fixed monthly rate | Monthly Paid | Contract; DOLE monthly-paid convention | Paid per attended hour, so each cut-off varies with working-day count | FAIL | F-04 |
| 17 | 13th-month pay = 1/12 of basic salary earned in the year | Rank-and-file | PD 851 as amended | Not computed; helper unused | NOT IMPLEMENTED | F-09 |
| 18 | 13th-month and other benefits exempt up to ₱90,000 | All | RA 10963; NIRC 32(B)(7)(e) | Split helper exists; no aggregation with other benefits, not applied to payroll | PARTIAL | F-09 |
| 19 | Final pay, leave conversion, separation pay | Separating employees | Arts. 297–299; company policy | Implemented; the pro-rated 13th month uses the notional period basic | PARTIAL | F-10, F-26 |
| 20 | SSS 2025 schedule (15%, MSC ₱5,000–₱35,000, ₱500 steps centred on the credit) | Private sector | SSS Circular 2024-006 | Bands now `[MSC−250, MSC+249.99]` in Go and in the seeded table | PASS (fixed) | F-06 |
| 21 | SSS MPF/WISP above ₱20,000 MSC (EE 5%, ER 10%) | Private sector | SSS Circular 2024-006 | Implemented and verified against the circular | PASS | — |
| 22 | Employees' Compensation: ₱10 up to MSC ₱14,500, ₱30 from ₱15,000 | Private sector | SSS Circular 2024-006 | Now applied by MSC | PASS (fixed) | F-07 |
| 23 | SSS contributions split across cut-offs per company schedule | Private sector | Company policy | `Divisor()` splits monthly amounts 1 or 2 ways | PASS | F-15 note |
| 24 | PhilHealth 5%, floor ₱10,000, ceiling ₱100,000, 50/50 | Private sector | RA 11223 Sec. 10 | Matches for 2024–2025 | PASS (2025) / NEEDS PROFESSIONAL CONFIRMATION (2026) | F-20 |
| 25 | Pag-IBIG 1% (≤ ₱1,500) / 2% employee, 2% employer, ₱10,000 MFS | Private sector | RA 9679 Sec. 7; HDMF Circular 460 | Matches | PASS / NEEDS PROFESSIONAL CONFIRMATION (circular text) | — |
| 26 | BIR withholding table for 2023 onwards (monthly & semi-monthly) | All | RA 10963; RR 11-2018 | Values match the published tables | PASS | — |
| 27 | Year-end annualisation / substitution (BIR 2316) | All | RR 11-2018 | Not implemented | NOT IMPLEMENTED | F-16 |
| 28 | Minimum wage earners exempt on statutory wage, holiday/OT/NSD and 13th month | MWE | RA 9504; RA 10963 | Monthly-paid staff are exempt; daily-paid staff are exempted on one day of minimum wage only | PARTIAL | F-08, F-25 |
| 29 | De minimis benefits non-taxable | All | RR 2-98 as amended | `employees.deminimis` ignored; no catalogue | FAIL | F-13 |
| 30 | Allowances / commissions / bonuses included in gross and taxed correctly | All | NIRC; company policy | Not loaded by the engine | FAIL | F-02 |
| 31 | Loans and other deductions withheld | All | Company policy; Art. 113 limits | Not loaded by the engine | FAIL | F-02 |
| 32 | Employee-specific benefit entitlements respected | All | Company policy | `rest_day_pay`, `*_holiday_pay`, `overtime_pay`, `night_differential` ignored | FAIL | F-13 |
| 33 | Statutory tables versioned and effective-dated | — | Audit control | `statutory_*` tables with `effective_from/to`; fallbacks if missing | PASS / PARTIAL | F-20 |
| 34 | Approved payroll is immutable; recalculation is auditable | — | Audit control | Hard delete + reinsert; `--force` resets `approved` to `processed` | FAIL | F-12 |
| 35 | Payroll liabilities and journal entries reconcile to the register | — | PFRS/accounting control | No GL, no journal, no register export | NOT IMPLEMENTED | F-19 |
| 36 | Monetary amounts are exact and reproducible | — | Audit control | Integer centavos; half-up rounding | PASS | F-17 note |
| 37 | Processing is idempotent and cannot double-run | — | Audit control | Transaction + `GET_LOCK`, delete-then-insert | PASS | — |
| 38 | Tenant isolation of payroll data | — | Privacy/security control | Every query filters `company_id` | PASS | — |

---

## 5. Detailed findings

### F-01 — Premium day types were never recognised (CRITICAL — fixed)

**Description.** `rules.DayMultiplier`, `IsHoliday`, `IsRestDay`, `IsPaidDay` and `IsUnpaidDay` matched canonical English keys. `timesheets.day_type` stores the short codes used by `rates_new` (`regular`, `rd`, `sh`, `rh`, `rd-sh`, `rd-rh`, `2rh`, `rd-2rh`, `rh-sh`, `rd-rh-sh`, `pl`, `upl`). Only `regular` matched, so every premium day was paid at **100%** and reported as no holiday/rest-day pay.

**Reproduction.** `SELECT day_type, COUNT(*) FROM timesheets GROUP BY day_type` on the audit database returns `regular` 231, `rd` 69. Before the fix, `rules.DayMultiplier("rd")` returned `1.00`.

**Evidence.** `payroll-engine/internal/rules/rules.go:54` (switch on canonical keys only) versus `app/Models/Timesheet.php:15-45` (stored codes) and `cache_rates_options()` feeding the editable `rates_new` table.

**Impact.** For a ₱26,100 employee, one 8-hour rest day underpaid ₱360 (1,200 → 1,560). The five-day scenario in section 6 underpaid ₱2,032.50 per cut-off.

**Root cause.** A port from the rate table's codes to invented English keys, with tests that only used the invented keys (`rules_test.go` used `DayRest`, never `"rd"`).

**Fix applied.** Added `storedDayCodes` and canonical constants for the combined holidays; `normalize()` now maps stored codes (including `-` separators) before lookup. Tests: `internal/rules/day_codes_test.go`, `internal/payroll/compute_test.go::TestComputePremiumsForStoredDayCodes`.

---

### F-02 — Allowances, compensations, bonuses, commissions, deductions and loans are ignored (CRITICAL — open)

**Description.** `db.loadEmployees` selects only `employees` columns and then `db.loadTimesheets`. Nothing reads `employees_allowances`, `employees_compensations`, `employees_bonuses`, `employees_commissions`, `employees_deductions` or `employees_loans`. `slip.Allowances`, `slip.Incentives` and `slip.OtherDeductions` are therefore always zero, `payroll_details.allowance`/`incentive`/`compensations` are always empty, and the payslip prints zero for every configured earning and deduction that is not statutory.

**Reproduction.** Run `bin/payroll-engine --payroll-id=<id> --dry-run` for a company with configured allowances/loans; every payslip reports `allowances = 0` and `other deductions = 0`. In the audit database: 1 allowance, 4 compensations, 3 deductions, 3 loans, 5 bonuses, 4 commissions.

**Evidence.** `payroll-engine/internal/db/load.go:271` (`loadEmployees` — employees only), `:321` (`loadTimesheets`), `payroll-engine/internal/payroll/compute.go:110-116` (`for _, earning := range employee.Allowances` — never populated), `payroll-engine/internal/db/write.go:51-63` (writes zero allowances/incentives). The legacy `ProcessPayrollEngine` *does* load them (`ProcessPayrollEngine.php:143-150`), which shows the requirement exists.

**Impact.** Gross understated by non-attendance earnings; net overstated by loans and other deductions; payslips do not match contracts or loan amortisation schedules. This is a payroll-of-record failure.

**Recommended fix.** Extend `model.Employee` and `db.Load` with effective-dated queries for the six tables (frequency = cut-off or `every_pay`, valid dates vs the payroll period, `is_active`), map taxable/non-taxable flags, apply proration and loan amortisation, and persist the breakdown (including `payroll_details.compensations`). Port the logic from `ProcessPayrollEngine::computeTotalAllowance/computeTotalDeduction/computeTotalLoans/computeBonus/computeCommission` rather than reinventing it.

---

### F-03 — Night-shift overtime is paid at 12.5% instead of 137.5% (CRITICAL — open)

**Description.** The timesheet editor's night-OT path (`Timesheet::addNightOverTime`) records the OT hours in `actual_nt_ot`, sets `actual_nt` to the *total* night hours and **skips** `addOverTime`, leaving `actual_ot = 0`. The engine then pays:
- `OvertimePay = hourly × actual_ot × dayMultiplier × 1.25 = 0`
- `NightOTPay = hourly × actual_nt_ot × dayMultiplier × 1.25 × 0.10` → 12.5% of the rate
- `NightPay` adds 10% for the same hours (they are inside `actual_nt`)

For two night-OT hours on an ordinary day the engine pays only the 12.5% differential (0.25 × hourly, plus a further 10% for the same hours through `NightPay`) instead of 2.75 × hourly.

**Evidence.** `app/Models/Timesheet.php:406-460` (night-OT writer), `app/Livewire/Admin/Actions/EditViewTimeSheet.php:105-121` (skips `addOverTime` when `isOtNt`), `payroll-engine/internal/payroll/compute.go:70-91`.
**Recommended fix.** Treat `actual_nt_ot` as a disjoint bucket: pay `hourly × ntOT × dayMultiplier × 1.25` as overtime **and** add the 10% differential, and exclude night-OT hours from `NightPay` (or define once whether `actual_ot` includes them and normalise in the loader). Document the bucket semantics in `model.TimesheetLine`. Add a test for a 22:00–02:00 shift.

---

### F-04 — Monthly-paid basis and unworked holiday pay are missing (HIGH — open)

**Description.** The engine has no payment type. `model.Employee` has no `PaymentType`, and `db.loadEmployees` does not select `employees.payment_type` (values `monthly` / `daily`). Pay is always the sum of attended hours × hourly rate. Consequences:

1. A **monthly-paid** employee's semi-monthly pay equals `days_worked × daily_rate`. With `days_year = 261`, a full month pays the monthly rate only when exactly 21.75 days are attended; 10- and 12-working-day cut-offs differ by ~₱1,200 on a ₱26,100 salary, instead of the fixed ₱13,050.
2. **Unworked regular holidays pay nothing.** Article 94 and the DOLE handbook (p. 14–15) require 100% of the daily wage for covered employees present or on paid leave on the preceding workday; 200% if they work. The engine pays 0 or 200%, never 100%.
3. **Daily-paid** employees are also entitled to unworked regular holiday pay subject to the same condition; the engine has no rule for it at all.

**Evidence.** `payroll-engine/internal/model/model.go:33-47` (no payment type), `payroll-engine/internal/db/load.go:273-279` (selected columns), `payroll-engine/internal/payroll/compute.go:50-75` (only lines with hours are paid). The legacy PHP path implements the monthly basis (`ProcessPayrollEngine.php:205-207` halves the monthly salary and deducts absences) and the DOLE formula is documented in the employee guide.

**Recommended fix.** Load `payment_type`, `is_monday_friday` and the holiday/rest-day entitlement flags. For monthly-paid employees compute `period basic = monthly × (12/days_year) × period_days` (or monthly ÷ periods), pay premiums as the *premium portion only* (e.g. regular holiday worked = +100% of daily rate), and add 100% for unworked regular holidays where the eligibility condition holds. This is a design change; do not patch it by adding a second base on top of attendance pay.

---

### F-05 — Absences were deducted from pay that had already excluded them (HIGH — fixed)

**Description.** Absent lines were skipped (`continue`), so the day earned nothing, and then `Gross` subtracted `AbsentAmount = daily × absentDays` a second time.

**Reproduction.** One worked day, one absent day, one paid leave day at ₱1,200/day produced gross ₱1,200 instead of ₱2,400 (previously asserted by `TestComputeAbsenceAndPaidLeave`).

**Evidence.** `payroll-engine/internal/payroll/compute.go:50-56` and `:106-124` before the fix.

**Fix applied.** `AbsentAmount` is still computed and reported (for the payslip and register) but no longer subtracted from the gross. The existing test was corrected to assert ₱2,400 and to explain why.

---

### F-06 — SSS salary-credit bands were offset by ₱250 (HIGH — fixed)

**Description.** Bands were generated as `[MSC, MSC+499.99]`. Circular 2024-006 centres each band on the credit: `₱5,250.00–₱5,749.99 → MSC ₱5,500.00`, and anything below ₱5,250 is billed at ₱5,000. For the first ₱250 of every band the engine used the previous (lower) credit.

| Monthly compensation | Circular MSC | Engine MSC before | EE before | EE per circular |
|---|---|---|---|---|
| 5,300.00 | 5,500 | 5,000 | 250.00 | 275.00 |
| 14,750.00 | 15,000 | 14,500 | 725.00 | 750.00 |
| 20,300.00 | 20,500 | 20,000 | 1,000.00 | 1,025.00 |
| 26,499.99 | 26,500 | 26,000 | 1,300.00 | 1,325.00 |

**Evidence.** `payroll-engine/internal/statutory/statutory.go:171` before the fix; `app/Console/Commands/PayrollStatutoryImport.php:40` generated the same offset into `statutory_sss_brackets`, i.e. the live table (verified: 62 rows, first band `0–4999.99`).

**Fix applied.** Go generator and the import command now build `[MSC−250, MSC+249.99]` bands with a 61-row table; `php artisan payroll:statutory:import` was re-run and the seeded rows were verified against the circular (MSC 14,500 / 15,000 / 20,000 / 20,500 / 35,000).

**Impact.** Employee under-contribution and under-remittance for half of all salary values; retroactive correction and possible SSS assessment for affected periods.

---

### F-07 — Employees' Compensation was flat ₱10 (HIGH — fixed)

**Description.** Every MSC received ₱10 EC. The circular requires ₱10 up to MSC ₱14,500 and ₱30 from MSC ₱15,000. Employer contribution was understated by ₱20 per month for every employee at or above ₱15,000 MSC.

**Evidence.** `statutory.go:137` (before), `config/payroll.php:40-51` (`ec_employer => 10` only), `PayrollStatutoryImport.php:60`.

**Fix applied.** EC is now derived from the MSC (`sssEmployeesCompensation`), the config gained `ec_employer_high` / `ec_high_msc`, and the importer writes the correct EC per row (verified: MSC 14,500 → 10.00; MSC 15,000 → 30.00).

---

### F-08 — Minimum wage earners are taxed (HIGH — open)

**Description.** `employees.is_minimum_wage` is never loaded. The engine computes taxable income as gross minus employee statutory contributions, so an MWE's statutory minimum wage, holiday/OT/NSD pay and 13th-month pay are taxed, contrary to RA 9504 as amended by RA 10963. `config.MinimumWage` is only used to emit a warning; `statutory_min_wage` is empty.

**Evidence.** `payroll-engine/internal/db/load.go:155-263` (no MWE/minimum-wage employer classification), `compute.go:171-186`, `statutory.go` (`BelowMinimum` used only for a warning).

**Recommended fix.** Load `is_minimum_wage`, the applicable regional wage order (with effectivity), and subtract the statutory minimum wage plus the holiday/OT/NSD attributable to it from taxable income; block or warn when the computed daily rate is below the effective minimum wage for the region.

---

### F-09 — 13th-month pay is not computed, paid or taxed (HIGH — open)

**Description.** Only `rules.ThirteenthMonth` and `rules.SplitThirteenthMonth` exist; nothing calls them. The engine has no 13th-month run, no accrual, no December deadline handling, no aggregation of 13th month with other benefits against the ₱90,000 exclusion, and no taxable-remainder withholding.

**Evidence.** `payroll-engine/internal/rules/rules.go` (`ThirteenthMonth`, `SplitThirteenthMonth` — unused), `app/Models/Bonus.php:19` (a `13th Month Pay` constant, not used by the engine), no 13th-month code in `compute.go`.

**Recommended fix.** Implement a 13th-month run per calendar year: total basic salary actually earned (excluding allowances, OT, NSD, holiday premiums and absences without pay) ÷ 12; pay on or before 24 December or on separation; aggregate the ₱90,000 exclusion across 13th month and other benefits per employee per year; withhold on the excess. Requires a per-employee year-to-date basic-salary ledger (see F-16).

---

### F-10 — Final pay and separation are not implemented (HIGH — open)

**Description.** No computation of final salary, unused leave conversion, pro-rated 13th month, separation pay (Arts. 297–299) or their tax treatment. `employees.date_resigned` and employment-type changes are not read by the engine.

**Recommended fix.** Add a separation settlement flow with explicit legal basis (just cause vs authorised cause vs retrenchment), leave conversion policy, final 13th month, and the BIR treatment of separation pay (exempt when due to sickness/death/other physical disability or beyond the employee's control, subject to the ₱10,000/₱100,000 rules of RR 11-2018 as applicable). Requires labour-law confirmation per case.

---

### F-11 — `timesheets.regular/ot/nt` are hours, but read as money (HIGH — open)

**Description.** `Timesheet` accessors divide `regular`, `ot`, `nt`, `nt_ot` by 100 (hours × 100), and `addTime()` writes premium-equivalent hours into them. The Go loader maps `regular` into `PrecomputedPay` (centavos) and uses it when `actual_regular` is zero. A legacy row with `regular = 800` and no `actual_regular` pays **₱8.00** for an 8-hour day.

**Evidence.** `app/Models/Timesheet.php:83-113` (accessors) and `:537-540` (`$this->{$fieldRate} = $hours`), `payroll-engine/internal/db/load.go:355-372` (`PrecomputedPay: money.Centavos(int64(regularPay))`), `compute.go:64-76`.

**Recommended fix.** Delete the `Precomputed*` money fallback, or reinterpret the columns as premium-equivalent hours and value them at the hourly rate. Add a loader test with `regular` populated and `actual_regular` empty.

---

### F-12 — No immutable payroll snapshot and no recalculation audit trail (HIGH — open)

**Description.** `db.Write` issues `DELETE FROM payroll_details` and re-inserts. `PayrollDetail` uses `SoftDeletes`, so the hard delete bypasses the intended history. `Write` also sets `status = 'processed'` unconditionally, so a forced re-run of an `approved` payroll unlocks it. There is no record of who re-ran what, with which engine version, inputs or totals; `slip.Warnings` are written to stdout only.

**Evidence.** `payroll-engine/internal/db/write.go:34-38` (hard delete) and `:74-80` (status reset); `app/Models/PayrollDetail.php:9,15`; `cmd/payroll-engine/main.go:113-116` (status guard only).

**Recommended fix.** Insert a new payroll version (or copy details to `payroll_detail_revisions`) instead of deleting; refuse `--force` on `approved` unless an explicit reopen step is recorded; persist warnings, input hashes and engine version on the payroll; write an audit event per run.

---

### F-13 — Employee benefit switches and de minimis are ignored (HIGH — open)

**Description.** `employees.rest_day_pay`, `regular_holiday_pay`, `special_holiday_pay`, `premium_holiday_pay`, `regular_holiday_special_holiday_pay`, `overtime_pay`, `night_differential`, `deminimis` and `timesheet` are not read. Employees contractually excluded from a premium receive it; `deminimis` never reaches taxable/non-taxable income. `is_paid_leave` is only set by some entry paths, so leave may be unpaid under the `pl` code path.

**Evidence.** `payroll-engine/internal/db/load.go:271-319`; `app/Livewire/Admin/Actions/EditViewTimeSheet.php` (paid-leave flag).

**Recommended fix.** Load the flags and gate each earning bucket; implement a de-minimis catalogue with the RR 2-98 ceilings (rice, clothing, medical, laundry, meal, medical assistance, etc.).

---

### F-14 — Statutory contributions are assessed on basic salary only (MEDIUM — open)

**Description.** SSS, PhilHealth and Pag-IBIG are computed from `employees.basic_salary`. Salary-related allowances that form part of "compensation" for SSS/HDMF purposes are excluded; conversely, part-time or no-pay periods keep the full monthly contribution. PhilHealth's premium base is the monthly basic salary, so its treatment is defensible, but SSS and HDMF use monthly compensation.

**Recommended fix.** Define one contribution base per agency in configuration, document it, and compute per period with the statutory rules for unpaid leave and partial months. Confirm with each agency's current circulars.

---

### F-15 — Non-uniform deduction schedules distort withholding (MEDIUM — open)

**Description.** When SSS/PhilHealth/HDMF are scheduled on the first or last pay only, the other cut-off's taxable income is computed without those employee contributions, so the semi-monthly table over-withholds in that cut-off and the pair does not reconcile to the monthly withholding. (Example: payroll group 3 uses `last_pay` schedules.)

**Recommended fix.** Compute withholding on the monthly basis and allocate, or annualise per employee across cut-offs (also resolves F-16).

---

### F-16 — No annualisation, YTD tracking or BIR 2316 (MEDIUM — open)

**Description.** Each cut-off is taxed independently from the semi-monthly table. There is no year-to-date taxable income, no adjustment for new hires/separations, no previous-employer consolidation, and no year-end true-up.

**Recommended fix.** Persist per-employee YTD taxable income, tax withheld and 13th-month benefits; add a year-end run that annualises, compares against the annual table and posts the adjustment (BIR 2316 / substituted filing).

---

### F-17 — Independent rounding of statutory components (LOW — open)

**Description.** Each statutory component is rounded separately and then halved (`split()`), so the sum of two cut-offs can differ from the monthly amount by ₱0.01. `SSSTotal` is also rounded from the monthly total independently of its parts.

**Recommended fix.** Round the monthly amount once, then allocate the balancing centavo to a defined cut-off (`money.Split` already does this deterministically).

---

### F-18 — Non-taxable amounts are trusted without a policy (MEDIUM — open)

**Description.** `slip.NonTaxable` is the sum of allowances flagged non-taxable; there is no de-minimis ceiling, no 13th-month aggregation and no validation that the flag is lawful. `payroll_details.non_taxable_income` is written from that value.

**Recommended fix.** Classify non-taxable amounts against a maintained catalogue with ceilings and effectivity, and reconcile non-taxable income to the 2316.

---

### F-19 — No payroll accounting or reconciliation (MEDIUM — open)

**Description.** The system stores payroll details only. There is no journal entry, no chart-of-accounts mapping, no payroll register export and no remittance reconciliation (SSS R-3, PhilHealth RF-1, HDMF, BIR 1601-C/2316). Employer contributions are computed (`TotalEmployerCost`) but never posted.

**Evidence.** No `journal`, `ledger` or `GeneralLedger` code or table exists; `app/Models/Account.php` is a billing/department-like model, and `Disbursement` maps to `companies_banks`.

**Recommended fix and the entries the data already supports**

For a processed run (amounts from `payroll_details`):

```
Dr  Salaries and wages expense            Σ gross_income
Dr  Employer contributions expense        Σ (sss_employer_total + total_philhealth + hdmf_total)
    Cr  Withholding tax payable                    Σ withholding_tax
    Cr  SSS contributions payable                  Σ sss_employee_total + sss_employer_total
    Cr  PhilHealth contributions payable           Σ total_philhealth
    Cr  Pag-IBIG contributions payable             Σ hdmf_total
    Cr  Loans and other deductions payable         Σ (total_deductions − statutory)
    Cr  Net pay payable                            Σ net_pay
```

Two mapping defects must be fixed before this can be automated: `payroll_details.basic_salary` is written from `RegularPay` (attendance pay, including premiums) rather than the period basic (`db/write.go:53`), and `total_employee` is written equal to `total_deductions` (`db/write.go:63`), so there is no separate "employee deductions" figure. Publish a payroll register (employee, gross, each deduction, employer share, net) and reconcile it to the journal.

---

### F-20 — Statutory tables are open-ended and can go stale silently (MEDIUM — open)

**Description.** The seeded rows have `effective_to = NULL`, so the 2025 SSS schedule and the 2024 PhilHealth schedule apply to any later payroll date, including 2026. There is no guard, alert or test that fails when the payroll date is outside a table's known validity. `ensureTaxBrackets` is a no-op wrapper. `PayrollStatutoryImport` only creates one effectivity, so a 2026 change needs a manual `config/payroll.php` edit before re-import.

**Recommended fix.** Add `effective_to` to the current rows when a successor is imported, validate at load time that the payroll date is covered (fail loudly, not silently), and record the source circular and URL per row (the `statutory_sources` table already exists).

---

### F-21 — Payslip "basic salary" is attendance pay (MEDIUM — open)

**Description.** `db.Write` maps `regular_pay` to `payroll_details.basic_salary`. The payslip prints it as the basic-salary line, so rest-day and holiday premiums appear as basic pay, while `basic_monthly_salary` (which is correct) is not printed.

**Recommended fix.** Write the period basic to `basic_salary` and keep attendance/premium buckets in their own columns.

---

### F-22 — 6-day divisor 312 vs 313 (LOW — open)

**Description.** Payroll group 2 uses `days_year = 313`, while the setup guide tells users to enter 312 and the form caps the value at 312. DOLE's 6-day factor is 313 (365 − 52 Sundays); 312 overstates the daily rate by ~0.32%.

**Recommended fix.** Standardise on 313 for Monday–Saturday schedules, align the form, help text and guide, and document the divisor in the payroll settings page.

---

### F-23 — Payrolls with `cut_off = NULL` never deduct scheduled contributions (LOW — open)

**Description.** `Divisor()` compares the normalised schedule with the raw `payrolls.cut_off`. A payroll with a NULL cut-off (one row exists) matches no `first_pay`/`last_pay` schedule, so SSS/PhilHealth/HDMF are silently skipped.

**Recommended fix.** Default the cut-off from the payroll date or fail validation when a schedule needs a cut-off and none is set.

---

### F-24 — Diagnostics and totals semantics (LOW — open)

**Description.** `slip.Warnings` (negative net clamped to zero, missing attendance, below-minimum wage) go to stdout only and are not stored. `holiday_pay`/`rest_day_pay` report total pay (base + premium) rather than the premium portion, and `sss_total` mixes employee and employer amounts into a single column.

**Recommended fix.** Persist warnings per employee/run; report premium components separately; keep employee and employer totals distinct.

---

### F-25 — Daily-paid minimum wage earners are only partly exempt (HIGH — open)

**Description.** For a daily-paid minimum wage earner the exemption is built from **one day** of minimum wage plus the period's premiums (`minimumWagePay` returns the daily minimum wage for daily-paid staff and is added once), instead of the minimum wage actually earned across the period. A ten-day semi-monthly period at ₱640/day earns ₱6,400 of statutory minimum wage, but only ₱640 is exempted, leaving ₱5,136 of exempt wage inside taxable income.

**Evidence.** `payroll-engine/internal/payroll/audit_coverage_test.go`, `TestMinimumWageExemptionCoversTheWholePeriod` (pending): exemption ₱856.00, expected ₱6,616.00; taxable income ₱5,136.00, expected ₱0.00.

**Recommended fix.** Base the exemption on the statutory wage actually earned in the period, plus the overtime, night-shift and holiday premiums attributable to it. Monthly-paid staff are already correct because `minimumWagePay` uses the monthly equivalent.

**Impact.** No tax arises in the audited scenario because ₱5,136 stays under the semi-monthly threshold, but a minimum wage earner with overtime, night differential or holiday premiums can cross it and be taxed on income the law exempts.

---

### F-26 — Final pay pro-rates 13th month on the notional period basic (MEDIUM — open)

**Description.** `ComputeFinalPay` adds `slip.PeriodBasic` to the year-to-date basic before dividing by 12. For monthly-paid staff that equals the amount earned, but for daily-paid staff it is the monthly rate divided by the number of pays, not the basic earned in the final period. A daily-paid employee who separates mid-period is therefore paid too much 13th month.

**Evidence.** `TestFinalPayProratesThirteenthMonthOnEarnedBasic` (pending): ₱144,000 earned year to date plus ₱6,000 in the final period should give ₱12,500; the engine returns ₱13,087.50 because it uses ₱13,050 of notional period basic.

**Recommended fix.** Use `slip.BasicEarned` — the basic actually earned in the period — instead of `slip.PeriodBasic`.

---

## 6. Independent recalculation

**Method.** A separate Python implementation (`independent_recalc.py`, `Decimal` arithmetic, half-up rounding) computes each scenario from the official tables and the DOLE guide table, with no call into the application. Expected values were derived before reading engine output; the script then diffs the fields per scenario:

```
$ python3 docs/payroll-audit/independent_recalc.py
# exit code 0 = every oracle value matches the Go suite
```

Each scenario reports `oracle`, the value asserted in the Go suite, and — for the two open findings — the value the engine returns today.

**Scenarios** (semi-monthly, `days_year = 261`, 8 hours/day, contributions on every pay):

| ID | Employee | Attendance |
|---|---|---|
| S1 | ₱26,100 monthly | 11 regular days × 8h |
| S2 | ₱20,300 monthly | 11 regular days × 8h |
| S3 | ₱35,000 monthly | 11 regular days × 8h |
| S4 | ₱14,750 monthly | 11 regular days × 8h |
| S5 | ₱26,100 monthly | 1 regular, 1 rest day, 1 regular holiday, 2h rest-day OT, 1 special day |
| S6 | ₱26,100 monthly | 1 worked, 1 absent, 1 paid leave |
| S7 | ₱30,000 monthly | 11 regular days + 2h OT + 2h night |

**Result after the fixes: 140 field comparisons, 0 mismatches.**

| Scenario | Gross | SSS EE | WISP EE | PhilHealth EE | Pag-IBIG EE | Tax | Net pay |
|---|---|---|---|---|---|---|---|
| S1 | 13,200.00 | 500.00 | 150.00 | 326.25 | 100.00 | 256.01 | 11,867.74 |
| S2 | 10,266.96 | 500.00 | 12.50 | 253.75 | 100.00 | 0.00 | 9,400.71 |
| S3 | 17,701.20 | 500.00 | 375.00 | 437.50 | 100.00 | 880.76 | 15,407.94 |
| S4 | 7,459.76 | 375.00 | 0.00 | 184.38 | 100.00 | 0.00 | 6,800.38 |
| S5 | 7,207.50 | 500.00 | 150.00 | 326.25 | 100.00 | 0.00 | 6,131.25 |
| S6 | 2,400.00 | 500.00 | 150.00 | 326.25 | 100.00 | 0.00 | 1,323.75 |
| S7 | 15,637.59 | 500.00 | 250.00 | 375.00 | 100.00 | 599.34 | 13,813.25 |

**Pre-fix differences measured with the same independent model**

| Finding | Scenario | Before | Correct | Difference |
|---|---|---|---|---|
| F-01 premiums | S5 | ₱5,175.00 gross | ₱7,207.50 gross | **−₱2,032.50** per cut-off |
| F-05 absences | S6 | ₱1,200.00 gross | ₱2,400.00 gross | **−₱1,200.00** |
| F-06 SSS bands (20,300) | S2 | EE ₱1,000.00/mo, MSC 20,000 | EE ₱1,025.00/mo, MSC 20,500 | −₱25.00/mo |
| F-06 SSS bands (14,750) | S4 | EE ₱725.00/mo, MSC 14,500 | EE ₱750.00/mo, MSC 15,000 | −₱25.00/mo |
| F-07 EC | S2/S3/S4 | ₱10.00/mo | ₱30.00/mo | −₱20.00/mo employer |

**Rounding note.** Using the annualised method (annual table ÷ 24) instead of the BIR published semi-monthly table changes S1/S3/S7 withholding by ₱0.05 per cut-off. This is a table-versus-annualisation rounding artefact, not a defect; the published table is the operative withholding reference and is what the engine implements.

---

## 7. Automated tests added or corrected

| File | Test | Law-based expectation |
|---|---|---|
| `payroll-engine/internal/rules/day_codes_test.go` | `TestDayMultiplierForStoredCodes` | `rd` 1.30, `sh` 1.30, `rh` 2.00, `rd-sh` 1.50, `rd-rh` 2.60, `2rh` 3.00, `rd-2rh` 3.90, `rh-sh` 2.60, `rd-rh-sh` 3.00 (DOLE guide table) |
| | `TestStoredCodeClassification` | holiday/rest-day/paid-leave/unpaid-leave classification for stored codes |
| `payroll-engine/internal/statutory/sss_2025_test.go` | `TestSSS2025ScheduleMatchesCircular` | 13 salary points against the printed rows of Circular 2024-006, including MSC, EE, ER and EC |
| | `TestSSS2025WISPSplit` | ₱22,500 → regular ₱1,000 + MPF ₱125 EE; ₱2,000 + ₱250 ER; total ₱3,405 |
| | `TestSSS2025ScheduleCoversWholeRange` | 61 contiguous bands, `0–5,249.99` floor band, `34,750–over` ceiling band |
| `payroll-engine/internal/payroll/compute_test.go` | `TestComputePremiumsForStoredDayCodes` | rest day 1,560; regular holiday 2,400; special day 1,560; rest-day OT 487.50 |
| | `TestComputeAbsenceAndPaidLeave` (corrected) | absent day is not deducted twice: gross ₱2,400 |
| `payroll-engine/internal/statutory/statutory_test.go` | `TestSSS2025ScheduleBands` (corrected) | 61 bands per the circular |
| `payroll-engine/internal/statutory/tax_boundaries_test.go` | `TestTRAINTaxBandEdges` | both ends of every monthly and semi-monthly TRAIN band, computed as base tax + rate × excess |
| | `TestTRAINTaxBandsAreContinuous` | no gap or jump between bands, for both frequencies |
| | `TestContributionCeilingEdges` | PhilHealth floor ₱10,000 and ceiling ₱100,000; Pag-IBIG 1%→2% switch at ₱1,500 |
| `payroll-engine/internal/payroll/audit_coverage_test.go` | `TestAdditionalEarningsDeductionsAndLoansReconcile` | allowance, de minimis cap, deduction and loan each counted once: gross ₱16,550.00, taxable ₱14,473.75, tax ₱608.51, net ₱13,365.24 |
| | `TestContributionCeilingsInARun` | SSS capped at MSC ₱35,000 (₱500 + ₱375 WISP), PhilHealth ₱1,250, Pag-IBIG ₱100; tax ₱28,603.30, net ₱94,171.70, employer cost ₱3,115.00 |
| | `TestThirteenthMonthAggregatesOtherBenefits` | ₱300,000 13th month + ₱60,000 other benefits → ₱270,000 taxable, ₱3,000 tax, ₱297,000 net |
| | `TestThirteenthMonthExclusionBoundary` | exactly ₱90,000 exempt; ₱90,000 plus ₱90,000 other benefits fully taxable |
| | `TestRepeatedRunsAreDeterministicAndDoNotDoubleChargeLoans` | identical input twice → identical payslip; the loan is charged once |
| | `TestMinimumWageExemptionCoversTheWholePeriod` | **pending (F-25)** — exemption ₱856.00, expected ₱6,616.00 |
| | `TestFinalPayProratesThirteenthMonthOnEarnedBasic` | **pending (F-26)** — ₱13,087.50, expected ₱12,500.00 |
| | `TestNewHireProrationIsUnresolvedPolicy` | **pending (policy)** — a mid-period hire receives the full ₱13,050.00 period basic; proration is undefined |
| `tests/Feature/Admin/PayrollStatutoryImportTest.php` (Pest) | `it seeds 61 contiguous SSS bands…` | the seeded table matches the circular: floor band `0–5,249.99`, ceiling band from `34,750`, no gaps |
| | `it matches the salary credits and regular contributions…` | 10 salary points against the printed rows: MSC, employee, employer, EC, and the WISP split |
| | `it adds the regular and WISP shares…` | printed totals: ₱760.00 at the floor, ₱3,030.00 at MSC ₱20,000, ₱3,405.00 at ₱22,500, ₱5,280.00 at the ceiling |
| | `it seeds the BIR TRAIN band bases…` | all ten monthly and semi-monthly band bases and rates to the centavo |
| | `it seeds the PhilHealth, Pag-IBIG and tax schedules` | 5% / ₱10,000–₱100,000; 2% with a 1% floor and ₱10,000 fund salary; 12 tax rows |
| | `it seeds the de minimis ceilings…` | RR 2-98 ceilings and the five source records |
| | `it is idempotent…` / `it closes the predecessor schedule…` | a re-run does not duplicate; a newer effectivity closes the old rows instead of deleting them |
| `tests/Unit/Payroll/GoPayrollEngineTest.php` (Pest) | 9 tests | binary required; `--dry-run` for a preview and `--force` for a process; DSN and payroll id forwarded; stderr becomes the exception message; invalid JSON rejected; version degrades to `null` |
| `tests/Unit/Payroll/EngineResultTest.php` (Pest) | 4 tests | full payload mapping, defaults for a partial payload, non-list payslips ignored, `toArray()` round-trip |

**Verified expected values** were produced by the independent Python model, not copied from engine output.

**Commands and results**

```
$ cd payroll-engine && go test ./... -count=1
ok  .../internal/money      0.317s
ok  .../internal/payroll    0.115s
ok  .../internal/rules      0.317s
ok  .../internal/statutory  0.115s
# 51 tests: 48 passed, 3 skipped (the pending items above)

$ go vet ./...          # clean
$ gofmt -l .            # clean

$ cd .. && vendor/bin/pint --dirty --format agent
{"tool":"pint","result":"passed"}

$ php artisan test --compact
Tests: 1 skipped, 140 passed (755 assertions)

$ python3 docs/payroll-audit/independent_recalc.py
# exit code 0

$ php artisan payroll:statutory:import --no-interaction
SSS brackets: 61
PhilHealth: 0.05
Pag-IBIG: 0.02
Tax brackets: 12
```

The PHP suite grew from 96 to 140 tests in the third pass: `tests/Feature/Admin/PayrollStatutoryImportTest.php` (30 tests) pins what `payroll:statutory:import` writes against the circulars, and `tests/Unit/Payroll/` (14 tests) covers the engine result mapping and the binary invocation contract.

**Before the fixes** the new tests failed exactly as the findings predict (`DayMultiplier("rd") = 1, want 1.3`; `gross = 1200.00, want 2400.00`; `salary 5250: MSC = 5000.00, want 5500`; `salary 20000: EC = 10.00, want 30`), which is the regression evidence in this report.

The repository has no CI pipeline, so both suites and the oracle are run locally with the commands above; wiring them into a pipeline is a prerequisite for relying on them as a gate.

**Coverage added in the second pass (4 November 2026).** The gaps listed after the first pass are now covered at engine level: allowance, deduction and loan flow; contribution ceilings inside a run; 13th-month aggregation and the ₱90,000 boundary; TRAIN band edges and continuity; PhilHealth and Pag-IBIG floor and ceiling edges; and repeat-run determinism. Three scenarios remain deliberately pending: F-25, F-26 and the undefined new-hire proration policy. Before being pinned, each pending test was run with its skip disabled and failed with exactly the values recorded in section 5.

**Still not covered.** The loader (`internal/db/load.go`) is exercised through the engine's input contract only, not against seeded database fixtures, so a PHP feature test that runs the engine for a seeded company and asserts register totals is still the main gap. Concurrency, transaction rollback, reopen-with-reason and duplicate-run protection at the database boundary are PHP-side concerns outside this Go suite; the engine contract proven here is stateless and deterministic.

---

## 8. Accounting and control review

| Control | Assessment |
| --- | --- |
| Salary expense recognition | Only `gross_income` per employee is stored; there is no posting. `basic_salary` holds attendance pay, not the period basic (F-21). |
| Employer contribution expense | `total_employer` = SSS (ER + WISP + EC) + PhilHealth ER + Pag-IBIG ER. It excludes the employer's 13th-month accrual and any other statutory cost, and is never posted. |
| Employee deductions payable | `total_employee` duplicates `total_deductions`; there is no split between statutory payables and loans/other deductions (F-19). |
| Employer contributions payable | Not separable per agency in a payable account without parsing `sss_*`, `philhealth_*`, `hdmf_*` columns. |
| Net salary payable | `net_pay` may be clamped to zero on a shortfall with only a stdout warning (F-24), which would silently break the liability. |
| Journal balancing | No journal exists; the data supports the entries in F-19 once the mapping defects are fixed. |
| Duplicate postings | `db.Write` deletes then re-inserts in one transaction and a `GET_LOCK` prevents concurrent runs — good. Re-runs destroy prior versions (F-12). |
| Reversals and corrections | Not supported; `--force` overwrites. |
| Approvals / segregation of duties | `approved`/`closed` are guarded in the CLI and UI, but `--force` reopens without an approval trail. |
| Audit logs | None for payroll; the application does not write a payroll audit trail. |
| Access control | Tenant-scoped queries and policies exist; payroll detail screens are gated by Livewire authorization. |
| Evidence export | Payslip PDF only; no payroll register, no statutory report export, no reconciliation report. |

---

## 9. Recommended remediation order

1. **Before the next live run:** deploy the four fixes in this change, rebuild `bin/payroll-engine`, and re-run `php artisan payroll:statutory:import` on every environment so `statutory_sss_brackets` matches Circular 2024-006.
2. **Immediately after:** decide the monthly-paid basis (F-04) and night-OT bucket semantics (F-03) — both change payslip amounts and need a communication plan and, likely, a look-back on recent runs.
3. **Load non-statutory earnings and deductions (F-02).** This is the largest functional gap and blocks using the system as a payroll of record.
4. **MWE exemption (F-08) and 13th month (F-09)**, then year-end annualisation (F-16).
5. **Immutable payroll versions, audit events and a reopen workflow (F-12)**, followed by payroll register and journal entries (F-19).
6. **Final pay/separation (F-10)** and the remaining medium/low items (F-11, F-13–F-15, F-17, F-18, F-20–F-24).
7. **Tests:** the Go scenarios missing after the first pass are now in place (section 7). Still open: fixing F-25 and F-26, settling the new-hire proration policy (section 10), and a PHP feature test that runs the engine against a seeded fixture company and asserts register totals.

---

## 10. Professional confirmation required

The following matters could not be settled from official primary sources during this audit and must be confirmed by a currently licensed Philippine CPA, tax practitioner or labour-law specialist before the system is relied upon:

1. PhilHealth premium rate, floor and ceiling applicable to payroll dates in **2026** (no 2026 issuance located; RA 11223's schedule stops at 2025).
2. The exact wording and maximum fund salary in **HDMF Circular No. 460** (the source PDF is behind a reCAPTCHA; ₱10,000 is used on secondary evidence).
3. Whether the company's SSS/PhilHealth/HDMF contribution base should include salary-related allowances, and how unpaid leave and partial months are treated for each agency.
4. Regional minimum wage and MWE coverage for each company location (no wage-order data is loaded).
5. The correct treatment of the company's specific separation, leave-conversion and retirement policies, and whether any CBA or company practice exceeds the statutory minimums audited here.
6. Whether a look-back correction and voluntary remittance are required for the under-contributions arising from F-06/F-07 and the underpayments from F-01/F-03/F-05, and from which payroll period.
7. How basic pay is prorated for a mid-period hire: monthly-paid staff currently receive the full period basic, and the application defines no proration basis (calendar days, working days or the payroll group divisor). Pinned by `TestNewHireProrationIsUnresolvedPolicy`.

> **2026 update.** Section 16 extends this list with the 2026 statutory, wage-order, holiday, remittance and data-quality confirmations. Read Section 16 together with this section. Section 14 records the new findings (F-27 to F-37) raised by the full-cycle review.

---

## 11. 2026 statutory basis verification

**Verification date:** 10 November 2026. **Method:** official agency sites and statute repositories were retrieved directly with `curl`; `web_fetch` could not complete TLS negotiation to the same hosts during this review. A value below is either taken from a primary issuance retrieved during this review, or explicitly marked `NEEDS PROFESSIONAL CONFIRMATION`. No rate was inferred from engine output.

### 11.1 Agency-by-agency position

| # | Item | Legal basis | 2026 position | Source retrieved | What the application applies | Status |
| --- | --- | --- | --- | --- | --- | --- |
| 1 | SSS contributions | RA 11199; SSS Circular No. 2024-006 | 15% total (5% employee, 10% employer); MSC ₱5,000–₱35,000 in ₱500 steps; WISP/MPF on the ₱20,000–₱35,000 excess; EC ₱10 up to MSC ₱14,500 and ₱30 from ₱15,000 | The official SSS contribution-table page is headed **Effective January 2025** and describes the 2021–2025 schedule. In the retrieved 2026 SSS circular index, no contribution-schedule circular appeared (the visible entries 2026-001 to 2026-004 are benefit/disbursement circulars); confirm the full index. | 61 bracket rows effective 2026-01-01 (provisional); the 2025 rows are closed. | Latest published schedule; confirm no 2026 issuance. |
| 2 | PhilHealth premium | RA 11223 (Universal Health Care Act), Sec. 10 | 5% premium; floor ₱10,000; ceiling ₱100,000; split 50/50 | The PhilHealth 2026 circular index (2026-0001 to 2026-0018) contains coverage, benefit, DRG and quality circulars — **no premium-rate circular**. The 2025 and 2024 indexes also show no rate circular in the retrieved list. | One 2026-01-01 provisional row at 5% with the ₱10,000 floor and ₱100,000 ceiling; the 2024 row is closed. | **NEEDS PROFESSIONAL CONFIRMATION** for 2026. |
| 3 | Pag-IBIG/HDMF | RA 9679, Sec. 7; HDMF Circular No. 460 (MFS increase effective February 2024) | 2% employee and employer; 1% employee below ₱1,500; maximum fund salary ₱10,000 (₱200 cap per side) | `pagibigfund.gov.ph` returns a reCAPTCHA interstitial to automated clients. No 2026 issuance was retrieved. | One 2026-01-01 provisional row; the 2024 row is closed. | **NEEDS PROFESSIONAL CONFIRMATION** for 2026. |
| 4 | BIR withholding tax on compensation | RA 10963 (TRAIN), Sec. 5 (amending NIRC Sec. 24); BIR RR 11-2018, Annex E/F | Annual table effective 1 January 2023 onwards: 0% up to ₱250,000; 15% over ₱250,000–₱400,000; 20% over ₱400,000–₱800,000; 25% over ₱800,000–₱2,000,000; 30% over ₱2,000,000–₱8,000,000; 35% above ₱8,000,000. Semi-monthly and monthly tables are the prescribed divisors. | `lawphil.net` RA 10963 text retrieved and confirms the 2023-onwards schedule; the BIR RR 11-2018 PDF (47 pages) was retrieved and confirms the semi-monthly/monthly mechanics. The BIR withholding page and Revenue Regulations list are client-rendered and could not be read as text; no 2026 compensation-withholding RR was retrieved. | 12 provisional rows effective 2026-01-01 (6 semi-monthly, 6 monthly); the 2023 rows are closed. | Statutory basis verified; confirm no 2026 RR. |
| 5 | 13th-month pay | PD 851 as amended by Memorandum Order No. 28 (1986) | 1/12 of basic salary earned in the calendar year; paid not later than 24 December | PD 851 text retrieved from `lawphil.net`; it states the 24 December deadline and the 1/12 definition. | `rules.ThirteenthMonth`; `payroll:special {group} thirteenth_month`; default payroll date 24 December. | Implemented; F-26 open for final-pay proration. |
| 6 | 13th-month and other benefits exclusion | RA 10963, Sec. 9, amending NIRC Sec. 32(B)(7)(e) | Aggregate exclusion of ₱90,000 for 13th-month pay and other benefits | RA 10963 text retrieved from `lawphil.net`; the ₱90,000 aggregate exclusion is explicit. | `ThirteenthMonthExemption = 9,000,000` centavos; `incrementalBenefitExcess` aggregates other benefits. | Implemented. |
| 7 | De minimis benefits | BIR RR 2-98 as amended by RR 11-2018 | Rice ₱2,000/month (or one 50-kg sack); uniform/clothing **₱6,000 per annum**; medical cash allowance **₱1,500 per semester or ₱250/month**; laundry ₱300/month; meal allowance for overtime/night shift **25% of the basic hourly rate**; medical assistance subject to its own annual ceiling | RR 11-2018 PDF retrieved; the text explicitly states the rice, clothing and medical-cash ceilings and leaves (f)–(j) as `xxx` in the retrieved copy. | 6 provisional 2026-01-01 rows with the corrected bases; the 2018 rows are closed | **Bases differ** (F-37); confirm 2026 ceilings. |
| 8 | Minimum wage | RA 6727; NWPC/RTWPB wage orders | NCR Wage Order No. NCR-28 effective 26 September 2026: ₱718.00–₱755.00, with non-agriculture at ₱755.00/day. NCR-27 (₱743.00–₱780.00 in tranches) is subject to continuing litigation. Other regions have 2026 orders in the NWPC matrix. | NWPC **Latest Wage Orders Matrix 2026–2027 as of 06 November 2026** retrieved, and the NWPC NCR page retrieved. | `statutory_min_wage` has **0 rows**; `minimum_wage_rates` contains 2023 rows (NCR non-agriculture ₱610.00). The loader uses `MAX(wage_start)` for the company, without region or industry. | **NOT 2026-READY** (F-27, F-28). |
| 9 | Final pay and separation | Labor Code Arts. 297–299 (renumbered; formerly Arts. 282–284); company policy | Separation pay of at least one month or one month per year of service for installation of labour-saving devices/redundancy; at least one month or one-half month per year for retrenchment/closure not due to serious losses. Final pay is settled on separation; DOLE guidance expects it within 30 days. | Labor Code text retrieved from `lawphil.net`; the specific separation-pay wording is in the retrieved text. | `ComputeFinalPay`; `payroll:special {group} final_pay`; F-26 open; no 30-day timer or automated follow-up. | Partial (F-26). |
| 10 | Government remittances and BIR 2316 | SSS R-3; PhilHealth RF-1/ER2; Pag-IBIG MCRF; BIR 1601-C, 1604-C, 2316 and alphalist | Agency-prescribed forms with control totals that reconcile to the payroll register | Form mechanics are prescribed by the agencies; no new 2026 form issuance was reviewed. | `payroll:remittance` emits a four-row summary CSV; `payroll:bir-2316` emits a YTD CSV. Neither is the prescribed form. | Manual re-keying required (F-31). |

### 11.2 2026 rate snapshot the engine must reproduce

| Component | 2026 value | Where it comes from |
| --- | --- | --- |
| SSS employee share | 5% of the applicable MSC, plus 5% WISP on the ₱20,000–₱35,000 excess | SSS Circular 2024-006, effective January 2025 |
| SSS employer share | 10% of the applicable MSC, plus 10% WISP, plus EC ₱10/₱30 | SSS Circular 2024-006 |
| SSS MSC range | ₱5,000 to ₱35,000 in ₱500 steps; 61 bands | SSS Circular 2024-006 |
| PhilHealth premium | 5% of monthly basic, floor ₱10,000, ceiling ₱100,000, 50/50 split | RA 11223 (2026 confirmation pending) |
| Pag-IBIG | 2% employee and employer on a maximum fund salary of ₱10,000; 1% employee at or below ₱1,500 | RA 9679 and HDMF Circular 460 (2026 confirmation pending) |
| BIR semi-monthly brackets | 0% to ₱10,416.99; 15% to ₱16,666.99; ₱937.50 + 20% to ₱33,332.99; ₱4,270.70 + 25% to ₱83,332.99; ₱16,770.70 + 30% to ₱333,332.99; ₱91,770.70 + 35% above | RA 10963; BIR RR 11-2018 mechanics |
| BIR monthly brackets | 0% to ₱20,832.99; 15% to ₱33,332.99; ₱1,875.00 + 20% to ₱66,666.99; ₱8,541.80 + 25% to ₱166,666.99; ₱33,541.80 + 30% to ₱666,666.99; ₱183,541.80 + 35% above | RA 10963; BIR RR 11-2018 mechanics |
| 13th-month/other-benefits exclusion | ₱90,000 aggregate per employee per year | RA 10963, Sec. 9 |
| NCR minimum wage | ₱755.00/day non-agriculture; ₱718.00/day agriculture, effective 26 September 2026 | NWPC Wage Order No. NCR-28 |
| De minimis ceilings | Rice ₱2,000/month; clothing ₱6,000/year; medical cash ₱1,500/semester or ₱250/month; laundry ₱300/month; meal 25% of the basic hourly rate for overtime/night work; medical assistance per its annual ceiling | RR 2-98 as amended by RR 11-2018 (2026 confirmation pending; see F-37) |

### 11.3 What the verification found

1. **No 2026 rate rows exist.** SSS, PhilHealth, Pag-IBIG and BIR rows are open-ended (`effective_to = NULL`), so a 2026 payroll silently inherits the 2025/2024/2023 values. This is mechanically convenient but evidentially weak: the run cannot show that the rate was confirmed for 2026 (F-27).
2. **The minimum-wage baseline is not a 2026 baseline.** `statutory_min_wage` is empty and the populated `minimum_wage_rates` table is a 2023 dataset. NCR non-agriculture is stored at ₱610.00 against the ₱755.00 NCR-28 rate (F-28).
3. **The wage-order lookup is not employee-specific.** `loadMinimumWage` selects `MAX(wage_start)` for the company across all regions and industries. A multi-region employer therefore applies the highest wage order to every employee, and a single-region employer still cannot prove the employee's region was used (F-28).
4. **MWE flags are not validated.** Two active employees carry `is_minimum_wage = 1` with monthly basic salaries of ₱40,000 and ₱100,000. The engine grants the ₱100,000 employee a ₱6,633.75 semi-monthly exemption. This is a master-data control failure, not an engine arithmetic failure (F-29).
5. **The 2026 holiday calendar is not loaded.** The `holidays` table contains 2023 rows only (1,682 rows); there are no 2024, 2025 or 2026 rows. Holiday pay and premium classification for 2026 therefore depend entirely on the `day_type` stored on each timesheet (F-33).
6. **Government identifiers are incomplete.** Of 17 active employees, 16 have a blank TIN or the literal value `1`, and 12 are missing SSS, PhilHealth and Pag-IBIG numbers. BIR 2316 and the agency remittances cannot be completed from the current master data (F-32).
7. **2026 runs were processed without attendance.** All four processed 2026 payrolls preview with **no attendance recorded; paid on basic salary**. Company 1 has `auto_absent_no_timesheet = 0`, which makes the engine pay the period basic when a timesheet is missing (F-30).
8. **Remittance and 2316 exports are summaries.** `payroll:remittance` produces four rows (SSS, PhilHealth, Pag-IBIG, BIR) and `payroll:bir-2316` produces a per-employee YTD CSV. Both are useful reconciliation aids, but neither is an agency-prescribed file (F-31).

---

## 12. End-to-end payroll cycle — CPA operating procedure (2026)

This is the procedure a Philippine CPA or payroll reviewer should follow for a regular cut-off run. It starts at the pay period and ends at the evidence pack. Use it with the advisory checklist in section 13. Step 14 covers 13th month, final pay, annualisation and BIR 2316.

### 12.1 Quick reference

| Step | Action | Primary owner | Key output | Critical check |
| --- | --- | --- | --- | --- |
| 1 | Lock the pay period and calendar | Payroll lead | Period, pay date, holiday list | One open run; no overlap |
| 2 | Validate employee master data | HR | Clean employee data | 100% government identifiers; valid MWE flag |
| 3 | Set up compensations and deductions | Payroll lead | Earnings/deductions schedule | Effectivity, tax class, loan balance |
| 4 | Capture timesheets | HR/timekeeper | Timesheet by employee-day | Every employee-day covered |
| 5 | Validate attendance and requests | HR | Approvals and attendance sign-off | No pending request |
| 6 | Load and verify statutory tables | Payroll lead + CPA | Rate tables, wage orders, holidays | 2026 coverage for the payroll date |
| 7 | Create the payroll run | Payroll lead | Payroll record | Correct group, period and headcount |
| 8 | Preview and reconcile | Payroll lead + CPA | Preview and sample recomputation | Gross − deductions = net |
| 9 | Process the payroll | Payroll lead | Payroll register | Status processed; engine version recorded |
| 10 | Review the register and controls | Reviewer | Reconciliation and variance analysis | No unexplained difference |
| 11 | Approve and lock | Company owner | Approval and journal entry | Reviewer is not the preparer |
| 12 | Generate and release payslips | Payroll staff | Payslip and release log | Payslip agrees to register |
| 13 | Remit statutory contributions and taxes | Finance | Agency returns and proof of payment | Control totals reconcile |
| 14 | Close and run special cycles | Payroll lead + CPA | Evidence pack, 13th month, final pay, 2316 | All components complete |

> **2026 dependency.** Steps 2, 6 and 14 depend on the 2026 data fixes in section 14. Until F-27 to F-33 and F-37 are closed or formally accepted with a compensating control, treat the 2026 run as advisory rather than a compliant payroll of record.

> **Segregation of duties.** The person who prepares the timesheets and earnings should not be the only person who processes and approves the run. Where the company is too small to separate all three roles, document the compensating review and have the owner sign the checklist.

### Step 1 — Lock the pay period and calendar

**Objective.** Define exactly what period is being paid and which rules apply.

**Owner.** Payroll lead; HR confirms the attendance period.

**Procedure.**

1. Confirm the company profile, payroll group, frequency (semi-monthly or monthly), cut-off, pay date and period start/end.
2. Confirm the payroll group's `days_year` (261 for Monday–Friday, 313 for Monday–Saturday) and `hours_day` (normally 8).
3. Confirm the holiday calendar and special working days for the period.
4. Confirm there is one open payroll for the group and period; close or cancel duplicates.

**Validation / control.** The pay date must not precede the period end. The period must not overlap another open or processed run for the same group. The group must be marked correctly as default if it is the company's standard group.

**Evidence.** Payroll group record; generated pay dates; holiday list; duplicate-run check.

**App / command.** **Payroll Management → Payroll Group**; **Getting started** readiness page; `payroll_dates` rows.

**Exception.** If the pay date list is empty, save the payroll group again to regenerate the dates, or create the missing `payroll_dates` row through the supported action.

### Step 2 — Validate employee master data

**Objective.** Ensure every employee in the run has complete, internally consistent data before any amount is computed.

**Owner.** HR; payroll lead reviews.

**Procedure.**

1. Confirm the employee is active, has the correct payroll group, payment type (monthly or daily), basic salary and daily rate, and date hired/separated.
2. Confirm the TIN, SSS number, PhilHealth number and Pag-IBIG/HDMF number are present and correctly formatted.
3. Confirm tax status and dependents; confirm the minimum-wage-earner flag only where the employee's salary is at or below the applicable regional minimum wage for the employee's region and industry.
4. Confirm benefit switches (rest day, regular/special holiday, overtime, night differential, de minimis) match the employee's entitlement.
5. Confirm bank account details for net-pay release.

**Validation / control.** 100% completeness for employees in the run. Reject an MWE flag where the salary exceeds the applicable minimum wage. Reject duplicate employee numbers or duplicate active records. Hold statutory reports if a government number is missing.

**Evidence.** Headcount reconciliation; data-quality exception report; corrected employee records.

**App.** **Employees** resource and employee import; **Employees → Related Records** for compensation and government data.

**Exception.** If a government number is genuinely unavailable, record the reason and hold the employee's BIR 2316 and agency remittance line until it is supplied. Do not invent a number.

### Step 3 — Set up compensations and deductions

**Objective.** Put every recurring and period-specific earning and deduction on the employee record with the correct effectivity and tax treatment.

**Owner.** Payroll lead; HR/finance approve entitlements.

**Procedure.**

1. Set up allowances (taxable, non-taxable and de minimis), compensations, bonuses and commissions with effectivity dates and frequency/cut-off.
2. Set up recurring deductions and loans with effectivity, amortisation, balance and cut-off.
3. Classify each earning as taxable, partially non-taxable or fully non-taxable; apply the de minimis ceilings.
4. Confirm that a period-specific bonus or commission is recorded once and in the correct period.

**Validation / control.** Effectivity dates fall within the period (or the recurring row is active for the period). Loan amortisation does not exceed the remaining balance. De minimis amounts above the ceiling are taxable. No duplicate recurring rows. Taxable and non-taxable amounts are separately identifiable.

**Evidence.** Per-employee earnings and deductions schedule; loan amortisation schedule; de minimis computation.

**App.** **Payroll Management → Allowances / Compensations / Deductions / Loans**; employee related records.

**Exception.** Correct the source record and re-run the preview. Do not adjust the payroll detail directly.

### Step 4 — Capture timesheets

**Objective.** Record attendance, leave, absences, overtime and night work for every employee-day in the period.

**Owner.** HR/timekeeper; payroll lead reviews.

**Procedure.**

1. Import the biometric or timesheet file, or generate the period's timesheet for the office/employees.
2. Review each day: day type, regular hours, overtime hours, night hours, night-overtime hours, absence, paid/unpaid leave and holiday.
3. Correct missing or inconsistent entries through the timesheet action; do not edit payroll details directly.
4. Confirm the night-overtime bucket is populated separately from ordinary overtime and ordinary night hours.

**Validation / control.** Every employee-day in the period has a timesheet row. Day types use the stored codes (`regular`, `rd`, `rh`, `sh`, `rd-rh`, `2rh`, `rd-2rh`, `rh-sh`, `rd-rh-sh`). Overtime and night work are approved. No overlapping leave, overtime and official-business entries.

**Evidence.** Import batch log; timesheet exception report; approved requests.

**App.** **Time and Attendance → Timesheet**: **Import Time Sheet**, **Generate Time Sheet**, and the row **Time** action.

**Exception.** If attendance is genuinely unavailable, do not silently pay the basic salary. Use the Step 8 override control (F-30) and document the reason.

### Step 5 — Validate attendance and requests before the cut-off

**Objective.** Resolve all attendance-affecting requests before the run is processed.

**Owner.** HR; department head approves; payroll lead confirms.

**Procedure.**

1. Review and approve or reject overtime, leave, undertime and official-business requests that touch the period.
2. Reconcile absences against approved leave and holidays.
3. Confirm no request for the period remains pending.
4. Sign off the attendance period.

**Validation / control.** No pending requests for the period. Approved leave matches the timesheet. Absences are supported by the attendance record. Overtime is authorized.

**Evidence.** Requests approval list; attendance sign-off; exception log.

**App.** **Requests** (overtime, leave, undertime, official business).

**Exception.** A late-approved request that changes pay requires a re-preview and re-process before approval.

### Step 6 — Load and verify the statutory tables

**Objective.** Prove that the rates used by the engine are the rates that apply to the payroll date.

**Owner.** Payroll lead; CPA reviewer signs the rate table.

**Procedure.**

1. Verify the 2026 SSS, PhilHealth, Pag-IBIG, BIR and de minimis position against the current issuances (section 11).
2. Run `php artisan payroll:statutory:import` (use `--fresh` only after a backup and only when replacing the whole statutory set).
3. Confirm the effective-date coverage for the payroll date; the engine must fail rather than use an uncovered table.
4. Load the 2026 regional wage orders and the 2026 holiday calendar.
5. Run `php artisan payroll:engine --check`.

**Validation / control.** `statutory_sources` records the effectivity and source for each table. The payroll date falls inside the effective range. The minimum-wage table has a row for the employee's region and industry. The holiday table covers the period. The engine check reports `Payroll engine is ready.`

**Evidence.** Import output; `statutory_sources` export; NWPC wage-order matrix; holiday proclamation; engine check output.

**App / command.** `payroll:statutory:import`; `payroll:engine --check`.

**Exception.** If the payroll date is outside every effective row, do not process. Import the correct schedule or correct the payroll date.

### Step 7 — Create the payroll run

**Objective.** Create one run for the correct group and period.

**Owner.** Payroll lead.

**Procedure.**

1. Open **Payroll Management → Payroll** and select **Create Payroll**.
2. Choose the payroll group and payroll date; confirm the displayed period and pay date.
3. Confirm the headcount and employee list.
4. Save; the run opens in **open** status.

**Validation / control.** The group, period and pay date match Step 1. The headcount reconciles to the expected employee list. No duplicate run exists.

**Evidence.** Payroll record; run ID; headcount reconciliation.

**App.** **Payroll Management → Payroll → Create Payroll**.

**Exception.** If the payroll date list is empty, correct the payroll group's cut-off/pay dates and regenerate.

### Step 8 — Preview and reconcile

**Objective.** Compute a dry run and prove the numbers before saving them.

**Owner.** Payroll lead; CPA reviewer samples.

**Procedure.**

1. Open the run; preview runs automatically for an open, draft or failed payroll.
2. Review the summary cards: employees, gross pay, deductions and net pay.
3. Read every warning; distinguish data gaps from policy outcomes.
4. Review the register row by row: gross, SSS, PhilHealth, Pag-IBIG, tax, total deductions and net pay.
5. Independently recompute a sample (at least the highest-paid employee, a daily-paid employee, an MWE and an employee with a loan) and compare.
6. Investigate zero pay, negative net, unusual gross, missing attendance and employees with no statutory deductions.

**Validation / control.** Gross equals the sum of earnings. Total deductions equals statutory contributions plus withholding tax plus loans and other deductions. Net equals gross minus total deductions. Employer contributions reconcile separately. Headcount and totals reconcile to the employee list.

**Evidence.** Preview output; sample recomputation; exception log; reviewer sign-off.

**App / command.** Payroll run page; `php artisan payroll:preview {payroll}`.

**Exception.** Correct the source data and refresh the preview. Preview is safe to repeat and does not create payslips.

### Step 9 — Process the payroll

**Objective.** Write the computed details to the payroll register.

**Owner.** Payroll lead.

**Procedure.**

1. Select **Process payroll** and confirm.
2. Wait for the status to move from **running** to **processed**; keep the queue worker running for large runs.
3. Read any failure reason and correct the source data before retrying.
4. Confirm the engine version and the input hash are recorded.

**Validation / control.** Only an authorized user processes. Re-processing replaces the details but preserves the prior revision. A processed run is not yet approved.

**Evidence.** Processing log; `payroll_audit_events`; engine version; processed timestamp.

**App / command.** **Process payroll**; `ProcessPayrollJob`; `payroll:engine --check` for readiness.

**Exception.** If processing fails, the status becomes **failed**. Do not approve; fix the data and process again.

### Step 10 — Review the register and controls

**Objective.** Perform the maker-checker review before approval.

**Owner.** Reviewer (not the preparer); CPA reviewer for the sample.

**Procedure.**

1. Reconcile the processed register to the preview; explain every difference.
2. Reconcile gross, statutory deductions, tax, loans and net pay to the independent sample.
3. Review the employer contributions and the journal entry that will post on approval (salaries expense, employer contributions expense, withholding tax payable, SSS/PhilHealth/Pag-IBIG payable, loans/other deductions payable and net pay payable).
4. Compare headcount and totals with the prior period; explain material variances.
5. Confirm funding for net pay and remittances.
6. Complete the section 13 checklist.

**Validation / control.** No unexplained difference. No employee with a negative net pay or a zero net pay without a documented reason. Statutory totals agree to the agency reconciliation. The reviewer signs the checklist.

**Evidence.** Payroll register CSV; reconciliation worksheet; variance analysis; signed checklist; funding confirmation.

**App / command.** `php artisan payroll:register {payroll} --path=register.csv`; `php artisan payroll:remittance {payroll} --path=remittance.csv`.

**Exception.** If a figure is wrong, return to the source data and re-process. Do not approve and then adjust.

### Step 11 — Approve and lock

**Objective.** Lock the reviewed figures and post the payroll liability.

**Owner.** Company owner or delegated approver; not the preparer.

**Procedure.**

1. Confirm the status is **processed**.
2. In the **Review and segregation of duties** panel, enter a review note and select **Mark reviewed**.
3. Select **Approve & lock** and confirm.
4. If the run has no reviewer, or you processed it yourself, enter an **approval override reason** when prompted.
5. Confirm the journal entry posts and the audit event records the approver, engine version and totals.
6. Retain the approval evidence.

**Validation / control.** Approval happens only after the Step 10 review. An approval without an independent review, or by the processor, requires a written override reason and records an `approval_segregation_override` event. The run is locked. Reopening requires a company owner and a written reason; the reason is recorded and the journal is reversed.

**Evidence.** Review audit event; approval audit event or segregation override; journal entry; lock status; reopen reason if applicable.

**App.** **Mark reviewed**; **Approve & lock**; **Reopen** (owner only).

**Exception.** If a correction is needed after approval, reopen with a reason, correct the source data, re-process, re-review and re-approve.

### Step 12 — Generate and release payslips

**Objective.** Give each employee an accurate, itemized payslip and retain proof of release.

**Owner.** Payroll staff; HR distributes.

**Procedure.**

1. Open the payroll register and select **View** in the **Payslip** column.
2. Confirm the payslip agrees to the register: name, employee number, position, pay date, period, earnings, deductions, net pay, employer share and taxable income.
3. Print the page or download the PDF; confirm the PDF shows the TIN, SSS, PhilHealth and Pag-IBIG numbers.
4. Release through the approved channel (print, email or portal) and record the release.
5. Retain a copy for the employee's payroll file.

**Validation / control.** Payslip equals register. Zero-value lines are hidden. Government numbers are present on the PDF. Access is restricted to the employee or an authorized administrator. No payslip is released before approval (or before processing, if company policy permits an advance payslip).

**Evidence.** Sample payslip; release log; employee acknowledgment; retained copy.

**App.** Payroll register → **View**; **Print**; **Download PDF** (`payslip.show`).

**Exception.** If the PDF template omits a mandatory item, correct the template before release; the web view currently omits government numbers even though the PDF includes them (F-35).

### Step 13 — Remit statutory contributions and taxes

**Objective.** Pay each agency the correct amount by the due date and reconcile the payment to the payroll liability.

**Owner.** Finance/treasury; payroll lead reconciles; CPA reviews.

**Procedure.**

1. Generate the remittance reconciliation: `php artisan payroll:remittance {payroll} --path=remittance.csv`.
2. Prepare the agency returns: SSS R-3; PhilHealth RF-1/ER2; Pag-IBIG MCRF; BIR 1601-C (monthly) and 1604-C/annual alphalist as applicable.
3. Pay through the approved channel and retain the proof of payment.
4. Reconcile each agency's control total to the payroll register and the general ledger payable.
5. Investigate and clear every difference before the next run.

**Validation / control.** Remittance total equals the payroll liability. The employee and employer shares are separately supported. Due dates are met. Proof of payment is retained. Differences are documented and cleared.

**Evidence.** Remittance CSV; agency forms; bank confirmations; reconciliation worksheet; general ledger extract.

**App / command.** `payroll:remittance`; agency e-filing portals.

**Exception.** The current exports are summary CSVs, not agency-prescribed files; re-keying is a control risk (F-31). Until an adapter exists, have a second person verify the re-keyed control totals.

### Step 14 — Close the period and run the special cycles

**Objective.** Finish the regular cycle and complete 13th month, final pay, annualisation and BIR 2316.

**Owner.** Payroll lead; CPA reviewer; company owner approves special runs.

**Procedure.**

1. **13th month.** Run `php artisan payroll:special {group} thirteenth_month --date=YYYY-12-24 --process`. Confirm 1/12 of basic salary earned in the calendar year, aggregate the ₱90,000 exclusion with other benefits, and pay not later than 24 December.
2. **Final pay.** Run `php artisan payroll:special {group} final_pay --separation-reason=... --process`. Confirm final period pay, pro-rated 13th month, leave conversion and separation pay. Pay within the company's documented final-pay period (DOLE guidance: 30 days).
3. **Annual adjustment.** Run `php artisan payroll:annualise {group} --year=YYYY --process`. Confirm the year-end true-up reconciles to the YTD ledger.
4. **BIR 2316.** Run `php artisan payroll:bir-2316 {group} --year=YYYY --path=bir-2316.csv`. Use it to prepare the prescribed certificate and alphalist; confirm every employee's TIN and government numbers are valid.
5. **Close.** Back up the database, export the register, remittance reconciliation and 2316 data, and retain the evidence pack. Reconcile the payroll payable accounts to zero (or the remaining unpaid liability).

**Validation / control.** 13th month is paid by 24 December and the ₱90,000 exclusion is aggregated across all benefits. Final pay includes every component and is paid within policy. Annualisation agrees to YTD. BIR 2316 agrees to YTD and the register. The evidence pack is complete and retained.

**Evidence.** Special payroll register; 13th-month computation; final-pay computation; separation reason and years of service; annualisation register; BIR 2316 CSV and prescribed form; evidence pack index; backup confirmation.

**App / command.** `payroll:special`; `payroll:annualise`; `payroll:bir-2316`; `payroll:register`; `payroll:remittance`.

**Exception.** If the YTD ledger is incomplete, do not annualise until the missing runs are processed; document the reconstruction.

---

## 13. Advisory validation checklist (2026)

Use this checklist for every regular run and for each special run. It is **advisory**: it does not block processing. A hard gate is not configured (the user selected advisory-only enforcement). File the completed checklist with the payroll evidence pack.

### 13.1 Period and master data

- [ ] Payroll group, frequency, cut-off, pay date and period start/end are correct.
- [ ] `days_year` and `hours_day` match the company's workweek.
- [ ] Holiday calendar and special working days for the period are loaded.
- [ ] One open run exists for the group and period; no duplicate.
- [ ] Every expected employee is active, in the correct group, with the correct payment type.
- [ ] Basic salary and daily rate are correct and supported by an approved personnel action.
- [ ] TIN, SSS, PhilHealth and Pag-IBIG numbers are present and valid.
- [ ] Tax status and dependents are correct.
- [ ] MWE flag is supported by the employee's region, industry and salary.
- [ ] Benefit switches match the employee's entitlement.
- [ ] Bank details are present for net-pay release.

### 13.2 Earnings and deductions

- [ ] Allowances, compensations, bonuses and commissions have the correct effectivity and frequency.
- [ ] Taxable, partially non-taxable and non-taxable amounts are correctly classified.
- [ ] De minimis amounts above the ceiling are taxed.
- [ ] Recurring deductions and loans have the correct effectivity and amortisation.
- [ ] Loan balances are not exceeded.
- [ ] No duplicate recurring earning or deduction rows.
- [ ] Period-specific items are recorded once and in the correct period.

### 13.3 Attendance

- [ ] Every employee-day in the period has a timesheet row.
- [ ] Day types use valid stored codes and match the holiday/rest-day calendar.
- [ ] Regular, overtime, night and night-overtime hours are separately and correctly recorded.
- [ ] Absences are supported by attendance and are not also approved leave.
- [ ] Paid and unpaid leave are correctly classified.
- [ ] Overtime, undertime and official-business requests for the period are approved.
- [ ] No request for the period remains pending.
- [ ] Attendance period is signed off by HR.

### 13.4 Statutory tables and engine

- [ ] 2026 SSS, PhilHealth, Pag-IBIG, BIR and de minimis positions are verified and signed off.
- [ ] `payroll:statutory:import` has been run and the effective dates cover the payroll date.
- [ ] 2026 regional wage orders are loaded for each employee's region and industry.
- [ ] 2026 holiday calendar is loaded.
- [ ] `php artisan payroll:engine --check` reports ready.
- [ ] No payroll date falls outside an effective statutory row.

### 13.5 Preview and reconciliation

- [ ] Preview completed without an engine error.
- [ ] Headcount and totals reconcile to the employee list.
- [ ] Gross equals the sum of earnings.
- [ ] Total deductions equals statutory contributions + withholding tax + loans/other deductions.
- [ ] Net equals gross minus total deductions.
- [ ] Employer contributions reconcile separately.
- [ ] At least four employees were independently recomputed (highest paid, daily paid, MWE, with a loan).
- [ ] Zero pay, negative net, unusual gross and missing attendance are investigated and explained.
- [ ] Every warning is read and either resolved or documented.
- [ ] No employee is paid without attendance unless an explicit override and reason are recorded.

### 13.6 Review, approval and payslips

- [ ] Processed register reconciles to the preview; every difference is explained.
- [ ] Prior-period variance analysis is complete and material variances are explained.
- [ ] Journal entry that will post on approval is correct and balances.
- [ ] Net-pay funding is confirmed.
- [ ] Reviewer is not the preparer (or a documented compensating review exists).
- [ ] Approval is recorded with approver, timestamp, engine version and totals.
- [ ] Payslips agree to the register.
- [ ] Payslip PDFs include TIN, SSS, PhilHealth and Pag-IBIG numbers.
- [ ] Payslip release is logged and retained.

### 13.7 Remittances and close

- [ ] Remittance reconciliation agrees to the payroll register.
- [ ] SSS R-3, PhilHealth RF-1/ER2, Pag-IBIG MCRF, BIR 1601-C/1604-C are prepared.
- [ ] Payments are made by the due date; proof of payment is retained.
- [ ] General ledger payables reconcile to the remittances.
- [ ] Differences are documented and cleared.
- [ ] 13th month is paid by 24 December and the ₱90,000 exclusion is aggregated.
- [ ] Final pay is complete and paid within policy.
- [ ] Annualisation agrees to YTD.
- [ ] BIR 2316 agrees to YTD and the register.
- [ ] Evidence pack is complete and retained.

### 13.8 Sign-off

| Role | Name | Position | Date | Signature |
| --- | --- | --- | --- | --- |
| Prepared by (payroll staff) | | | | |
| Reviewed by (finance/HR reviewer) | | | | |
| Approved by (company owner / authorized approver) | | | | |
| CPA reviewer (advisory) | | | | |

> **Advisory only.** This checklist does not enforce a hard block. Findings that require correction must be resolved or formally accepted with a compensating control before the run is relied upon.

---

## 14. 2026 findings refresh and recommended fixes

This section originally recorded the open findings. The **10 November 2026 remediation** implements F-25 to F-37; Appendix F records the implementation and verification. The tables below preserve the pre-fix observations and show the fixed status.

### 14.1 Re-test of the original findings

| Finding | Test | Pre-fix observed | Expected | Status | Conclusion |
| --- | --- | --- | --- | --- | --- |
| F-25 — daily-paid MWE exemption | `TestMinimumWageExemptionCoversTheWholePeriod` (now passing) | Exemption ₱856.00; taxable ₱5,136.00 | Exemption ₱6,616.00; taxable ₱0.00 | **Fixed** | The test passes unskipped; the engine now covers the whole period. |
| F-26 — final-pay 13th-month proration | `TestFinalPayProratesThirteenthMonthOnEarnedBasic` (now passing) | 13th month ₱13,087.50 | ₱12,500.00 | **Fixed** | The test passes unskipped; the engine uses `BasicEarned`. |
| New-hire proration policy | `TestNewHireProrationIsUnresolvedPolicy` (skipped) | Full period basic ₱13,050.00 | Policy undefined | **Open policy** | A monthly-paid hire on 2026-01-16 with 10 attended days receives the full period basic. |

### 14.2 New 2026 findings

#### F-27 — 2026 statutory basis is not explicitly effective-dated (HIGH — open)

**Legal basis.** RA 11199 (SSS), RA 11223 (PhilHealth), RA 9679 (Pag-IBIG), RA 10963 (BIR).

**Description.** The statutory rows that apply to a 2026 payroll are open-ended rows effective 2025-01-01 (SSS), 2024-01-01 (PhilHealth), 2024-02-01 (Pag-IBIG) and 2023-01-01 (BIR). No 2026-effective row exists for any table. The engine therefore applies the prior year's values to 2026 without an explicit 2026 confirmation. The 2026 PhilHealth circular index and the 2026 SSS circular index contain no rate-setting circular in the retrieved lists; the BIR Revenue Regulations list is client-rendered and no 2026 compensation-withholding RR was retrieved.

**Evidence.** `statutory_sources` and the four statutory tables (section 15.3); the retrieved PhilHealth 2026 circular index; the SSS contribution-table page headed **Effective January 2025**.

**Impact.** A stale or superseded rate can be applied silently. The run cannot demonstrate that the rate was confirmed for 2026, which weakens the audit trail and may misstate employee and employer contributions.

**Recommended fix.** Add explicit 2026-effective rows (or a formally documented carry-forward row) for each statutory table, with the 2026 source and effectivity. Add a `payroll:statutory:verify {date}` command that resolves the applicable row, requires a `statutory_sources` record whose confirmation covers that year, reports the source/effectivity/confirmation status, and fails or warns when the rate is not confirmed for the payroll year.

**Acceptance test.** A 2026 payroll date resolves to a row whose 2026 confirmation is recorded, and the verification command fails when the confirmation is removed.

#### F-28 — Minimum-wage lookup is stale and not employee-specific (HIGH — open)

**Legal basis.** RA 6727; NWPC/RTWPB wage orders, including NCR-28 effective 26 September 2026.

**Description.** `statutory_min_wage` is empty. The fallback `minimum_wage_rates` table contains 2023 data, including NCR non-agriculture at ₱610.00/day. The loader selects `MAX(wage_start)` for the company across every region and industry, so a multi-region employer applies one (the highest) rate to all employees. The 2026 NCR non-agriculture rate is ₱755.00/day under NCR-28.

**Evidence.** `statutory_min_wage` has 0 rows; `minimum_wage_rates` has NCR non-agriculture 61,000 centavos (₱610.00); the NWPC matrix as of 6 November 2026 and the NWPC NCR page show NCR-28 at ₱718.00–₱755.00 effective 26 September 2026. The loader is `payroll-engine/internal/db/load.go` (`loadMinimumWage`).

**Impact.** The MWE exemption and the below-minimum-wage warning are understated by ₱145.00 per day for an NCR non-agriculture employee. For a semi-monthly monthly-paid MWE, the exemption is ₱6,633.75 at the stale ₱610.00 rate versus ₱8,210.63 at ₱755.00 — an under-exemption of ₱1,576.88 per cut-off. Multi-region employers may apply the wrong region's rate entirely.

**Recommended fix.** Store the employee's region and industry (or derive them from the office), load the NWPC matrix as effective-dated rows, and resolve the applicable wage order by region + industry + payroll date. Fail loudly when no matching order exists. Keep the 2026 rate and its wage-order number in `statutory_sources`.

**Acceptance test.** An NCR non-agriculture employee at ₱755.00 receives the full period exemption; a Region X employee uses the Region X rate; an employee with no matching region/industry produces a blocking error.

#### F-29 — MWE flag is not validated against salary (HIGH — open)

**Legal basis.** RA 9504 and RA 10963 (minimum-wage-earner exemption).

**Description.** The engine trusts `employees.is_minimum_wage`. Two active employees carry the flag with monthly basic salaries of ₱40,000 and ₱100,000. The ₱100,000 employee receives a ₱6,633.75 semi-monthly MWE exemption, reducing taxable income and withholding tax.

**Evidence.** Read-only query: 17 active employees, 2 flagged MWE, salaries ₱40,000 and ₱100,000. Payroll 51, employee row id 2: monthly ₱100,000, gross ₱51,120.00, taxable ₱41,261.25, MWE exemption ₱6,633.75, tax ₱6,252.76.

**Impact.** Under-withholding and an incorrect BIR 2316. The employee may also be incorrectly excluded from statutory minimum-wage adjustments.

**Recommended fix.** Validate the flag when the employee record is saved and again when the payroll is processed: the basic salary (or daily rate) must be at or below the applicable regional minimum wage for the employee's region and industry. Warn or block; add a data-quality report that lists every MWE flag with the supporting wage order. Require HR to correct the flag in the source record.

**Acceptance test.** A ₱100,000 employee cannot carry the MWE flag (or receives zero exemption), and the data-quality report shows the wage order that supports each valid flag.

#### F-30 — Payrolls can be processed without attendance (MEDIUM — open)

**Legal basis.** Labor Code wage and attendance rules; payroll internal control.

**Description.** `PayWithoutTimesheet` defaults to `true` unless the `auto_absent_no_timesheet` setting is truthy. Company 1 has `auto_absent_no_timesheet = 0`, so a missing timesheet produces "no attendance recorded; paid on basic salary." All four processed 2026 payrolls preview with that warning for every employee.

**Evidence.** Section 15.3 (2026 previews); `payroll-engine/internal/db/load.go` default; `settings` row for company 1.

**Impact.** Employees can be paid the full period basic with no attendance evidence. This can overpay absences or underpay overtime and premiums, and it weakens the audit trail.

**Recommended fix.** When a run contains employees with no timesheet for the period, require an explicit override with a reason, recorded in `payroll_audit_events` and surfaced on the run. Add an exception report of employees paid without attendance. Keep the current policy available, but make it a deliberate, evidenced decision rather than a silent default.

**Acceptance test.** Processing a no-attendance run requires a reason; the reason and actor are recorded; the register flags the employees.

#### F-31 — Remittance and BIR 2316 outputs are not agency-prescribed (MEDIUM — open)

**Legal basis.** SSS R-3; PhilHealth RF-1/ER2; Pag-IBIG MCRF; BIR 1601-C, 1604-C, 2316 and alphalist.

**Description.** `payroll:remittance` produces a four-row summary (SSS, PhilHealth, Pag-IBIG, BIR) and `payroll:bir-2316` produces a per-employee YTD CSV. Neither is the prescribed agency file or form. The monthly and annual returns require employee-level detail, control totals and, for BIR, the alphalist format.

**Evidence.** Section 15.1 command outputs; command source in `app/Console/Commands/PayrollRemittance.php` and `PayrollBir2316.php`.

**Impact.** Manual re-keying, transcription errors and a reconciliation gap between the payroll liability and the amount remitted. The evidence pack cannot be tied to the filed returns without extra work.

**Recommended fix.** Add export adapters per agency (or a documented, versioned mapping) that produce the required employee-level detail and control totals. Validate the control total against the payroll register before export. Retain the generated file, the filing acknowledgment and the proof of payment.

**Acceptance test.** Each agency file reconciles to the payroll register and the general ledger payable, and the control totals agree to the centavo.

#### F-32 — Government identifiers are incomplete or invalid (MEDIUM — open)

**Legal basis.** BIR 2316 and agency registration requirements.

**Description.** Of 17 active employees, 16 have a blank TIN or the literal value `1`, and 12 are missing SSS, PhilHealth and Pag-IBIG numbers. The 2026 BIR 2316 CSV contains rows with a blank TIN and a TIN of `1`.

**Evidence.** Section 15.3 read-only query; `payroll:bir-2316 1 --year=2026` output (no personal data reproduced in this report).

**Impact.** BIR 2316 and the agency remittances cannot be filed accurately. A TIN of `1` will be rejected or mis-matched.

**Recommended fix.** Make the TIN, SSS, PhilHealth and Pag-IBIG numbers mandatory for employees in a payroll run; validate format and check digits where applicable; add a data-quality dashboard and a blocking exception on the BIR 2316 export. Allow a documented "pending" status with a follow-up date, but do not export an invalid value.

**Acceptance test.** An employee with a blank or invalid TIN cannot be included in a BIR 2316 export without a documented exception; the data-quality report shows zero invalid identifiers for employees in the run.

#### F-33 — 2026 holiday calendar is not loaded (MEDIUM — open)

**Legal basis.** Labor Code Art. 94 (holiday pay) and the annual holiday proclamation.

**Description.** The `holidays` table contains 2023 rows only (1,682 rows); there are no 2024, 2025 or 2026 rows. Holiday pay and premium classification for 2026 therefore depend entirely on the `day_type` stored on each timesheet, which is not independently checked against the official calendar.

**Evidence.** Section 15.3 read-only query.

**Impact.** A missed or misclassified holiday under- or over-pays employees, and the payroll cannot demonstrate that the official 2026 holiday list was applied.

**Recommended fix.** Load the 2026 holiday proclamation (and local holidays) with dates and day types; add a readiness check that every payroll period has the applicable holidays; and validate timesheet day types against the calendar before processing.

**Acceptance test.** Every 2026 payroll period has a complete holiday calendar, and a timesheet with a holiday day type that is not in the calendar produces a warning.

#### F-34 — Maker-checker is not enforced (MEDIUM — open)

**Legal basis.** Internal control and payroll audit standards; company policy.

**Description.** The run records `approved_by` and `approved_at`, but any authorized user can process and approve the same run. There is no reviewer field and no separation between preparation, processing and approval. Reopening is owner-only and requires a reason, which is a good control, but it does not replace day-to-day segregation.

**Evidence.** `PayrollRun` (`process`, `approve`, `reopen`); `payrolls.approved_by`.

**Impact.** A single user can prepare, process and approve a payroll without independent review. This increases the risk of error and fraud.

**Recommended fix.** Add a reviewer/approver role gate and record the reviewer in the audit event. Enforce that the approver is not the processor (or require a documented compensating approval by the owner). Use the section 13 checklist as the evidence of review.

**Acceptance test.** The approval audit event shows a different user from the processor, or a documented owner exception.

#### F-35 — Payslip web view omits government identifiers (LOW — open)

**Legal basis.** Employee transparency and BIR/agency reporting practice.

**Description.** The on-screen payslip shows the employee name, employee number, position, pay date, period, earnings, deductions, net pay, employer share, taxable income and payroll number. The PDF template also shows the TIN, SSS, PhilHealth and Pag-IBIG numbers; the web view does not.

**Evidence.** `resources/views/livewire/admin/payroll/payslip.blade.php`; `app/Gateways/PayslipGateway.php`.

**Impact.** An employee reviewing the on-screen payslip cannot verify the government numbers used for contributions; the two outputs are inconsistent.

**Recommended fix.** Add the government identifiers (masked as required by privacy policy) and the employer name/address to the web view, and add a "prepared by" line. Keep the PDF and web output consistent.

**Acceptance test.** The web view and PDF disclose the same mandatory items.

#### F-36 — No documented retention and evidence-pack policy (LOW — open)

**Legal basis.** BIR record-retention rules and labor-record retention practice; company policy.

**Description.** The application can export a register, remittance summary and 2316 CSV, but there is no defined retention period, evidence-pack contents or close checklist. Records are stored in the database and the filesystem without an explicit retention rule.

**Evidence.** Section 12, Step 14; command set.

**Impact.** Evidence may be incomplete or deleted before the retention period expires; an audit or agency inspection may not be supportable.

**Recommended fix.** Define the retention period with the company's CPA/tax adviser, specify the evidence-pack contents (register, payslips, remittance reconciliation, agency forms, approvals, journal entries, 2316), and add a period-close export bundle. Record the retention rule in this document and in the company's payroll policy.

**Acceptance test.** Closing a period produces a complete, indexed evidence pack that can be retrieved for the retention period.

#### F-37 — De minimis ceilings do not match RR 11-2018 (MEDIUM — open)

**Legal basis.** BIR RR 11-2018, amending RR 2-98 (de minimis benefits).

**Description.** `config/payroll.php` stores a flat monthly catalogue that does not match the statutory bases for every benefit:

| Benefit | RR 11-2018 / RR 2-98 basis | Application ceiling | Effect |
| --- | --- | --- | --- |
| Rice subsidy | ₱2,000/month or one 50-kg sack | ₱2,000/month | Matches. |
| Uniform and clothing | ₱6,000 per annum | ₱250/month (₱3,000/year) | Under-exempts by ₱3,000/year; over-taxes. |
| Medical cash allowance to dependents | ₱1,500 per semester or ₱250/month | ₱300/month | Over-exempts by ₱50/month; under-taxes. |
| Laundry | ₱300/month | ₱300/month | Matches. |
| Meal allowance (overtime/night shift) | 25% of the basic hourly rate | ₱500/month flat | Wrong basis; may over- or under-exempt. |
| Medical assistance | Annual ceiling under RR 2-98 | ₱1,500 (flat) | Wrong basis; likely under-exempts. |

**Evidence.** `config/payroll.php` (`statutory.de_minimis.benefits`); BIR RR 11-2018 PDF, p. 22 (retrieved) — rice ₱2,000, clothing ₱6,000 per annum, medical cash ₱1,500 per semester or ₱250 per month.

**Impact.** Taxable income and withholding tax are misstated for employees receiving these benefits. The BIR 2316 and the alphalist inherit the error.

**Recommended fix.** Replace the flat monthly catalogue with the RR 11-2018 ceilings and bases; support annual and per-semester limits and the 25%-of-hourly-rate meal rule; keep the de minimis switch; add tests for each benefit at, below and above the ceiling.

**Acceptance test.** A ₱6,000/year clothing allowance is fully exempt; a medical cash allowance above ₱250/month (or ₱1,500/semester) is taxable; an overtime meal allowance is tested against 25% of the basic hourly rate; the rice and laundry ceilings remain unchanged.

### 14.3 Compliance matrix addendum (2026)

The section 4 matrix is unchanged for F-01 to F-26. The following rows supplement it:

| Area | 2026 assessment | Finding |
| --- | --- | --- |
| 2026 statutory rate basis | Prior-year rows apply to 2026 without a 2026 confirmation | F-27 |
| Regional minimum wage | Stale 2023 data; region/industry not used | F-28 |
| MWE classification | Flag not validated; high earners receive exemption | F-29 |
| Attendance evidence | Runs can be paid on basic with no timesheets | F-30 |
| Remittance and BIR 2316 forms | Summary CSVs, not agency forms | F-31 |
| Government identifiers | 16/17 blank or `1` TIN; 12/17 missing agency numbers | F-32 |
| 2026 holiday calendar | 2023 only; 2024–2026 missing | F-33 |
| Maker-checker | No reviewer field or separation of duties | F-34 |
| Payslip disclosures | Web view omits government identifiers | F-35 |
| Retention and evidence pack | No documented retention rule | F-36 |
| De minimis ceilings | Flat monthly catalogue differs from RR 11-2018 bases | F-37 |

### 14.4 Recommended 2026 remediation order

1. **Before the next 2026 live run:** close F-29 (MWE flag), F-32 (government identifiers), F-33 (2026 holidays) and F-30 (attendance override). These are data and control fixes that do not require an engine redesign.
2. **Before relying on the 2026 contributions and taxable income:** close F-28 (regional minimum wage), F-27 (2026 rate confirmation) and F-37 (de minimis ceilings). Each requires a rate/table or configuration change and a CPA sign-off.
3. **Before the next agency filing:** close F-31 (remittance/2316 adapters) or document a manual re-keying control with a second-person verification.
4. **Next control cycle:** F-34 (maker-checker), F-35 (payslip disclosures) and F-36 (retention/evidence pack).
5. **Continue the existing queue:** F-25 and F-26, plus the new-hire proration policy.
6. **Re-run** the Go suite, the independent oracle and the targeted PHP tests after any fix, and update sections 15 and 16.

---

## 15. Evidence and reproduction (2026 update)

### 15.1 Commands run and results

| Command | Result |
| --- | --- |
| `cd payroll-engine && go test ./... -count=1` | Passed: `money`, `payroll`, `rules`, `statutory` all `ok`; `cmd/payroll-engine`, `db`, `model` have no test files. |
| `python3 docs/payroll-audit/independent_recalc.py` | Exit 0. Every oracle value matches the Go suite; F-25 and F-26 now match the law-based values. |
| `php artisan test --compact` | **171 passed, 1 skipped (885 assertions)** after the remediation. |
| `php artisan payroll:engine --check` | Driver `go`; rebuilt binary; version `payroll-engine 9b75c7e-dirty (engine 1.0.0)`; database connected; **Payroll engine is ready.** |
| `go test ./internal/payroll -run TestMinimumWageExemptionCoversTheWholePeriod -v` and `go test ./internal/payroll -run TestFinalPayProratesThirteenthMonthOnEarnedBasic -v` | Both tests **PASS** unskipped. |
| `php artisan payroll:preview 41`, `51`, `52`, `53` | All four 2026 payrolls preview successfully; every employee row warns **no attendance recorded; paid on basic salary**. Payroll 53 has 0 employees. |
| `php artisan payroll:remittance 51` | Summary: SSS employee ₱3,675.00 / employer ₱7,415.00; PhilHealth ₱4,250.00 / ₱4,250.00; Pag-IBIG ₱470.00 / ₱470.00; BIR 1601-C ₱22,018.01. |
| `php artisan payroll:register 51 --path=/tmp/payroll51-register.csv` | Register written; header includes period basic, regular pay, overtime, night, holiday, rest day, allowances, compensations, bonus, commission, 13th month, leave conversion, separation pay, gross, taxable, non-taxable, tax, SSS, PhilHealth, Pag-IBIG, totals and net. |
| `php artisan payroll:bir-2316 1 --year=2026` | Rejects the 5 invalid TINs and returns failure; `--allow-invalid-tin` writes the data plus a rejects file and a control total. |
| `php artisan payroll:statutory:verify 2026-11-30 --company=1` | All six tables covered; all rows provisional; `--strict` returns failure. |
| `php artisan payroll:wage-orders:import` | 22 provisional wage-order rows; NCR-28 non-agriculture ₱755.00. |
| `php artisan payroll:holidays:import --year=2026 --company=1` | 19 holidays from Proclamation No. 1006. |
| `php artisan payroll:data-quality --company=1` | 40 issues across 8 employees: invalid TINs, missing agency numbers, missing region/industry, invalid MWE flag. |
| `php artisan payroll:remittance 51 --format=all --path=/tmp/remit` | Employee-level SSS, PhilHealth, Pag-IBIG and BIR files with control totals. |
| `php artisan payroll:evidence-pack 51 --path=/tmp/evidence51` | Register, remittance files, BIR 2316 data, manifest with SHA-256 hashes and README. |

> **Privacy.** Command output containing employee names or government numbers was not reproduced in this report. Internal employee row ids are used instead.

### 15.2 Independent 2026 sample recalculation

The sample is employee row id 30 in payroll 51 (semi-monthly, monthly-paid, ₱90,000.00 monthly basic). The recomputation uses only the 2025 SSS schedule, the 5% PhilHealth schedule, the 2% Pag-IBIG schedule and the 2023-onwards TRAIN semi-monthly table:

| Component | Computation | Amount |
| --- | --- | --- |
| Period basic | ₱90,000.00 ÷ 2 | ₱45,000.00 |
| SSS employee | 5% × ₱20,000 ÷ 2 = ₱500.00; WISP 5% × ₱15,000 ÷ 2 = ₱375.00 | ₱875.00 |
| PhilHealth employee | 5% × ₱90,000 = ₱4,500.00 premium; employee half = ₱2,250.00; ÷ 2 semi-monthly | ₱1,125.00 |
| Pag-IBIG employee | 2% × ₱10,000 = ₱200.00; ÷ 2 | ₱100.00 |
| Taxable income | ₱45,000.00 − ₱875.00 − ₱1,125.00 − ₱100.00 | ₱42,900.00 |
| Withholding tax | ₱4,270.70 + 25% × (₱42,900.00 − ₱33,333.00) | ₱6,662.45 |
| Total deductions | ₱875.00 + ₱1,125.00 + ₱100.00 + ₱6,662.45 | ₱8,762.45 |
| Net pay | ₱45,000.00 − ₱8,762.45 | ₱36,237.55 |

The stored `payroll_details` row for payroll 51, employee row id 30, reports exactly these amounts. This confirms that the **arithmetic** for a 2026 payroll date is correct against the rates the engine applies; it does not confirm that those rates are the 2026 legal rates (F-27).

**MWE illustration.** Employee row id 2 has a monthly basic of ₱100,000.00 and `is_minimum_wage = 1`. The engine reports a ₱6,633.75 semi-monthly MWE exemption. That equals ₱610.00 × 261 ÷ 12 ÷ 2, i.e. the stale 2023 NCR rate. At the 2026 NCR-28 rate of ₱755.00, the same formula gives ₱8,210.63. If the employee is not a legitimate MWE, the correct exemption is ₱0.00. Either way, the flag is a control failure (F-29) and the rate is stale (F-28).

### 15.3 Database evidence (read-only)

| Evidence | Result |
| --- | --- |
| Statutory rows | Pre-fix: SSS 61 rows effective 2025-01-01; PhilHealth 2024-01-01; Pag-IBIG 2024-02-01; BIR 2023-01-01; de minimis 2018-01-01. After remediation: 2026-01-01 rows exist for all five tables with `status = provisional`, and the predecessors are closed. |
| Minimum wage | Pre-fix: `statutory_min_wage` 0 rows; 2023 `minimum_wage_rates`. After remediation: 15 effective 2026 wage-order rows, including NCR-28 non-agriculture ₱755.00. |
| Holidays | Pre-fix: 2023 only (1,682 rows). After remediation: 19 rows for 2026 from Proclamation No. 1006. |
| Active employees | 17; 2 flagged as minimum-wage earners (monthly basic ₱40,000 and ₱100,000); 1 daily-paid. |
| Government identifiers | 16 of 17 have a blank TIN or the literal value `1`; 12 of 17 are missing SSS, PhilHealth and Pag-IBIG numbers. |
| 2026 timesheets | 93 rows, 1–31 October 2026, 3 employees; 0 rows in 26 July–10 August 2026. |
| 2026 payrolls | 4 processed; all preview with no attendance recorded and pay on basic salary. |
| Payroll 51 sample | 5 employees; gross ₱51,120.00 + ₱45,000.00 + ₱40,000.00 + ₱3,500.00 + ₱33,500.00; employee row id 30 recomputed in section 15.2. |

Representative queries:

```sql
-- Effective-date coverage for the 2026 payroll year
SELECT table_name, effectivity, source_url
FROM statutory_sources
ORDER BY table_name, effectivity;

-- Minimum-wage data quality
SELECT COUNT(*) FROM statutory_min_wage;
SELECT region, industry, wage_start/100, wage_end/100
FROM minimum_wage_rates
WHERE region = 'NCR' ORDER BY industry;

-- 2026 holiday coverage
SELECT YEAR(date) AS y, COUNT(*) FROM holidays WHERE deleted_at IS NULL GROUP BY YEAR(date);

-- Government identifier completeness
SELECT COUNT(*) AS employees,
       SUM(CASE WHEN tin_number IS NULL OR TRIM(tin_number) = '' OR tin_number = '1' THEN 1 ELSE 0 END) AS invalid_tin,
       SUM(CASE WHEN sss_number IS NULL OR TRIM(sss_number) = '' THEN 1 ELSE 0 END) AS missing_sss
FROM employees WHERE deleted_at IS NULL;

-- MWE flags with salary
SELECT id, basic_salary/100, daily_rate/100, payment_type
FROM employees WHERE deleted_at IS NULL AND is_minimum_wage = 1;
```

### 15.4 Test status and limits of this update

- The Go suite passes; F-25 and F-26 pass unskipped. The new-hire proration policy remains deliberately skipped.
- The full PHP suite passes: **171 passed, 1 skipped (885 assertions)**.
- The independent oracle passes; F-25 and F-26 match the law-based values.
- New tests cover the de minimis bases, the MWE guard, the 2026 command paths, statutory verification, master-data quality reporting, the payslip disclosures and the payroll controls.
- The 2026 sample reconciles to the stored payroll, but it validates the engine against the rates loaded, not the legal correctness of those rates. That is F-27; the 2026 rows remain provisional.

---

## 16. Professional confirmation required — 2026 update

This section extends section 10. A currently licensed Philippine CPA, tax practitioner or labour-law specialist must confirm the following before the 2026 payroll is relied upon:

1. **SSS 2026.** Confirm that no 2026 contribution-schedule circular supersedes SSS Circular No. 2024-006 and that the 15% schedule, ₱5,000–₱35,000 MSC, WISP threshold and EC amounts remain in force for 2026 payroll dates.
2. **PhilHealth 2026.** Confirm the premium rate, floor and ceiling for 2026. No 2026 premium-rate circular was located; RA 11223's schedule reached 5% in 2025. Confirm whether 5% with the ₱10,000 floor and ₱100,000 ceiling continues, and from what date.
3. **Pag-IBIG 2026.** Confirm the 2% rate and the ₱10,000 maximum fund salary, and whether HDMF Circular No. 460 has been superseded. The source site blocks automated retrieval.
4. **BIR 2026.** Confirm that the 2023-onwards TRAIN withholding table and the RR 11-2018 semi-monthly/monthly mechanics remain in force for 2026, and that no 2026 Revenue Regulations change the compensation-withholding table.
5. **Regional wage orders.** Confirm the wage order that applies to each company location and industry for each 2026 payroll date. NCR-28 (₱755.00 non-agriculture, effective 26 September 2026) is verified from the NWPC matrix; NCR-27 is subject to litigation; other regions have their own 2026 orders and tranches.
6. **2026 holidays.** Confirm the official 2026 holiday proclamation (Proclamation No. 1006) and any local holidays, and load them into the holiday table.
7. **MWE classification.** Confirm the policy and evidence for each employee flagged as a minimum-wage earner, including the applicable region, industry and wage order.
8. **Final pay and separation.** Confirm the company's separation-pay policy, the tax treatment of each separation cause, the leave-conversion policy and the final-pay settlement period (DOLE guidance: 30 days).
9. **Remittance forms.** Confirm the agency forms and e-filing channels for 2026 (SSS R-3, PhilHealth RF-1/ER2, Pag-IBIG MCRF, BIR 1601-C/1604-C/2316/alphalist) and the control totals the company will use.
10. **Retention.** Confirm the retention period for payroll records, payslips, remittances and BIR 2316 with the company's CPA/tax adviser, and document the evidence-pack contents.
11. **Contribution base.** Confirm whether salary-related allowances are included in the SSS, PhilHealth and Pag-IBIG contribution base, and how unpaid leave and partial months are treated.
12. **Look-back.** Confirm whether a look-back correction or voluntary remittance is required for the under-contributions and underpayments identified in this audit (F-25, F-26, F-28, F-29) and from which payroll period.

> **Scope disclaimer.** This is an AI-assisted, CPA-style review prepared from the code, tests, official issuances retrieved during the review and read-only database evidence. It is not a licensed CPA opinion, an audit under Philippine Standards on Auditing, or a substitute for the company's own payroll review and sign-off.

---

## Appendix A — Files changed by this audit

| File | Change |
|---|---|
| `payroll-engine/internal/rules/rules.go` | Stored day-type code mapping; `rh-sh` (260%) and `rd-rh-sh` (300%) added; invariant day normalisation |
| `payroll-engine/internal/rules/day_codes_test.go` | New — stored-code multipliers and classification |
| `payroll-engine/internal/statutory/statutory.go` | SSS 2025 bands centred on the credit; EC ₱10/₱30 by MSC |
| `payroll-engine/internal/statutory/sss_2025_test.go` | New — Circular 2024-006 row-by-row expectations |
| `payroll-engine/internal/statutory/statutory_test.go` | Band count corrected to 61 |
| `payroll-engine/internal/payroll/compute.go` | Absence no longer double-deducted |
| `payroll-engine/internal/payroll/compute_test.go` | Absence expectation corrected; premium-day integration test added |
| `app/Console/Commands/PayrollStatutoryImport.php` | Band generator centred on the credit; EC threshold; source note |
| `config/payroll.php` | `ec_employer_high`, `ec_high_msc` |
| `bin/payroll-engine` | Rebuilt with `make -C payroll-engine build` |
| `payroll-engine/internal/statutory/tax_boundaries_test.go` | New (second pass) — TRAIN band edges and continuity, PhilHealth and Pag-IBIG floor/ceiling |
| `payroll-engine/internal/payroll/audit_coverage_test.go` | New (second pass) — earnings/deduction/loan reconciliation, contribution ceilings in a run, 13th-month aggregation and boundary, repeat-run determinism; pending tests for F-25, F-26 and new-hire proration |
| `docs/payroll-audit/independent_recalc.py` | New (second pass) — committed Decimal oracle reproducing every expected value; exits non-zero on any disagreement |
| `tests/Feature/Admin/PayrollStatutoryImportTest.php` | New (third pass) — Pest tests pinning what the PHP importer writes against the circulars, including idempotency and predecessor closing |
| `tests/Unit/Payroll/GoPayrollEngineTest.php` | New (third pass) — Pest tests for the engine binary contract (flags, DSN, stderr, invalid JSON, version) |
| `tests/Unit/Payroll/EngineResultTest.php` | New (third pass) — Pest tests for the engine payload mapping |

## Appendix B — Queries used as evidence

```sql
-- Live day-type codes (F-01)
SELECT day_type, COUNT(*) FROM timesheets GROUP BY day_type;

-- Old statutory table shape (F-06/F-07)
SELECT msc_from/100, msc_to/100, msc/100, ee/100, er/100, ec/100
FROM statutory_sss_brackets ORDER BY msc_from LIMIT 8;

-- Configured earnings/deductions not loaded by the engine (F-02)
SELECT (SELECT COUNT(*) FROM employees_allowances)  AS allowances,
       (SELECT COUNT(*) FROM employees_compensations) AS compensations,
       (SELECT COUNT(*) FROM employees_deductions)  AS deductions,
       (SELECT COUNT(*) FROM employees_loans)       AS loans,
       (SELECT COUNT(*) FROM employees_bonuses)     AS bonuses,
       (SELECT COUNT(*) FROM employees_commissions) AS commissions;

-- Go-engine payslip showing zero allowance/incentive and total_employee = total_deductions (F-02/F-19)
SELECT allowance/100, incentive/100, total_employee/100, total_deductions/100
FROM payroll_details WHERE payroll_id = 41;
```

---

## Appendix C — Implementation update (4 November 2026)

Every finding in section 5 up to F-24 has been addressed in the application. F-25 and F-26 were raised in the second test pass (section 7) and remain open; each is pinned by a pending test with its expected value. The status column below supersedes the matrix in section 4 where they differ. The 2026 findings **F-27 to F-37** are recorded in section 14 and summarised in Appendix D; they are not part of the closure table below.

| Finding | Status | Implementation |
| --- | --- | --- |
| F-01 premium day types | Closed | Stored codes mapped in `rules.normalize`; 130 %–390 % premiums and tests. |
| F-02 allowances/compensations/bonuses/commissions/deductions/loans | Closed | `db.Load` reads all six tables with effective dates, cut-off frequency, taxable flags, proration and loan amortisation; the amounts flow into gross, non-taxable income, other deductions and the payslip. |
| F-03 night-shift overtime | Closed | Loader keeps `actual_nt`, `actual_nt_ot` and `actual_ot` disjoint; the engine pays the overtime base at 125 % plus the 10 % differential and excludes night-OT hours from ordinary night pay. |
| F-04 monthly basis and unworked holidays | Closed | `employees.payment_type` drives a fixed period basic for monthly staff (premium portions only) and attendance pay for daily staff; unworked regular holidays pay 100 % subject to the Article 94 condition. |
| F-05 absence double deduction | Closed | Absence is reported but never subtracted twice. |
| F-06 SSS 2025 bands | Closed | Bands centred on the credit; 61 rows seeded. |
| F-07 Employees' Compensation | Closed | ₱10 / ₱30 by MSC. |
| F-08 minimum wage earners | Closed (config-dependent) | `is_minimum_wage` and the regional minimum wage exempt the statutory wage and the holiday/OT/NSD attributable to it from taxable income. Wage orders must be loaded in `statutory_min_wage` or `minimum_wage_rates`. |
| F-09 13th-month pay | Closed | `payroll:special {group} thirteenth_month` computes 1/12 of YTD basic and aggregates the ₱90,000 exclusion with other benefits. |
| F-10 final pay / separation | Closed (policy-dependent) | `payroll:special {group} final_pay` computes final period pay, pro-rated 13th month, leave conversion and separation pay by cause; exempt causes are configurable. |
| F-11 legacy hours read as money | Closed | The money fallback is gone; legacy `regular` is read as hours and a warning is emitted. |
| F-12 immutable runs and audit trail | Closed | `payroll_detail_revisions`, `payroll_audit_events`, reopen-with-reason, engine version and input hash. |
| F-13 benefit switches and de minimis | Closed | Employee switches gate each premium; `statutory_de_minimis` holds the RR 2-98 ceilings and excess benefits become taxable. |
| F-14 contribution base | Closed (configurable) | `sss_base`, `phil_health_base` and `hdmf_base` settings select `basic`, `gross` or `period_gross`. |
| F-15 non-uniform deduction schedules | Closed | When a contribution schedule is not every-pay, withholding is computed on the monthly equivalent and allocated across cut-offs. |
| F-16 annualisation and BIR 2316 | Closed | `employee_payroll_ytd`, `payroll:annualise`, `payroll:bir-2316`. |
| F-17 rounding | Closed | Statutory amounts are split with `money.Split`; `sss_total` is the sum of its parts. |
| F-18 non-taxable policy | Closed | De minimis catalogue with effectivity plus the employee's de minimis switch. |
| F-19 accounting and reconciliation | Closed | Journal entries on approval, payroll register, and per-agency remittance reconciliation. |
| F-20 stale statutory tables | Closed | The importer closes predecessor rows and the engine fails loudly when the payroll date is not covered. |
| F-21 payslip basic salary | Closed | `basic_salary` is the period basic; the payslip prints the monthly rate and separates attendance/premiums. |
| F-22 6-day divisor | Closed | Monday–Saturday divisor standardised on 313. |
| F-23 NULL cut-off | Closed | The cut-off is derived from the payroll date when it is missing. |
| F-24 diagnostics and totals | Closed | Warnings are persisted on `payrolls.warnings` and `payroll_details.warnings`; premium components and `total_employee` are separate columns. |
| F-25 daily-paid MWE exemption | **Open** | The exemption uses one day of minimum wage for daily-paid staff. `TestMinimumWageExemptionCoversTheWholePeriod` pins the expected ₱6,616.00 against the observed ₱856.00. |
| F-26 final pay 13th pro-ration | **Open** | Pro-rates on `PeriodBasic` instead of the basic earned in the final period. `TestFinalPayProratesThirteenthMonthOnEarnedBasic` pins ₱12,500.00 against the observed ₱13,087.50. |

Remaining conditions that are not code defects:

1. The PhilHealth 2026 rate, HDMF Circular 460 wording, contribution-base policy, regional wage orders and separation policy still require the CPA/labour-law confirmation listed in section 10.
2. Existing employees should be reviewed for the benefit switches (`rest_day_pay`, `regular_holiday_pay`, `overtime_pay`, `night_differential`, `deminimis`) because rows created before those columns existed default to `0`. Premia suppressed by a switch are reported as warnings on the payslip.
3. Historical payrolls dated before 2025 need the applicable statutory schedules imported before they can be re-processed; the engine will refuse to compute them with an uncovered table.

---

## Appendix D — 2026 findings register

> **Status update (10 November 2026).** All findings below are implemented in the 10 November remediation. See Appendix F for the implementation and verification record. "Fixed (provisional)" means the code is in place but the underlying 2026 rate or wage-order data still needs professional confirmation.

| Finding | Severity | Status | Implementation | Primary owner |
| --- | --- | --- | --- | --- |
| F-27 — 2026 statutory basis not explicitly effective-dated | HIGH | Fixed (provisional) | 2026 rows imported with `status = provisional`; `payroll:statutory:verify` reports coverage and `--strict` fails | Payroll lead + CPA |
| F-28 — Minimum-wage lookup stale and not employee-specific | HIGH | Fixed (provisional) | Office/employee region + industry; 2026 NWPC matrix import; per-employee wage resolution | Payroll lead + HR |
| F-29 — MWE flag not validated | HIGH | Fixed | Engine warns and grants zero exemption for a rate above the minimum; `payroll:data-quality` reports the flag | HR |
| F-30 — Payrolls can be processed without attendance | MEDIUM | Fixed | Processing warns and requires a written override; reason recorded on the payroll and in `payroll_audit_events` | Payroll lead |
| F-31 — Remittance and 2316 outputs not agency-prescribed | MEDIUM | Fixed (working papers) | Employee-level SSS/PhilHealth/Pag-IBIG/BIR CSVs with control totals; mapping doc | Finance + payroll lead |
| F-32 — Government identifiers incomplete/invalid | MEDIUM | Fixed | ID format rules, `payroll:data-quality`, and BIR 2316 block/override | HR |
| F-33 — 2026 holiday calendar not loaded | MEDIUM | Fixed | `payroll:holidays:import` loads Proclamation No. 1006 (19 holidays) | HR |
| F-34 — Maker-checker not enforced | MEDIUM | Fixed (warning + override) | Reviewed step, `processed_by`/`reviewed_by`, approval override reason and audit event | Company owner |
| F-35 — Payslip web view omits government identifiers | LOW | Fixed | Masked TIN/SSS/PhilHealth/Pag-IBIG and employer details on the web payslip | Payroll lead |
| F-36 — No documented retention/evidence-pack policy | LOW | Fixed | `payroll:evidence-pack` writes register, remittance files, BIR 2316, manifest hashes and README | CPA + company owner |
| F-37 — De minimis ceilings do not match RR 11-2018 | MEDIUM | Fixed (provisional) | Monthly/annual/semester/hourly-OT bases and corrected ceilings; boundary tests | Payroll lead + CPA |

---

## Appendix E — 2026 official sources and retrieval limitations

| Source | What was retrieved | Used for | Limitation |
| --- | --- | --- | --- |
| SSS, *SSS Contribution Table* — https://www.sss.gov.ph/sss-contribution-table/ | Page headed **Effective January 2025**, describing the 2021–2025 schedule under RA 11199 | SSS 15% schedule, MSC bands, WISP, EC | No 2026 table on the page; the page describes the schedule as ending in 2025. |
| SSS, *SSS Circulars* — https://www.sss.gov.ph/sss-circulars/ | 2026 circular index (2026-001 to 2026-004 in the retrieved view) | Confirming no 2026 contribution-schedule circular in the index | The index may be paginated; confirm the full 2026 list. |
| PhilHealth, *Circulars 2026* — https://www.philhealth.gov.ph/circulars/2026/ | 2026-0001 to 2026-0018 (coverage, benefits, DRG, quality) | Confirming no 2026 premium-rate circular in the retrieved list | The index may be paginated; confirm the full 2026 list. |
| PhilHealth, *Circulars 2025* and *2024* archives | Retrieved lists | Checking for a premium-rate circular | The retrieved lists did not show a rate circular; confirm with PhilHealth. |
| BIR, *RR No. 11-2018* — https://bir-cdn.bir.gov.ph/local/pdf/RR%20No.%2011-2018.pdf | 47-page PDF | Semi-monthly/monthly withholding mechanics and de minimis rules | The PDF is the 2018 issuance; the 2023 TRAIN schedule is in RA 10963. |
| RA 10963 (TRAIN) — https://lawphil.net/statutes/repacts/ra2017/ra_10963_2017.html | Full text | 2023-onwards annual tax table and the ₱90,000 benefits exclusion | Statute text; confirm no 2026 amending law. |
| PD 851 — https://lawphil.net/statutes/presdecs/pd1975/pd_851_1975.html | Full text | 13th-month pay definition and 24 December deadline | Read with MO 28 and the DOLE handbook. |
| Labor Code (PD 442) — https://lawphil.net/statutes/presdecs/pd1974/pd_442_1974.html | Full text | Separation pay and wage/attendance provisions | Use the renumbered articles (297–299) and current DOLE guidance. |
| NWPC, *Latest Wage Orders Matrix 2026–2027 as of 06 November 2026* — https://nwpc.dole.gov.ph/wp-content/uploads/2026/11/Latest-Wage-Orders-Matrix-2026-2027-As-of-06-November-2026.pdf | 4-page PDF | 2026 regional wage orders and effectivity dates | Verify the wage order for each company location; NCR-27 is subject to litigation. |
| NWPC, *NCR* — https://nwpc.dole.gov.ph/ncr/ | NCR wage table | NCR-28 non-agriculture ₱755.00, effective 26 September 2026; NCR-DW-06 ₱7,800.00 | Confirm local exemptions and industry classifications. |
| Pag-IBIG Fund — https://www.pagibigfund.gov.ph/ | reCAPTCHA interstitial | Attempted verification of Circular No. 460 and the 2026 position | **Not retrievable by automated client**; confirm with Pag-IBIG. |
| BIR withholding tax and Revenue Regulations pages | Client-rendered pages; no readable 2026 table | Attempted 2026 withholding verification | **Not readable as text**; confirm with BIR or the company's tax adviser. |

> **Retrieval note.** `web_fetch` failed at the TLS/transport layer for the agency hosts during this review; the pages above were retrieved with `curl`. Where a page is client-rendered or protected by a challenge, the limitation is stated rather than inferred away.

---

## Appendix F — 2026 remediation implementation (10 November 2026)

**Scope.** All findings F-25 to F-37 were implemented. No production employee data was silently rewritten; the controls report and require correction.

| Finding | Implementation | Verification |
| --- | --- | --- |
| F-25 | `minimumWagePay` counts paid days for daily-paid staff and caps the exemption at the basic earned. | `TestMinimumWageExemptionCoversTheWholePeriod` passes unskipped (₱6,616.00 exemption, ₱0 taxable). |
| F-26 | `ComputeFinalPay` uses `slip.BasicEarned` in the 13th-month base. | `TestFinalPayProratesThirteenthMonthOnEarnedBasic` passes unskipped (₱12,500.00). |
| F-27 | `payroll:statutory:import --effective-from=2026-01-01 --provisional`; `payroll:statutory:verify {date} [--strict]`. | 2026-11-30 resolves SSS (61), PhilHealth (1), Pag-IBIG (1), BIR (12), de minimis (6) and minimum wage (15) rows; all report provisional. |
| F-28 | `offices.region/industry` and `employees.region/industry`; `payroll:wage-orders:import`; per-employee resolution in the Go loader. | NCR-28 non-agriculture resolves ₱755.00; the ₱100,000 MWE preview now shows the applicable ₱755.00 rate. |
| F-29 | Engine guard warns and zeroes the exemption when the daily rate exceeds the minimum; `payroll:data-quality` lists invalid flags. | Payroll 51 preview: deductions rose from ₱8,486.76 to ₱10,145.20 for the flagged ₱100,000 employee, with the warning "minimum wage flag ignored". |
| F-30 | `PayrollAttendance` detects missing timesheets; `PayrollRun::process()` requires `overrideReason`, stores it and records `no_attendance_override`. | `PayrollRunTest` rejects processing without a reason; the manual preview shows the missing-attendance warning. |
| F-31 | `payroll:remittance --format=sss` (also philhealth, pagibig, bir or all) with control totals; `payroll:bir-2316` control total and rejects. | SSS total ₱3,675.00 / ₱7,415.00 / ₱11,090.00 and BIR tax ₱22,018.01 match the summary; the new command test asserts employee rows and totals. |
| F-32 | TIN/SSS/PhilHealth/Pag-IBIG format rules; `payroll:data-quality`; BIR 2316 rejects invalid TINs unless `--allow-invalid-tin`. | `payroll:bir-2316` returns failure for the 5 invalid TINs; `--allow-invalid-tin` writes a rejects file and the data. |
| F-33 | `payroll:holidays:import --year=2026` loads 19 Proclamation No. 1006 dates. | The command test asserts 19 rows, `rh` for 25 December, `sh` for 24 December and `regular` for 25 February. |
| F-34 | `processed_by`/`reviewed_by`/`reviewed_at`; `review()` action; approval warns and requires an override reason without a review, when the reviewer is the processor, or when the approver is the processor. | `PayrollRunTest` covers review-then-approve and the missing-review rejection. |
| F-35 | Masked TIN/SSS/PhilHealth/Pag-IBIG and employer/approver details on the web payslip. | Blade renders; the PDF already carried the identifiers. |
| F-36 | `payroll:evidence-pack {payroll} --path=...` writes register, per-agency remittance files, BIR 2316 data, `manifest.json` with SHA-256 hashes and `README.txt`. | Manual run on payroll 51 produced 8 data files plus manifest and README. |
| F-37 | `statutory_de_minimis.basis` and `DeMinimisBenefit.Basis`; corrected ceilings and annual/semester/hourly-OT calculations. | `TestDeMinimisAnnualClothingCeiling`, `TestDeMinimisSemesterMedicalCeiling` and `TestDeMinimisHourlyMealCeiling` pass. |

**Remaining conditions.**

1. The 2026 PhilHealth/Pag-IBIG rates and the non-NCR wage ranges are **provisional**. Run `payroll:statutory:verify 2026-11-30 --strict` after the CPA confirms them; it will fail until every row is verified.
2. The production data still needs correction: 16 invalid/blank TINs, 12 missing agency numbers, 2 invalid MWE flags, and office/employee region/industry values. Use `php artisan payroll:data-quality --company={id}`.
3. Existing 2026 payrolls may change when re-processed (F-25, F-29, F-37). Preview first; processing creates a revision and does not destroy the prior details.
4. The remittance exports are employee-level working papers, not agency e-filing files. Verify the current form mapping before submission.
5. `docs/payslip.pdf` is missing, so `--payslips` in the evidence pack only warns; print payslips from the payroll register.
