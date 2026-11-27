---
description: "Government personnel actions in OpenHR PH: promotion, transfer, detail, reassignment, separation actions, approval workflow, effective dates and occupancy updates."
---

# Personnel actions

This page covers the **Personnel Actions** screen in the **Government** menu. For appointments, see
[Appointments](government-appointments.md).

## Create a personnel action

**Government → Personnel Actions**

1. Select the employee.
2. Choose the action type: promotion, transfer, detail, reassignment, separation, or another supported
   type.
3. Record:
   - the current appointment,
   - the target plantilla item, position, or office,
   - the reason,
   - the proposed and effective dates,
   - the supporting document where required.
4. Save. The action starts as `draft`.

## Approve and complete

Use **Advance Action** to move the action through:

```text
draft → submitted → under_review → approved → completed
```

`rejected` and `cancelled` record the other outcomes. Invalid transitions are rejected.

When the action reaches **completed**:

- the employee's position and organization assignment are updated;
- plantilla occupancy is released or reassigned where applicable;
- the appointment and service record reflect the change;
- the action remains in the audit trail.

An action at `approved` is not yet effective. Do not stop at approval if occupancy or the employee record
must change.

## Common action types

| Action | Typical effect |
| --- | --- |
| **Promotion** | Moves the employee to a higher plantilla item or salary grade |
| **Transfer** | Moves the employee to another office or agency |
| **Detail** | Temporary assignment without changing the permanent appointment |
| **Reassignment** | Moves the employee to another position at the same level |
| **Separation** | Ends the appointment; complete clearance and final pay separately |

## Documents and audit

Attach the action order, approval, and supporting documents. Personnel actions are sensitive records;
access is limited by the **Employees** permission and the standard tenant scope. Every transition writes an
audit event.

## Common problems

| Problem | What to check |
| --- | --- |
| Personnel action cannot be created | The employee and action type are required. |
| Target plantilla item is not selectable | The item must be active and unoccupied for the effective date. |
| Advance Action is rejected | The target status is not allowed from the current status. |
| Occupancy did not change | The action must reach `completed`. |
| Employee record did not update | Check the effective date and the target position/office. |
| Action is missing from the service record | Confirm the related appointment and that the action is completed. |

## Related documentation

- [Government-sector HR](government-hr.md)
- [Appointments](government-appointments.md)
- [Plantilla and qualifications](government-plantilla.md)
- [HRMPSB](government-hrmpsb.md)
- [Government separation and retirement](government-separation-retirement.md)
