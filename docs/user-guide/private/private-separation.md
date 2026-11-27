---
description: "Private-sector separation in OpenHR PH: resignation, termination, redundancy, end of contract, clearance tasks, final settlement, effective separation and retirement cases."
---

# Private separation

This page covers the **Separation** menu for Private-sector organisations. Employment is covered in
[Private employment](private-employment.md); retirement in [Retirement](../shared/retirement.md).

## Separation types

| Type | Typical use |
| --- | --- |
| **Resignation** | Employee-initiated separation with notice |
| **Termination** | Just or authorized cause with evidence and approvals |
| **Redundancy** | Redundancy or retrenchment with affected-role records |
| **End of contract** | Fixed-term or project completion |
| **Retirement** | Retirement case; use the Retirement module for calculations |
| **Death** | Death in service with beneficiary documents |
| **Other** | Any other authorized separation |

## Initiate the case

**Separation → Separation Cases**

1. Use **Initiate Separation** on the employee.
2. Choose the type and record:
   - proposed effective date,
   - reason and notes,
   - clearance template.
3. Save. The case starts as `initiated` and clearance tasks are generated when a template is supplied.

Statuses: `initiated`, `review`, `clearance_in_progress`, `final_pay_review`, `approved`, `effective`,
`closed`, `cancelled`.

## Clearance

**Separation → Clearance Tasks**

1. Each task records the office, assignee, status, and evidence.
2. Use **Close Clearance Task** to mark it completed or waived.
3. Attach evidence for property return, finance accountability, and IT access removal.

**Separation → Clearance Templates**

Maintain reusable templates per organization. A template defines the tasks and responsible offices. Do not
edit a template after a case has used it; create a new template version instead.

## Final settlement

**Separation → Final Settlements**

Use **Record Final Settlement** to record the reviewed itemisation:

- earnings, including final pay, leave conversion, and separation or retirement components,
- deductions, including loans, advances, and statutory contributions,
- total amount,
- calculation reference,
- reviewer and approver.

Statuses: `draft`, `review`, `approved`, `paid`.

The system records the reviewed inputs; it does not determine legal entitlement. HR and finance should
review the itemisation against the employee's records and the applicable policy before approval.

## Make the separation effective

Use **Advance Case** to move the case through:

```text
initiated → review → clearance_in_progress → final_pay_review → approved → effective
```

When the case becomes effective:

- the employee's lifecycle is set to separated;
- the effective date is recorded on the employee;
- the case remains available for clearance and final-pay follow-up;
- the case can then be closed.

Do not mark a case effective before clearance and final pay are reviewed.

## Documents

Attach the resignation letter, notices, clearance forms, final settlement, quitclaim, and certificate of
employment to the case or the employee's document repository. Use the correct classification; separation
documents may be confidential.

## Common problems

| Problem | What to check |
| --- | --- |
| Separation menu is missing | The installation sector must be Private. |
| A case cannot advance | The target status is not allowed from the current status. |
| Clearance tasks are missing | The case was created without a clearance template. |
| Final settlement cannot be approved | It must be reviewed first, and finance must confirm the itemisation. |
| Employee is not marked separated | The case has not reached `effective`. |
| Retirement amount is missing | Use the Retirement module for the benefit and terminal-leave calculations. |

## Related documentation

- [Private employment](private-employment.md)
- [Retirement](../shared/retirement.md)
- [Documents and workflow](../shared/documents-and-workflow.md)
- [Reports and payslips](../shared/reports-and-payslips.md)
- [Private-sector HR](private-hr.md)
