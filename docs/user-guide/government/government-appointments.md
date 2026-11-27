---
description: "Government appointment records in OpenHR PH: create appointments, attach documents, move the appointment state machine, record CSC actions and assumption of duty."
---

# Appointments

This page covers the **Appointments** screens in the **Government** menu. For plantilla items and
qualification screening, see [Plantilla and qualifications](government-plantilla.md).

## Create an appointment

**Government → Appointments**

1. Create the appointment for the employee.
2. Select:
   - the plantilla item,
   - the position and office,
   - the appointment type (original, promotion, transfer, and the other available types),
   - the nature (permanent, temporary, casual, contractual, and the other available natures),
   - the legal basis and reference number.
3. Set the effectivity date. The assumption date can be recorded later.
4. Save. The appointment starts as `draft`.

Attach the supporting documents in **Government → Appointment Documents**:

- the appointment paper,
- the position description,
- the qualification documents,
- the CSC submission or approval,
- other agency requirements.

## Appointment state machine

Use **Change Status** on the appointment. The usual path is:

```text
draft → for_review → approved_for_issuance → issued → served
      → submitted_to_csc → csc_action_pending → validated
```

`returned` and `cancelled` are available where the transition rules allow. Invalid moves are rejected.

Do not edit the status field directly. Each transition records the actor and timestamp, and the audit
trail expects the state machine path.

## CSC actions and assumption of duty

**Government → CSC Actions**

1. Use **Record CSC Action** on the appointment or CSC Actions screen.
2. Record the CSC response, reference, and date.
3. Attach the CSC order or notice.

**Government → Assumptions**

1. Use **Record Assumption** when the appointee reports for duty.
2. Record the assumption date and supporting document.
3. The assumption date is written back to the appointment.

Once the appointment is validated:

- it is part of the employee's service record;
- it can be used by personnel actions and payroll;
- the plantilla item shows the current occupant.

## Appointment documents

**Government → Appointment Documents**

Use this screen to review all documents linked to appointments. Documents can also be stored in the shared
document repository with the correct classification.

## Common problems

| Problem | What to check |
| --- | --- |
| Appointment cannot be created | The employee, plantilla item, and effectivity date are required. |
| Plantilla item is not selectable | The plantilla version must be approved and the item must be active and unoccupied. |
| Status change is rejected | The target status is not allowed from the current status. |
| CSC action cannot be recorded | The appointment should be issued/served first, and the CSC reference is required. |
| Assumption fails | The appointment must be in a served or issued state that allows assumption. |
| Appointment is missing from the service record | The appointment may not be validated yet. |

## Related documentation

- [Government-sector HR](government-hr.md)
- [Plantilla and qualifications](government-plantilla.md)
- [Personnel actions](government-personnel-actions.md)
- [Government records](government-records.md)
- [Documents and workflow](../shared/documents-and-workflow.md)
