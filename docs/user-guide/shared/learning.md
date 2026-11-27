---
description: "Manage the OpenHR PH learning catalogue: courses, scheduled sessions, capacity-aware enrollment, completion recording and employee certifications."
---

# Learning and certifications

This page covers the shared **Learning** menu. Training history is visible to HR and to the employee in
the portal.

## Courses

**Learning → Courses**

1. Create the course with a code, title, provider, category, duration, and cost.
2. Add a description that employees and nominators can use.
3. Keep the course active to make it available for scheduling.

The course catalogue is separate from the government PDS training records, which remain part of the
employee's official service record.

## Sessions

**Learning → Sessions**

1. Select the course.
2. Set the start and end date/time, location, capacity, and cost.
3. Choose the status: `scheduled`, `completed`, or `cancelled`.

Only a **scheduled** session accepts enrollments. Capacity counts active enrollments and ignores withdrawn
ones. A session with no capacity is unlimited.

## Enrollments

**Learning → Enrollments**

1. Select the session and employee.
2. Save. The enrollment starts as `enrolled` (or `nominated` if your workflow uses nomination).
3. If the employee is already enrolled, the existing record is returned instead of creating a duplicate.
4. When the session is full, new enrollments are rejected with a capacity error.

### Record completion

Use **Record Completion** on an enrollment:

| Status | Meaning |
| --- | --- |
| `attended` | Attended but not yet completed |
| `completed` | Completed successfully |
| `failed` | Did not meet the requirement |
| `withdrawn` | Withdrew from the session |

Record the score and evaluation where available. The completion date is set automatically.

## Certifications

**Learning → Certifications**

1. Select the employee.
2. Record the certification type, title, issuer, number, issue date, and expiry date.
3. Attach the certificate document where available.
4. Set the status: `active`, `expired`, or `revoked`.

Certifications expiring within 30 days appear in:

- the `hr:reminders` notification run;
- **Reports → Workforce Analytics**;
- the employee's **Portal → Training** page.

## Common problems

| Problem | What to check |
| --- | --- |
| A session cannot be selected | It must be scheduled; cancelled or completed sessions do not accept enrollments. |
| Enrollment says the session is full | Increase capacity or withdraw another enrollment. |
| Completion fails | The status must be attended, completed, failed, or withdrawn. |
| A certification reminder is missing | The certification must be active, have an expiry date, and expire within 30 days. |
| A course is missing from the session form | The course must exist and be active in the same company. |

## Related documentation

- [Performance](performance.md)
- [Employee self-service](employee-self-service.md)
- [Workforce analytics](workforce-analytics.md)
- [HR, workforce and talent overview](hr-and-workforce.md)
