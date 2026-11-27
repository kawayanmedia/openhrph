---
slug: /releases/2026-11-hr-platform
description: "Release notes for the five-phase OpenHR PH HR platform expansion: shared foundation, government and private HR, workforce and talent, retirement, analytics and compliance."
---

# HR platform expansion — November 2026

This release adds a complete HR platform on top of the existing employee, attendance, leave, and payroll
features. It was delivered in five phases and is documented here as one release so upgrade notes and
cross-phase behaviour are in one place.

## What is new

| Phase | Capability | Main surfaces |
| --- | --- | --- |
| 1 | Shared HR foundation: organization structure, positions, documents, workflow, audit | HR, Documents, Workflow menus |
| 2 | Government HR: plantilla, appointments, personnel actions, HRMPSB | **Government** menu |
| 3 | Private HR: recruitment to hire, employment administration, separation | Recruitment, Employment, Separation menus |
| 4 | Workforce and talent: attendance corrections, timesheet approval, performance, learning, employee self-service | Workforce, Performance, Learning menus and the employee portal |
| 5 | Retirement, analytics and compliance | Retirement and Compliance menus, Workforce Analytics report |

All five phases are tenant-scoped. Government-only menus appear only when the installation's sector is
Government; private-sector menus appear only for Private. Shared HR menus are available to both.

## Upgrade notes

- Run `php artisan migrate`. The new migrations are guarded with `Schema::hasTable` and
  `Schema::hasColumn`, so the same command upgrades an existing installation.
- No existing tables were destructively changed. The only alterations are additive columns on
  `timesheets`, `job_vacancies`, and `employees` (lifecycle/organization links), plus the app-side
  subclasses for government plantilla items and service records.
- Keep the scheduler running. `hr:reminders` now also reports retirement cases due within 90 days,
  external claims not checked in 30 days, certifications expiring in 30 days, and compliance rules
  expiring in 90 days.
- Assign the new admin permissions to the groups that need them. Menus are gated by
  `can => 'employees'`; retirement-case visibility additionally accepts an HR `Retirement`
  permission or record ownership.
- The employee portal navigation gains **Performance**, **Training**, and **Documents**. Employees
  can acknowledge and download documents they own; confidential documents belonging to another
  employee remain inaccessible.

### New migrations

| Migration | Purpose |
| --- | --- |
| `2026_11_27_000100_create_organization_units_tables` | Organization units, locations, cost centers, employee lifecycle links |
| `2026_11_27_000200_create_position_history_tables` | Position requirements, assignments, reporting lines, job descriptions, employment changes |
| `2026_11_27_000300_create_document_tables` | Documents, immutable versions, access logs, acknowledgments |
| `2026_11_27_000400_create_workflow_tables` | Workflow definitions, instances, tasks, decisions, delegations |
| `2026_11_27_000500_create_audit_events_table` | Append-only audit trail |
| `2026_11_27_000600_create_notifications_table` | Database notifications for approvals and reminders |
| `2026_11_27_000700_create_plantilla_version_tables` | Versioned plantilla and plantilla items |
| `2026_11_27_000710_create_appointment_tables` | Appointments, documents, actions, assumptions, personnel actions |
| `2026_11_27_000720_create_government_committee_tables` | HRMPSB committees, members, deliberations |
| `2026_11_27_000800_create_private_recruitment_tables` | Requisitions, extended vacancies, applications, screening, interviews, assessments, offers |
| `2026_11_27_000810_create_employment_tables` | Employments, contracts, probation reviews, regularization, onboarding, leave policy rules |
| `2026_11_27_000820_create_separation_tables` | Separation cases, clearance templates/tasks, final settlements |
| `2026_11_27_000900_create_attendance_correction_tables` | Attendance corrections/decisions, timesheet approval history, approval columns on `timesheets` |
| `2026_11_27_000910_create_performance_tables` | Performance cycles, goals, reviews, ratings, development plans |
| `2026_11_27_000920_create_learning_tables` | Training courses, sessions, enrollments, employee certifications |
| `2026_11_27_001000_create_retirement_tables` | Retirement plans, cases, calculations, documents, external claims |
| `2026_11_27_001010_create_compliance_tables` | Compliance sources, rules, versioned configurations |
| `2026_11_27_001020_add_company_id_to_compliance_rule_versions` | Tenant isolation for rule versions |

## Phase 1 — Shared HR foundation

### Organization structure

