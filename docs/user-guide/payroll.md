# Payroll

This page helps you prepare, process, review, and approve payroll with confidence.

## Before you start

- Confirm the payroll group, cut-off, and payroll date.
- Make sure employee records are complete and active.
- Make sure attendance, leave, overtime, undertime, and official business requests are reviewed.
- Confirm allowances, compensations, deductions, loans, bonuses, and commissions have the correct effectivity dates. Effective-dated rows are paid automatically in the period they cover.
- Review the statutory rates with your payroll lead. Statutory tables are effective-dated so historical runs stay reproducible; the engine refuses to process a payroll whose date is outside every effective row.
- Check each employee's payment type (**monthly** or **daily**) and benefit switches (rest day, holiday, overtime, night differential, de minimis).

## Payroll groups

A payroll group defines how often employees are paid and which rules apply. Open **Payroll Management → Payroll Group** to review or create one.

Typical settings include:

- frequency (for example, semi-monthly or monthly)
- days per year and hours per day (261 for Monday–Friday, 313 for Monday–Saturday)
- SSS, PhilHealth, Pag-IBIG, and tax schedules
- whether the group is the default group

Employees must be assigned to the correct payroll group.

## How pay is calculated

- **Monthly-paid employees** receive a fixed period basic (the monthly rate divided by the number of pays). Working fewer or more days in a cut-off does not change it; absences reduce it by the daily rate and premium days add only the premium portion (rest day +30%, regular holiday +100%, and so on).
- **Daily-paid employees** are paid for attended days, with the full premium for rest days and holidays, 100% for an unworked regular holiday when they worked or were on paid leave the day before, and 125% overtime plus the 10% night differential.
- Allowances, compensations, bonuses, commissions, loans, and other deductions configured for the period are included in gross and net pay.
- Minimum wage earners are exempt from tax on the statutory minimum wage and on the holiday, overtime and night-shift pay attributable to it.

## Payroll types

| Type | Purpose |
| --- | --- |
| **Regular** | The normal cut-off payroll. |
| **13th month** | 1/12 of the basic salary earned in the year, with the ₱90,000 exclusion aggregated with other benefits. |
| **Final pay** | Final period pay, pro-rated 13th month, leave conversion and separation pay for separated employees. |
| **Annual adjustment** | Year-end true-up of withholding tax against the annualised taxable income. |

Create the non-regular runs from the console:

```bash
php artisan payroll:special {group} thirteenth_month --date=2025-12-24 --process
php artisan payroll:special {group} final_pay --separation-reason=retrenchment --years-of-service=5 --process
php artisan payroll:annualise {group} --year=2025 --process
```

Add `--employee=ID` (repeatable) to restrict a run.

## Create or open a payroll run

1. Open **Payroll Management → Payroll**.
2. Create a payroll for the period, or open an existing one.
3. Check the payroll group, payroll date, cut-off, and date range.

The payroll run page shows a step indicator and the payroll status.

## The payroll flow

### 1. Preview

Select **Preview**. The payroll engine calculates the run without saving the final results.

Use the preview to check:

- employee count and totals
- gross pay, deductions, and net pay
- statutory contributions and withholding tax
- employees with zero or unusual pay
- missing attendance or incomplete records

Preview is a dry run. It is safe to repeat.

### 2. Process

Select **Process payroll** and confirm.

Processing recalculates the run and writes the payroll details. The status moves to **running**. The page refreshes automatically while processing.

- Smaller payrolls are processed immediately.
- Larger payrolls are queued and may take longer. Keep the queue worker running.
- If processing fails, the page shows the reason. Fix the data and process again.

### 3. Review

When processing finishes, the status becomes **processed**. Review the payroll details carefully:

- employee pay and deductions
- overtime, leave, undertime, and official business effects
- loans and other recurring deductions
- statutory contributions and tax
- employer contributions
- totals and headcount

### 4. Approve and lock

When the run is correct, select **Approve & lock** and confirm.

