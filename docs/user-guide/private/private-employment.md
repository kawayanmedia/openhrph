---
description: "Private-sector employment administration in OpenHR PH: employments, contracts, probation reviews, regularization decisions, onboarding checklists, onboarding tasks and private leave policy rules."
---

# Private employment

This page covers the **Employment** menu for Private-sector organisations. Recruitment is covered in
[Private recruitment](private-recruitment.md); separation in [Private separation](private-separation.md).

## Employments

**Employment → Employments**

1. Create the employment for the employee with:
   - employment type (regular, probationary, contractual, project, seasonal, or the types available),
   - start date,
   - status (`active`, `suspended`, `ended`),
   - organization unit, location, and cost center,
   - payroll group and compensation references where required.
2. Use **Start Employment** when the employment is created outside the hire flow.
3. Ending an employment normally happens through a separation case so clearance and final pay are
   complete.

Only one active employment per employee is expected. If an employee moves between companies, create the
employment in the destination company and end the old one.

## Contracts

**Employment → Contracts**

Record contract dates, terms, renewal date, and status. Contracts are separate from the employment so
fixed-term and project employees keep a renewal history.

When a contract is renewed, create a new contract record with the new dates instead of editing the old one.
The employment remains continuous unless there is a break.

## Probation

**Employment → Probation Reviews**

1. Create the review before the probationary period ends.
2. Record the review date, rating, recommendation, and comments.
3. Attach supporting documents where required.

Probation reviews feed the regularization decision. Keep the review even when the decision is to extend or
end the employment.

## Regularization

**Employment → Regularization Decisions**

Use **Regularization Decision** to record:

- the decision (regularized, extended, or not regularized),
- the effective date,
- the approver and approval date,
- notes and supporting documents.

The decision updates the employment status when it takes effect. The probation review and the decision
remain separate records for audit.

## Onboarding

**Employment → Onboarding Checklists**

The hire conversion generates a checklist from the configured template. Review the checklist and adjust
owners or due dates.

**Employment → Onboarding Tasks**

1. Assign each task to an owner.
2. Set the due date.
3. Mark tasks completed or waived.
4. Use the task notes to record evidence or blockers.

Onboarding should be complete before the first payroll run so bank details, government numbers, and
contracts are ready.

## Private leave policy rules

**Employment → Leave Policy Rules**

Private leave rules configure eligibility and accrual without hard-coding company policy. Rules are
resolved by the leave service when a request is evaluated.

1. Create the rule with the leave type, employment type or classification, eligibility conditions, and
   accrual values.
2. Set effective dates.
3. Activate the rule.

Statutory minimums still apply. A company rule cannot override mandatory law. If a rule conflicts with a
statutory requirement, the system should raise an error rather than silently choosing the company value.

## Common problems

| Problem | What to check |
| --- | --- |
| Employment menu is missing | The installation sector must be Private. |
| An employee cannot be hired again | An active employment already exists; end it first. |
| Regularization cannot be recorded | The employment should be probationary and the review should exist. |
| Onboarding tasks are not generated | The hire conversion or checklist template is missing. |
| Leave rule is not applied | Check the effective date, employment type, and rule status. |
| Contract renewal is missing from history | Create a new contract record instead of editing the old one. |

## Related documentation

- [Private recruitment](private-recruitment.md)
- [Private separation](private-separation.md)
- [Attendance and requests](../shared/attendance-and-requests.md)
- [Payroll](../shared/payroll.md)
- [Private-sector HR](private-hr.md)
