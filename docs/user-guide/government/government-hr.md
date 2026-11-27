---
description: "Government-sector OpenHR PH guide directory: plantilla, qualifications, appointments, CSC actions, personnel actions, HRMPSB, service records, leave cards, GSIS retirement and government separation."
---

# Government-sector HR

This is the entry point for Philippine national government agencies, LGUs, and GOCCs running OpenHR PH in
**Government** sector with the **PH** jurisdiction. If your installation is Private, start at
[Private-sector HR](../private/private-hr.md). Shared modules — organization, documents, approvals, corrections,
performance, learning, retirement mechanics, compliance, and analytics — are listed in
[HR, workforce and talent overview](../shared/hr-and-workforce.md).

The **Government** menu and these guides apply only when both the sector and jurisdiction are
Government/PH. If the menu is missing, ask your administrator to check **Settings → Sector Settings** and
the installation jurisdiction.

## Government module guides

| Guide | What it covers |
| --- | --- |
| [Plantilla and qualifications](government-plantilla.md) | Plantilla versions, plantilla items, occupancy, qualification standards, qualification screening |
| [Appointments](government-appointments.md) | Appointment records, documents, state machine, CSC actions, assumption of duty |
| [Personnel actions](government-personnel-actions.md) | Promotion, transfer, detail, reassignment, separation actions and occupancy updates |
| [HRMPSB](government-hrmpsb.md) | Committees, members, deliberations and selection-board records |
| [Government records](government-records.md) | Salary grades, NOSA, service records, leave cards, PDS/201 file |
| [Separation and retirement](government-separation-retirement.md) | Resignation, retirement, GSIS, terminal leave, death in service, dropping from rolls, dismissal, clearance, service-record closure |

## Government menu map

| Group | Screens |
| --- | --- |
| Plantilla | Plantilla Versions, Plantilla Items |
| Qualification | Qualification Standards, Qualification Screenings |
| Appointments | Appointments, Appointment Documents, CSC Actions, Assumptions |
| Personnel | Personnel Actions |
| Selection board | HRMPSB Committees, HRMPSB Members, Deliberations |
| Reference | Salary Grades, NOSA, Service Records, Leave Cards |

The same employee record is used by the PDS/201 file and payroll. Government HR screens add the
plantilla, appointment, and personnel-action layer on top of that shared record.

## How the government workflow fits together

```text
Plantilla version → Plantilla item → Qualification standard/screening
                                     ↓
                              Appointment → CSC action → Assumption → Service record
                                     ↓
                              Personnel action (promotion/transfer/detail/…)
                                     ↓
                              Separation/retirement → Clearance → Final settlement
```

- **Plantilla** defines authorized positions.
- **Qualifications and HRMPSB** support the selection decision.
- **Appointments** record the legal appointment, CSC action, and assumption of duty.
- **Personnel actions** record later movements and update occupancy/position history.
- **Service records and leave cards** preserve the official employment history.
- **Separation and retirement** close the record with clearance, final pay, and GSIS tracking.

## Permissions and privacy

- The **Government** menu requires the Government sector and PH jurisdiction on the installation.
- The **Employees** permission controls access to the government HR screens.
- Appointment, personnel-action, retirement, and service-record data is sensitive. Limit access to HR
  staff who need it, and review the audit trail when access changes.
- Documents follow classification and ownership rules; confidential and restricted documents are not
  exposed to general document users.

## Common questions

| Question | Where to look |
| --- | --- |
| Which status comes next on an appointment? | [Appointments](government-appointments.md) |
| Why is a plantilla item not selectable? | [Plantilla and qualifications](government-plantilla.md) |
| Why did occupancy not change after a personnel action? | [Personnel actions](government-personnel-actions.md) |
| Where do I record the CSC response? | [Appointments](government-appointments.md#csc-actions-and-assumption-of-duty) |
| Where are the HRMPSB minutes? | [HRMPSB](government-hrmpsb.md) |
| How do I correct a service record or leave card? | [Government records](government-records.md) |
| How do I process GSIS retirement and terminal leave? | [Separation and retirement](government-separation-retirement.md) |

## Related documentation

- [HR, workforce and talent overview](../shared/hr-and-workforce.md)
- [Private-sector HR](../private/private-hr.md)
- [Employees](../shared/employees.md)
- [Government and Private sectors — developer guide](../../developer-guide/sectors.md)
- [Release notes — November 2026](../../releases/2026-11-hr-platform.md)