Approved payrolls are locked. This protects the numbers used for payslips and reports. If you need to change an approved payroll, a company owner must enter a **reason** and select **Reopen**. The reason is stored in the payroll audit trail, the journal entry is reversed, and the payroll moves back to **processed** so it can be reviewed and approved again.

Re-processing never destroys the prior run: the previous details are copied to the payroll revisions before the new figures are written.

### 4a. Review, then approve

After processing, open the payroll run and complete the **Review and segregation of duties** panel:

1. Read the register and the control totals.
2. Enter an optional review note and select **Mark reviewed**.
3. Approve with a different user where possible.

If the run has not been reviewed, or you are approving a run you processed yourself, approval requires an **approval override reason**. The reason and the reviewer/approver are recorded in the payroll audit trail.

### 4b. Paying without attendance

If employees have no timesheet for the period and the company pays the basic salary without attendance, the run shows an attendance warning. Enter an **attendance override reason** before processing. The reason is stored on the payroll and in the audit trail.

### 4c. Statutory verification and data quality

Before a 2026 run, verify that the statutory tables cover the payroll date:

```bash
php artisan payroll:statutory:verify 2026-11-30 --company={id}
```

Provisional rows are reported but do not block; add `--strict` to fail until every row is confirmed. Use the data-quality report to find invalid MWE flags, missing government IDs and missing office/employee region or industry:

```bash
php artisan payroll:data-quality --company={id}
```

### 5. Payslips

After processing or approval, open an employee's payslip from the payroll register. Payslips can be printed from the browser or downloaded as a PDF, depending on your permissions.

See [Reports and payslips](reports-and-payslips.md).

### 6. Export the register and remittances

```bash
php artisan payroll:register {payroll} --path=register.csv
php artisan payroll:remittance {payroll} --path=remittance.csv
php artisan payroll:bir-2316 {group} --year=2025 --path=bir-2316.csv
```

The register reconciles to the journal entry posted when the payroll was approved.

## Payroll statuses

| Status | Meaning |
| --- | --- |
| **Open** | The payroll exists but has not been processed. |
| **Draft** | An early state used by some records. Preview and process when ready. |
| **Running** | The payroll engine is processing. Wait for it to finish. |
| **Processed** | Details are calculated and ready for review. |
| **Approved** | The payroll is reviewed and locked. |
| **Closed** | The payroll is final and no further changes are expected. |

## Before you approve

- [ ] The payroll group and period are correct.
- [ ] Every expected employee appears.
- [ ] No unexpected employee appears.
- [ ] Attendance and requests are up to date.
- [ ] Overtime and leave are correct.
- [ ] Allowances, compensations, bonuses, commissions, loans, and recurring deductions are correct.
- [ ] Statutory contributions and tax look right.
- [ ] Net pay totals match your review.
- [ ] You have taken a database backup or snapshot if your team requires one.

## Common questions

**Why is the process button disabled?**
The payroll may already be approved or closed. Reopen it first if changes are needed.

**Why is an employee missing?**
Check the employee's payroll group, employment status, date hired, and attendance for the period.

**Why are the amounts different from last time?**
Check attendance, overtime, leave, loans, salary changes, and statutory rates. Use Preview to compare before approving.

**Who can reopen an approved payroll?**
Only the company owner can reopen an approved payroll, and a reason is required. The reason and the engine version are recorded in the audit trail.

**Why does processing stop with "statutory table does not cover the payroll date"?**
The payroll date is outside every effective row of a statutory table. Import the current schedule (`php artisan payroll:statutory:import`) or correct the payroll date. The engine will not silently use a stale table.

**Can I process the same payroll twice?**
Preview as often as you like. Processing recalculates the run. Approved payrolls are locked, so reopen first if you need to process again.

## See also

- [2026 CPA payroll cycle procedure and advisory checklist](../payroll-audit/2026-11-payroll-compliance-audit.md#12-end-to-end-payroll-cycle--cpa-operating-procedure-2026) — the full step-by-step from timesheets to payslips, remittances and special runs, with the 2026 statutory verification and open findings.
