---
description: "Government plantilla and qualification workflows in OpenHR PH: plantilla versions, plantilla items, occupancy, qualification standards and qualification screening."
---

# Plantilla and qualifications

This page covers the plantilla and qualification screens in the **Government** menu. Start at
[Government-sector HR](government-hr.md) for the sector overview.

## Plantilla versions

**Government → Plantilla Versions**

1. Create a version with:
   - a version label (for example `2026-01`),
   - a name,
   - the approval reference,
   - effective-from and effective-until dates,
   - the approval document where available.
2. Use **Approve Version** when the plantilla is authorized.
3. Approved versions are used by appointments, personnel actions, and reports. Retired versions remain
   for history.

Statuses: `draft`, `approved`, `retired`. Only a draft version can be approved.

Do not edit a historical version to reflect a new staffing pattern. Create a new version instead, so
appointments keep pointing at the plantilla that was in force when they were issued.

## Plantilla items

**Government → Plantilla Items**

1. Select the plantilla version.
2. Record the item:
   - item number and position title,
   - office, position, and organization unit,
   - salary grade and step,
   - funded flag and funding source,
   - status and effective dates,
   - qualification standard where applicable.
3. Keep the item linked to its version.

### Occupancy

An appointment or personnel action can occupy a plantilla item. The government services check occupancy
before accepting a new appointment and release the item when the occupant leaves.

If an item cannot be selected:

- confirm the plantilla version is approved;
- confirm the item is active and effective for the appointment date;
- check the current appointment and the item's occupancy fields.

## Qualification standards

**Government → Qualification Standards**

Record the requirements for a position or plantilla item:

- education,
- training, including required hours,
- experience, including required years,
- eligibility,
- license, where the position requires one,
- effective dates and source reference.

Qualification standards can be versioned. Keep the effective dates accurate so screening uses the
standard that applied at the time of the appointment.

## Qualification screening

**Government → Qualification Screenings**

1. Use **Screen Employee** on the screening screen.
2. Select the employee or applicant and the qualification standard.
3. Record the result, assessed requirements, and comments.
4. Save. The screening is available to the appointment and HRMPSB process.

Screening results support the appointment decision; they do not replace the official appointment process
or the CSC action. Attach the supporting documents (transcripts, certificates, licenses) to the employee
or screening record.

## Common problems

| Problem | What to check |
| --- | --- |
| Plantilla item is not selectable | The version must be approved and the item active/effective. |
| Plantilla version cannot be approved | It must be a draft; check required fields and approval reference. |
| Plantilla item is already occupied | End or transition the current appointment before assigning a new one. |
| Screening result is missing from the appointment | Re-run **Screen Employee** and link the screening to the appointment. |
| A qualification standard changed | Create a new version with a new effective date; do not overwrite history. |

## Related documentation

- [Government-sector HR](government-hr.md)
- [Appointments](government-appointments.md)
- [Personnel actions](government-personnel-actions.md)
- [HRMPSB](government-hrmpsb.md)
