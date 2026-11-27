---
description: "Set up OpenHR PH organization units, locations, cost centers, positions, requirements, job descriptions, assignments, reporting lines and employment changes."
---

# Organization and positions

This page covers the shared **HR** menu. It is available to both Private and Government installations.
Sector-specific screens are covered in the [Private-sector HR](../private/private-hr.md) and
[Government-sector HR](../government/government-hr.md) guides.

## Before you start

- Decide the top-level structure first: company → division → department → section/unit.
- Collect the location and cost-center list from finance.
- Have the position catalogue and job descriptions ready. Importing employees later is easier when the
  positions already exist.

Existing offices and departments are bridged to organization units during migration. Old records remain
visible; new records should use the unified structure.

## Organization units

**HR → Organization Units**

1. Create the top-level unit (for example, the company or agency).
2. Add child units and choose the correct unit type (division, department, section, team, or the types
   available in your installation).
3. Set a unique code for each unit. Codes are used in filters, imports, and reports.
4. Move a unit only when the hierarchy is being reorganized; moves are validated to prevent a unit from
   becoming its own ancestor.

Unit hierarchy drives approvals when an approver scope is defined by organization unit. Changing the
hierarchy can change who approves future requests, so review workflow definitions after a large
reorganization.

## Locations and cost centers

**HR → Locations** and **HR → Cost Centers**

- A location records where work is performed. Use it when attendance rules, holidays, or reporting
  differ by site.
- A cost center records who pays for the position. Finance normally owns this list.
- Both can be referenced by positions and position assignments.

Assignments keep the location and cost center that applied at the time, so a later reorganization does
not rewrite history.

## Positions

**HR → Positions**

1. Create the position with its code, title, organization unit, location, cost center, and employment
   type.
2. Record whether the position is plantilla-funded (Government) or budgeted (Private) where the fields
   apply.
3. Use **HR → Position Requirements** to record education, experience, training, licenses, and
   eligibility requirements.
4. Use **HR → Job Descriptions** to attach the duties, responsibilities, and competencies. Job
   descriptions can be versioned by effective date.

A position is a catalogue entry. The person holding it is recorded through a position assignment, not by
editing the position.

## Position assignments

**HR → Position Assignments**

1. Select the employee and position.
2. Set the effective-from date and, when known, the effective-to date.
3. Save. The previous assignment for that position is closed, and the new assignment becomes current.
4. Overlapping assignments for the same position are rejected. If an employee holds two positions, record
   each assignment separately.

Assignment history preserves the organization unit, location, and cost center in effect. Use it for
reorganizations, acting appointments, and audits.

## Reporting relationships

**HR → Reporting Relationships**

Record the supervisor relationship with effective dates. Reporting lines are used by:

- the workflow **ApproverResolver** when an approval step uses the reporting line;
- employee and organization reports;
- government personnel actions where the current appointment matters.

End a reporting relationship with an effective-to date instead of deleting it.

## Employment changes

**HR → Employment Changes**

Use employment changes for promotions, transfers, acting designations, salary changes, and other
effective-dated movements. Each change keeps:

- the change type and reason,
- the old and new values,
- the effective date,
- the approver and supporting document where required.

Employment changes are the audit-friendly alternative to editing an employee's position or salary field
directly. The employee's current values are updated when the change takes effect.

## Common problems

| Problem | What to check |
| --- | --- |
| A unit cannot be moved | The target is a child of the unit, which would create a cycle. |
| A position cannot be assigned | Another current assignment exists; close it first or end-date it. |
| The wrong person receives an approval | Check the reporting relationship and the workflow definition's approver scope. |
| An employee's old department appears on a report | The assignment history is correct; check the report's as-of date. |
| A position is missing from the employee form | The position must be active and belong to the same company. |

## Related documentation

- [HR, workforce and talent overview](hr-and-workforce.md)
- [Employees](employees.md)
- [Documents and workflow](documents-and-workflow.md)
- [Private-sector HR](../private/private-hr.md)
- [Government-sector HR](../government/government-hr.md)