- New `organization_units`, `locations`, and `cost_centers` tables with tenant ownership and hierarchy.
- Existing `departments` and `offices` are bridged to organization units and backfilled, so legacy
  records and reports keep working while new records use the unified structure.
- `OrganizationUnitService`
  owns creation, hierarchy moves, and cycle prevention.

### Position history

- `positions` are enriched with requirements, job descriptions, effective-dated assignments, reporting
  relationships, and employment changes.
- `PositionAssignmentService`
  keeps one current occupant per position, closes prior assignments, and prevents overlapping
  effective dates.
- Assignment history preserves the organization unit, location, and cost center at the time of the
  assignment so reorganizations do not rewrite history.

### Documents

- `DocumentService`
  stores metadata in the database and bytes on a private disk.
- Versions are immutable; editing creates a new version with its own checksum, uploader, and timestamp.
- Classification (`public`, `internal`, `confidential`, `restricted`) drives access. Downloads and
  sensitive access are written to `document_access_logs`.
- Employees acknowledge documents through `document_acknowledgments`; the portal exposes their own
  documents, download, and acknowledgment.

### Workflow engine

- `WorkflowEngine`
  supports versioned definitions, sequential and parallel steps, task assignment by user or approver
  scope, due dates, delegations, and immutable decisions.
- `ApproverResolver`
  resolves reporting-line and permission-based approvers.
- `WorkflowDefinitionValidator`
  rejects malformed step graphs before a definition is activated.
- The admin **Approvals** inbox lists pending tasks; a notification bell surfaces assignment and due-date
  notifications.

### Audit trail

- `AuditLogger`
  writes append-only `audit_events`. High-risk decisions, approvals, corrections, and calculations
  record actor, before/after payloads, and correlation metadata without storing document bytes or
  secrets.

## Phase 2 — Government HR

- Versioned **plantilla** (`plantilla_versions`, `plantilla_items`) with approval actions and occupancy
  checks. `PlantillaService`
  owns publishing and item lifecycle.
- **Qualification standards and screening** with `QualificationScreeningService`.
- **Appointments** with a state machine, supporting documents, appointment actions, and assumption of
  duty in `AppointmentService`.
- **Personnel actions** (promotion, transfer, detail, separation actions) in
  `PersonnelActionService`,
  including CSC action recording and occupancy handling.
- **HRMPSB** committees, members, and deliberations for selection boards.
- Government `PlantillaItem` and `ServiceRecord` are extended through app subclasses and the
  `config/government.php` model map, so PDS pages and existing package relations keep resolving.

## Phase 3 — Private HR

### Recruitment and onboarding

- Job requisitions with approval transitions, extended vacancies, applications, screening results,
  interviews and evaluations, assessments, selection recommendations, and job offers.
- `RecruitmentService`
  owns the pipeline, offer acceptance, applicant-to-employee conversion, and onboarding checklist
  generation.
- Legacy `job_vacancies.type` remains satisfied through service defaults, so existing recruitment
  screens and imports do not break.

### Employment administration

- Employments, contracts, probationary reviews, regularization decisions, onboarding checklists and
  tasks, and private leave policy rules.
- `EmploymentService`
  manages start, probation, regularization, contract renewals, and effective-dated changes.
- `PrivateLeavePolicyService`
  resolves private leave accrual and eligibility rules without hard-coding company policy.

### Separation

- Resignation, termination, redundancy, end-of-contract, retirement, and other separation cases with
  clearance templates/tasks and itemised final settlements.
- `SeparationService`
  generates clearance tasks, records reviewed final-pay inputs, and marks the employee separated only
  when the case becomes effective.

## Phase 4 — Workforce and talent

### Attendance corrections and timesheet approval

- `AttendanceCorrectionService`
  follows request → approve/reject → apply. Applying writes a new punch with `source=correction`; the
  original device punch is never overwritten and is preserved with its original values.
- `TimesheetApprovalService`
  implements submit → approve/reject → payroll lock with an immutable `timesheet_approvals` history.
- Decisions and approval rows throw on update/delete to preserve the audit trail.

### Performance

- `PerformanceService`
  manages cycles, goals and goal agreement, reviews, criterion ratings, weighted final scores,
  optional overall overrides, finalization, and employee acknowledgment.
- Finalized reviews are immutable; a new cycle is required to reassess.

### Learning and certifications

- `LearningService`
  manages the course catalogue, sessions, capacity-aware enrollment, completion recording, and
  certification issuance.
