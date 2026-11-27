---
description: "Directory of OpenHR PH HR, workforce and talent guides: organization, documents, approvals, corrections, performance, learning, retirement, compliance, analytics and employee self-service."
---

# HR, workforce and talent overview

The HR platform is documented by module instead of in one long page. Pick the guide for the work you are
doing. Every page is written for the admin workspace at `/app` unless it says otherwise.

## Module guides

### People and structure

| Guide | Use it for |
| --- | --- |
| [Employees](employees.md) | Employee records, bank details, documents, schedules, compensation, and import |
| [Organization and positions](organization-and-positions.md) | Organization units, locations, cost centers, positions, requirements, job descriptions, assignments, reporting lines, employment changes |

### Time and attendance

| Guide | Use it for |
| --- | --- |
| [Attendance and requests](attendance-and-requests.md) | Leave, overtime, undertime, official business, attendance certificates, and request approvals |
| [Timesheet management](timesheet-management.md) | Daily timesheet routine, device imports, night shifts, absences, locking and reopening |
| [Workforce corrections and approvals](workforce-corrections.md) | Attendance corrections and the timesheet submit/approve/reject/lock workflow |

### HR administration

| Guide | Use it for |
| --- | --- |
| [Documents and workflow](documents-and-workflow.md) | Document versions, classification, acknowledgments, workflow definitions, delegations, approvals inbox, audit trail |
| [Performance](performance.md) | Cycles, goals, reviews, ratings, finalization, acknowledgment, development plans |
| [Learning and certifications](learning.md) | Courses, sessions, enrollments, completion, certifications |
| [Retirement](retirement.md) | Retirement plans, eligibility, cases, benefit and terminal-leave calculations, external claims |
| [Compliance](compliance.md) | Compliance sources, rules, rule versions, review, approval, effective-date resolution |
| [Workforce analytics](workforce-analytics.md) | Headcount, hires, separations, retirement forecasts, completion rates, compliance exposure |
| [Employee self-service](employee-self-service.md) | Portal dashboard, requests, performance, training, documents, attendance report |

### Sector guides

| Guide | Use it for |
| --- | --- |
| [Private-sector HR](../private/private-hr.md) | Private menu overview and links to recruitment, employment, separation, and company retirement plans |
| [Private recruitment to hire](../private/private-recruitment.md) | Requisitions, vacancies, applications, screening, interviews, assessments, offers, hire conversion, onboarding |
| [Private employment](../private/private-employment.md) | Employments, contracts, probation, regularization, onboarding tasks, private leave policy rules |
| [Private separation](../private/private-separation.md) | Separation cases, clearance, final settlement, effective separation |
| [Government-sector HR](../government/government-hr.md) | Plantilla, qualification standards and screening, appointments, CSC actions, assumptions, personnel actions, HRMPSB, service records, leave cards, GSIS retirement |

### Payroll and rewards

| Guide | Use it for |
| --- | --- |
| [Payroll](payroll.md) | Payroll groups, compensation catalogue, calculations, processing, approval, and locking |
| [Reports and payslips](reports-and-payslips.md) | Report centre, employee master file, 201 file, payroll register, payslips, remittances |

## Shared behaviour you will see across modules

### Approvals and audit

High-risk operations use the workflow engine and append-only audit trail. Approvals create immutable
decisions. Correction decisions, timesheet approvals, finalized performance reviews, approved retirement
calculations, and approved compliance rule versions cannot be edited or deleted.

### Documents

Documents are stored with immutable versions and a classification. Employees can acknowledge and download
documents they own. Confidential and restricted documents are not exposed to general document users.

### Tenancy

Every HR record belongs to a company. Switching companies changes the records you see. Reports and menus
apply the same tenant scope as the operational screens.

### Permissions

Most HR menus require the **Employees** permission. Payroll menus require payroll permissions, and document
classification requires document-management permission for confidential and restricted files. If a menu is
missing, check the user's group before changing the data.

## Where to start

- **Setting up a new company** — [Organization and positions](organization-and-positions.md), then
  [Employees](employees.md).
- **Hiring** — [Private recruitment](../private/private-recruitment.md) or [Government-sector HR](../government/government-hr.md).
- **Fixing attendance before payroll** — [Workforce corrections and approvals](workforce-corrections.md).
- **Running reviews and training** — [Performance](performance.md) and [Learning and certifications](learning.md).
- **Planning retirement or checking compliance** — [Retirement](retirement.md) and [Compliance](compliance.md).
- **Employee questions** — [Employee self-service](employee-self-service.md).

## Related documentation

- [Release notes — November 2026](../../releases/2026-11-hr-platform.md)
- [HR platform modules — developer reference](../../developer-guide/hr-platform.md)
- [User guide home](../README.md)
