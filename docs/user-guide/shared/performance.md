---
description: "Run OpenHR PH performance cycles: activate cycles, set and agree goals, start reviews, record ratings, finalize and acknowledge reviews, and manage development plans."
---

# Performance

This page covers the shared **Performance** menu. Performance data is tenant-scoped and visible to HR and
to the employee through the portal.

## Performance cycles

**Performance → Performance Cycles**

1. Create a cycle with a name, sector, period start and end, and optional template version.
2. Activate the cycle. Goals and reviews can only be created in an active cycle.
3. Keep one cycle open per review period. A finalized review cannot be reopened; use a new cycle for a
   re-assessment.

Statuses: `draft`, `active`, `calibration`, `completed`, `cancelled`. Only a draft cycle can be activated.

## Goals

**Performance → Goals**

1. Select the cycle and employee.
2. Record the goal title, measures, target, weight, and due date.
3. Agree the goals with the employee. Agreeing moves the employee's draft goals to `agreed`.
4. Update goal status as work progresses: `draft`, `agreed`, `in_progress`, `completed`, `cancelled`.

Weights are percentages or relative weights. The review calculation uses them when computing the weighted
overall score.

## Reviews and ratings

### Start a review

1. Open **Performance → Reviews** or use **Start Review** on a cycle.
2. Select the employee and, optionally, the reviewer.
3. The review starts as `draft`. Only one review per employee per cycle is created.

### Record ratings

**Performance → Ratings**

1. Choose the review.
2. Record each criterion, score, weight, and comments.
3. Recording a rating moves the review to `under_review`.

The overall rating is the weighted average of criterion scores. If weights are missing, the average score
is used.

### Finalize and acknowledge

1. Open **Performance → Reviews** and use **Finalize Review**.
2. Optionally enter an overall rating override. Leave it blank to use the weighted calculation.
3. At least one rating is required. Once finalized, the review is immutable and the overall rating and
   finalization time are recorded.
4. The employee acknowledges the finalized review from **Portal → Performance**. Acknowledgment records the
   timestamp and moves the review to `acknowledged`.

## Development plans

**Performance → Development Plans**

Create a plan for an employee with a title, goals, due date, and optional cycle. Plans move through
`draft`, `active`, `completed`, and `cancelled`. The employee can view their plans in the portal.

## Status reference

| Status | Meaning |
| --- | --- |
| `draft` | Created but not started |
| `self_assessment` | Employee submitted a self-assessment |
| `submitted` | Awaiting manager review |
| `under_review` | Ratings are being recorded |
| `calibrated` | Calibration completed |
| `finalized` | Final rating recorded; immutable |
| `acknowledged` | Employee acknowledged the result |

## Common problems

| Problem | What to check |
| --- | --- |
| Goals cannot be added | The cycle must be active. |
| Review cannot start | The cycle must be active, and a review may already exist for the employee. |
| Finalize fails | At least one criterion rating is required. |
| Review cannot be edited | It is already finalized. Start a new cycle for reassessment. |
| Employee cannot acknowledge | The review must be finalized first. |
| Overall score looks wrong | Check the criterion weights; an override may have been used. |

## Related documentation

- [Learning](learning.md)
- [Employee self-service](employee-self-service.md)
- [Workforce analytics](workforce-analytics.md)
- [HR, workforce and talent overview](hr-and-workforce.md)