- Expiring certifications feed `hr:reminders` and the analytics report.
- The course model is `App\Models\LearningCourse` (table `training_courses`) to avoid colliding with the
  government PDS `TrainingCourse` model.

### Employee self-service

- The portal gains **Performance** (goals, reviews, development plans, acknowledge finalized review),
  **Training** (enrollments, scores, certifications), and **Documents** (own documents, download,
  acknowledge).
- Portal document access is authorized by `DocumentPolicy`, which now allows an employee to view their
  own documents while keeping other employees' confidential documents restricted.

## Phase 5 — Retirement, analytics and compliance

### Retirement

- `RetirementService`
  owns the case lifecycle: `draft → eligibility_review → applied → approved → completed` (or
  `cancelled`), plus document attachment and external claim tracking.
- `RetirementEligibilityService`
  evaluates age, service years, and plan effective dates, and stores an advisory snapshot on the case.
- Configurable `RetirementPlan`
  records support defined-benefit, defined-contribution, statutory, and GSIS-style plans.
- Benefit formulas are inert JSON. Supported shapes are `percentage_of_salary`, `fixed_amount`, and
  `accumulated_contributions`; the service never evaluates user-supplied code.
- Every `RetirementCalculation`
  stores its inputs, outputs, and rule version. Calculations start as `draft`, must be reviewed, and
  are immutable once approved. A case cannot be completed without an approved benefit calculation.
- Terminal leave is a separate calculation type that records reviewed days and daily rate.
- `ExternalClaimTracking`
  tracks GSIS/SSS submission through release. The system prepares and tracks claims; it does not assume a
  public government API exists.

### Compliance register

- `ComplianceRegistry`
  stores authoritative sources, rule codes, sector/organization/classification dimensions, effective
  dates, and versioned JSON configurations.
- A version moves `draft → reviewed → approved`. Approved versions are immutable and tenant-scoped.
- Resolution is as-of-date and sector-aware, returns the approved configuration, and raises a
  validation error rather than guessing when no approved rule is effective or when rules conflict.
- Executable keys (`php`, `eval`, `script`) are rejected.

### Analytics

- `WorkforceAnalytics`
  reports active headcount, 90-day hires, YTD separations, pending and 12-month retirement forecasts,
  training and review completion rates, pending attendance corrections, open external claims, expiring
  certifications, and compliance exposure.
- The report is available at **Reports → Workforce Analytics** (`/app/reports/workforce-analytics`) and
  applies the same tenant scope as the rest of the admin.

### Reminders

`hr:reminders` now covers:

- workflow tasks due in 3 days,
- documents expiring in 30 days,
- certifications expiring in 30 days,
- retirement cases due in 90 days,
- external claims not checked in 30 days,
- compliance rules expiring in 90 days.

## Validation

The complete suite was run after the final phase:

```bash
vendor/bin/pint --dirty --format agent
php artisan test --compact
```

Result: **834 passed, 3 skipped, 4249 assertions**.

Phase test locations:

- `tests/Feature/Hr/` — organization, positions, documents, workflow, audit, reminders
- `tests/Feature/Hr/Government/` — plantilla, screening, appointments, personnel actions
- `tests/Feature/Hr/Private/` — recruitment, employment, leave policy, separation
- `tests/Feature/Hr/Workforce/` — corrections, timesheet approval, performance, learning, portal
- `tests/Feature/Hr/Retirement/`, `Compliance/`, `Analytics/` — Phase 5 domain and admin screens

## Known limitations

- **No token-authenticated public API yet.** The application does not ship Sanctum or Passport, and
  adding an authentication dependency needs an explicit decision. Government integration is handled
  through claim tracking, calculations, documents, and reminders. See the
  [developer guide](../developer-guide/hr-platform.md#api-and-integration-boundaries) for the intended
  API contract.
- **Benefit formulas are intentionally limited.** New formula types require a reviewed change to
  `RetirementService`, not user-entered expressions.
- **Compliance configurations are data, not executed formulas.** Consumers must read the configuration
  and apply it through vetted code.

## Related documentation

- [HR platform modules — developer reference](../developer-guide/hr-platform.md)
- [HR, workforce and talent — user guide](../user-guide/hr-and-workforce.md)
- [Private-sector HR — user guide](../user-guide/private-hr.md)
- [Government-sector HR — user guide](../user-guide/government-hr.md)
- [Architecture](../developer-guide/architecture.md)
- [Multi-tenancy](../tenancy.md)
