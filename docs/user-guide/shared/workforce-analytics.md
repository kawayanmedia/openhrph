---
description: "Read the OpenHR PH workforce analytics report: headcount, hires, separations, retirement forecasts, training and review completion, corrections, claims, certifications and compliance exposure."
---

# Workforce analytics

**Reports → Workforce Analytics** (`/app/reports/workforce-analytics`) gives HR a tenant-scoped snapshot
of workforce and compliance exposure. The report is read-only and refreshes on demand.

## Metrics

| Metric | What it counts |
| --- | --- |
| **Active headcount** | Employees not separated or archived |
| **New hires (90 days)** | Employees with a hire date in the last 90 days |
| **Separations YTD** | Separation cases with an effective or proposed date in the current year |
| **Pending retirements** | Retirement cases in `applied` or `approved` |
| **Retirements next 12 months** | Open retirement cases with a proposed date in the next 12 months |
| **Training completion** | Completed enrollments divided by all enrollments |
| **Reviews finalized** | Finalized or acknowledged reviews divided by all reviews |
| **Pending corrections** | Attendance corrections still in `submitted` |
| **Open external claims** | Submitted, in-process, or approved retirement claims |
| **Certifications expiring** | Active certifications expiring in the next 30 days |
| **Approved compliance rules** | Rules in `approved` status |
| **Rules expiring (90 days)** | Approved rules with an effective-until date in the next 90 days |

Use **Refresh** after processing payroll, completing retirements, or updating compliance rules.

## How to read the report

- A high **pending corrections** count usually means attendance data is not ready for payroll. Clear the
  corrections before locking timesheets.
- **Retirements next 12 months** is a planning metric; it is based on open cases, not on every employee who
  might become eligible.
- **Training completion** and **Reviews finalized** are rates. A zero denominator shows `0%`.
- **Certifications expiring** and **Rules expiring** are leading indicators for the reminder run.
- Use the report with the operational screens; it does not replace the detailed lists.

## Scope and permissions

- The report is scoped to the active company. Switching companies changes the data.
- **Employees** permission is required to open it.
- Metrics are aggregated. They do not expose confidential case content.

## Related documentation

- [Performance](performance.md)
- [Learning](learning.md)
- [Retirement](retirement.md)
- [Compliance](compliance.md)
- [Reports and payslips](reports-and-payslips.md)
