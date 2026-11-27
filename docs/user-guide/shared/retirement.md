---
description: "Manage OpenHR PH retirement plans, eligibility, cases, reviewed benefit and terminal-leave calculations, documents and external GSIS/SSS claims."
---

# Retirement

This page covers the shared **Retirement** menu. The plan and external agency differ by sector; use the
[Private-sector HR guide](../private/private-hr.md) for company plans and the
[Government-sector HR guide](../government/government-hr.md) for GSIS.

## Retirement plans

**Retirement → Retirement Plans**

Create a plan with:

- name and sector,
- plan type: defined benefit, defined contribution, statutory, or GSIS,
- eligibility age and minimum service years,
- effective dates,
- benefit formula,
- optional employer and employee contribution rates.

### Benefit formulas

Formulas are inert JSON. Supported shapes:

```json
{"type":"percentage_of_salary","rate":0.025}
```

```json
{"type":"fixed_amount","amount":100000}
```

```json
{"type":"accumulated_contributions"}
```

The service never executes user-supplied expressions. A new formula shape requires a code change and
review.

## Retirement cases

**Retirement → Retirement Cases**

Use **Open Retirement Case** to select the employee, plan, retirement type, and proposed date. The case
stores an eligibility snapshot (age, service years, required age/service, and reasons for ineligibility).
The snapshot is advisory; HR must verify official records.

### Case lifecycle

```text
draft → eligibility_review → applied → approved → completed
```

`cancelled` is available from the open states. Use **Update Case** to:

- assess eligibility,
- submit the application,
- approve the application,
- complete the retirement,
- cancel the case.

When a case is completed, the employee's lifecycle is set to separated and the proposed retirement date is
recorded as the separation date.

## Calculations

**Retirement → Calculations**

Calculations are created from a case and keep their inputs, outputs, and rule version.

### Benefit calculation

1. Create the calculation with the service years and monthly salary. The plan's formula is applied.
2. The calculation starts as `draft` and is marked **provisional**.
3. Use **Review Calculation** to approve or reject it. Approval records the reviewer, time, and notes.
4. Approved calculations are immutable and cannot be deleted.
5. A case cannot be completed without an approved benefit calculation.

Do not treat a draft or rejected calculation as a final entitlement. Preserve the inputs and rule version
so the computation can be reproduced.

### Terminal leave

Terminal leave is a separate calculation type. Record the reviewed days and daily rate. The amount is
`days × daily_rate` and is marked provisional until reviewed. Government users should confirm the balance
against the leave card before approval.

## Documents

Attach documents to a retirement case (service record, leave card, application, agency forms). Documents
use the shared repository, including classification, access logs, and acknowledgments.

## External claims

**Retirement → External Claims**

Track GSIS, SSS, or other agency claims:

- agency and reference number,
- status: `not_submitted`, `submitted`, `in_process`, `approved`, `denied`, or `released`,
- submitted, last-checked, and released dates,
- notes.

The system prepares and tracks claims. It does not assume a public agency API exists. Claims not checked in
30 days are included in `hr:reminders`.

## Common problems

| Problem | What to check |
| --- | --- |
| A case cannot be opened | The employee must belong to the company, and the plan should be active and effective. |
| A case cannot be completed | An approved benefit calculation is required. |
| A calculation cannot be edited | It is already approved. Create a new calculation for a new review. |
| Eligibility shows ineligible | Check birth date, hire date, plan age/service, and plan effective dates. |
| Terminal leave amount is zero | Check days and daily rate inputs; both must be recorded. |
| Claim status is stale | Update the external claim; reminders report claims not checked in 30 days. |

## Related documentation

- [Private-sector HR](../private/private-hr.md)
- [Government-sector HR](../government/government-hr.md)
- [Compliance](compliance.md)
- [Workforce analytics](workforce-analytics.md)
- [HR, workforce and talent overview](hr-and-workforce.md)
