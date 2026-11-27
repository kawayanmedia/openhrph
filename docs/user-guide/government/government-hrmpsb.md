---
description: "HRMPSB workflows in OpenHR PH: create selection-board committees, add members, record deliberations, and link results to appointments."
---

# HRMPSB

This page covers the **HRMPSB** screens in the **Government** menu. HRMPSB is the Human Resource
Merit Promotion and Selection Board process for government selection and promotion.

## Create a committee

**Government → HRMPSB Committees**

1. Create the committee for the selection round.
2. Record the committee name, purpose, effectivity, and status.
3. Attach the office order or constituting authority in the document repository.

Use a new committee record for each selection round or when the membership changes; do not overwrite the
members of a past round.

## Add members

**Government → HRMPSB Members**

1. Select the committee.
2. Add the chair and members.
3. Record each member's role (chair, member, secretariat, observer).
4. Set the term or effectivity where applicable.

Membership is part of the selection record. Keep the member list fixed for a round once deliberations
start.

## Record deliberations

**Government → Deliberations**

1. Select the committee and the position or plantilla item.
2. Record the date and the candidates considered.
3. Record the deliberation outcome and recommendation.
4. Attach the minutes and supporting documents.

Deliberations and qualification screening results support the appointment record. They do not replace the
official appointment, CSC action, or the agency's selection process.

## How it connects to appointments

```text
Vacancy/plantilla item → Qualification screening → HRMPSB deliberation
                       → Appointment record → CSC action → Assumption
```

- Use [Plantilla and qualifications](government-plantilla.md) to screen candidates.
- Use [Appointments](government-appointments.md) to record the appointment that follows the board's
  recommendation.
- Attach the HRMPSB minutes to the appointment or the document repository.

## Common problems

| Problem | What to check |
| --- | --- |
| Committee is not selectable | It must exist for the same company and be effective for the deliberation date. |
| Member was added to the wrong round | Create a new committee record; do not edit historical membership. |
| Deliberation cannot be saved | The committee, position/plantilla item, and date are required. |
| Minutes are missing | Attach the signed minutes in the document repository and link them to the deliberation. |
| Appointment does not reference the board | Link the screening and deliberation records to the appointment. |

## Related documentation

- [Government-sector HR](government-hr.md)
- [Plantilla and qualifications](government-plantilla.md)
- [Appointments](government-appointments.md)
- [Documents and workflow](../shared/documents-and-workflow.md)
