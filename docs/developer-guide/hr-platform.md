---
slug: /developer-guide/hr-platform
description: "Developer reference for the OpenHR PH HR platform modules: namespaces, data model, services, admin UI, workflow, tenancy, testing and extension points."
---

# HR platform modules

This page is the developer reference for the HR capabilities added in the
[November 2026 release](../releases/2026-11-hr-platform.md). It covers where the code lives, how the
modules fit together, and how to extend them without breaking sector or jurisdiction boundaries.

## Architectural boundaries

The platform follows the existing repository rules:

- **Country law** lives in `app/Jurisdictions/<Country>` and is resolved through `JurisdictionManager`
  or `Installation::jurisdiction()`. HR modules must not add `if (country === ...)` branches.
- **Sector policy** lives in `app/Sectors/` plus the jurisdiction's `Config/sector.php` and is resolved
  through `SectorManager`. HR modules must not add `if (government)` branches in payroll or UI code.
- **Philippine statutory calculators** stay in `App\Jurisdictions\Philippines\Statutory`. The government
  HR module (`App\Jurisdictions\Philippines\Government`) is policy and records, not statutory payroll.
- **Tenancy** is enforced by `BelongsToTenant` and `TenantContext`. Every new HR table carries
  `company_id` and is scoped by the trait, with deliberate `withoutTenant()` calls inside services that
  need to lock or read across the tenant boundary.
- **Audit** is append-only. High-risk records (`AuditEvent`, `WorkflowDecision`,
  `AttendanceCorrectionDecision`, `TimesheetApproval`) throw on update and delete.

## Code map

| Area | Path |
| --- | --- |
| Shared HR models | `app/Models/` (`OrganizationUnit`, `Location`, `CostCenter`, `PositionAssignment`, `Document`, `WorkflowDefinition`, `AuditEvent`, …) |
| Government models | `app/Jurisdictions/Philippines/Government/Models/` |
| Government services | `app/Jurisdictions/Philippines/Government/Services/` |
| Government admin UI | `app/Jurisdictions/Philippines/Government/Admin/` and the feature-pack menu |
| Private recruitment/employment/separation | `app/Models/`, `app/Services/Recruitment`, `app/Services/Employment`, `app/Services/Separation` |
| Workforce and talent | `app/Services/Attendance`, `app/Services/Performance`, `app/Services/Learning` |
| Retirement | `app/Services/Retirement`, `app/Models/Retirement*`, `app/Models/ExternalClaimTracking` |
| Compliance | `app/Services/Compliance`, `app/Models/Compliance*` |
| Analytics | `app/Services/Analytics`, `app/Livewire/Admin/Reports/WorkforceAnalytics.php` |
| Admin resources | `app/Livewire/Admin/Resources/` |
| Domain actions | `app/Livewire/Admin/Actions/{Government,PrivateSector,Workforce,Retirement,Compliance}/` |
| Employee portal | `app/Livewire/Portal/` and `resources/views/livewire/portal/` |
| Migrations | `database/migrations/2026_11_27_*` |
| Tests | `tests/Feature/Hr/` |

## Data model by phase

### Shared foundation

- `organization_units` (hierarchy, type, parent), `locations`, `cost_centers`.
- `positions` enriched with requirements, job descriptions, effective-dated assignments, reporting
  relationships, and employment changes.
- `documents`, `document_versions`, `document_access_logs`, `document_acknowledgments`.
- `workflow_definitions`, `workflow_instances`, `workflow_tasks`, `workflow_decisions`,
  `workflow_delegations`.
- `audit_events` and database notifications.

### Government

- `plantilla_versions`, `plantilla_items`.
- `appointment_records`, appointment documents/actions/assumptions, `personnel_actions`.
- HRMPSB committees, members, and deliberations.
- App subclasses of the package `PlantillaItem` and `ServiceRecord`, wired through
  `config/government.php`.

### Private

- `job_requisitions`, extended `job_vacancies`, `applications`, `application_documents`,
  `screening_results`, `interviews`, `interview_evaluations`, `assessments`,
  `selection_recommendations`, `job_offers`.
- `employments`, `employment_contracts`, `probationary_reviews`, `regularization_decisions`,
  `onboarding_checklists`, `onboarding_tasks`, `leave_policy_rules`.
- `separation_cases`, `clearance_templates`, `clearance_tasks`, `final_settlements`.

### Workforce and talent

- `attendance_corrections`, `attendance_correction_decisions`, `timesheet_approvals`, plus approval
  columns on `timesheets`.
- `performance_cycles`, `performance_goals`, `performance_reviews`, `performance_ratings`,
  `development_plans`.
- `training_courses`, `training_sessions`, `training_enrollments`, `employee_certifications`.

### Retirement and compliance

- `retirement_plans`, `retirement_cases`, `retirement_calculations`, `retirement_documents`,
  `external_claim_tracking`.
- `compliance_sources`, `compliance_rules`, `compliance_rule_versions`.

## Service catalogue

| Service | Responsibility |
| --- | --- |
| `Organization\OrganizationUnitService` | Unit creation, hierarchy moves, cycle prevention |
| `Organization\PositionAssignmentService` | Effective-dated occupancy, closing prior assignment, history |
| `Documents\DocumentService` | Upload, immutable versions, classification, acknowledgments, download |
| `Workflow\WorkflowEngine` | Definition activation, instance start, task completion, decisions, delegation |
| `Workflow\ApproverResolver` | Resolve approver scopes and reporting lines |
| `Workflow\WorkflowDefinitionValidator` | Reject invalid step graphs |
| `Hr\AuditLogger` | Append-only audit entries |
| `Government\PlantillaService` | Plantilla version publishing and item lifecycle |
| `Government\QualificationScreeningService` | Qualification standard screening |
| `Government\AppointmentService` | Appointment state machine, documents, assumptions |
| `Government\PersonnelActionService` | Promotion/transfer/detail actions and occupancy |
| `Recruitment\RecruitmentService` | Requisition pipeline, offers, hire conversion, onboarding |
| `Employment\EmploymentService` | Employment start, probation, regularization, contract changes |
| `Leave\PrivateLeavePolicyService` | Private leave policy resolution |
| `Separation\SeparationService` | Clearance, final settlement inputs, effective separation |
| `Attendance\AttendanceCorrectionService` | Correction request, decision, apply-as-new-punch |
| `Attendance\TimesheetApprovalService` | Submit, approve/reject, payroll lock |
| `Performance\PerformanceService` | Cycles, goals, reviews, ratings, finalization |
| `Learning\LearningService` | Courses, sessions, capacity-aware enrollment, certifications |
| `Retirement\RetirementEligibilityService` | Age/service/plan-effective-date snapshot |
| `Retirement\RetirementService` | Case lifecycle, calculations, review, claims |
| `Compliance\ComplianceRegistry` | Sources, rules, version review/approval, as-of resolution |
| `Analytics\WorkforceAnalytics` | Tenant-scoped workforce metrics |

## Admin UI conventions

Resources live in `App\Livewire\Admin\Resources` and implement:

- `fields(Request $request)` — form and table columns;
- `cards`, `filters`, `lenses`, `actions` — optional arrays;
- static `$model`, `$group`, `$search`, `$with`, `$sector`, `$jurisdiction`.

Key rules:

- A resource class name that matches its model short name must alias the model import
  (`use App\Models\Document as DocumentModel`).
- Computed field closures bind to the **model**, not the resource. Use `displayUsing` when the value
  needs a resource method.
- `BelongsTo` form keys are relation names (`form.employee`), not foreign keys.
- Read-only resources override `canCreateResource()`, `authorizedToCreate()`, `authorizedToUpdate()`,
  and `authorizedToDelete()`.
- Use explicit short index names for long MySQL composite indexes.

Government resources set `$sector = 'Government'` and `$jurisdiction = 'PH'`; private resources set
`$sector = 'Private'`. Shared HR resources leave both unset so they appear for either sector.

### Menu registration

Shared and private menus are sections in `config/admin.php`. Government menus come from the feature
pack (`App\Jurisdictions\Philippines\Government\GovernmentFeaturePack::adminMenu()`), so the same
resource can appear only when the installation is Government.

### Actions

Actions extend `App\Livewire\Admin\Actions\Action` and declare `fields()` plus `handle()`. Scope an action
with `onlyOnIndexToolbar()`, `onlyOnTableRow()`, `onlyBulk()`, `onlyOnDetail()`, or `onlyOnIndex()`.

Important actions:

| Area | Actions |
| --- | --- |
| Government | `ApprovePlantillaVersion`, `ScreenQualification`, `TransitionAppointment`, `RecordAppointmentAssumption`, `RecordCscAction`, `AdvancePersonnelAction` |
| Private | `TransitionRequisition`, `PublishVacancy`, `ScreenApplication`, `IssueJobOffer`, `RespondToJobOffer`, `HireApplicant`, `StartEmployment`, `RecordRegularizationDecision`, `InitiateSeparation`, `CompleteClearanceTask`, `TransitionSeparation`, `RecordFinalSettlement` |
| Workforce | `DecideAttendanceCorrection`, `ManageTimesheetApproval`, `ActivatePerformanceCycle`, `StartPerformanceReview`, `FinalizePerformanceReview`, `CompleteTrainingEnrollment` |
| Retirement | `OpenRetirementCase`, `ManageRetirementCase`, `ReviewRetirementCalculation`, `ManageExternalClaim` |
| Compliance | `RegisterComplianceRule`, `ManageComplianceRuleVersion` |

## Workflow and audit integration

- Domain services call `AuditLogger::record()` after state changes. Pass the subject model and compact
  before/after arrays; never include file bytes, tokens, or secrets.
- Workflow definitions are data. Validation happens in `WorkflowDefinitionValidator`; activation and
  execution happen in `WorkflowEngine`.
- Immutable models throw `LogicException` on update/delete. Service methods that need to transition an
  immutable record lock it via `withoutTenant()` and update the status columns they own.
- Retirement and compliance approvals follow the same pattern: calculations and rule versions start as
  drafts, are reviewed, and become immutable once approved.

## Tenancy rules

- New tenant tables use `App\Models\Concerns\BelongsToTenant` and `company_id`.
- Services that must act across the global scope (locking, jobs, console commands) use
  `withoutTenant()` deliberately and then restore context.
- Any table without `company_id` must be scoped through a tenant-owned parent. Compliance rule versions
  originally lacked `company_id`; migration `2026_11_27_001020` adds it so the resource cannot leak
  another tenant's configurations.
- Console commands use `App\Console\Concerns\RunsForTenants` and iterate companies explicitly.

## Testing

Run the narrowest suite first:

```bash
php artisan test --compact tests/Feature/Hr
vendor/bin/pint --dirty --format agent
```

The complete suite at the end of the release:

```bash
php artisan test --compact
# 834 passed, 3 skipped, 4249 assertions
```

Testing conventions used by these modules:

- `RefreshDatabase` with SQLite in memory.
- `Company::factory()` plus `User::factory()->forCompany($company)` and `actingAs($user)` to establish
  the tenant.
- Factories for employees, timesheets, positions, and users; direct model creation for the new HR records
  until factories are added.
- Service-level tests for state machines and calculations, resource tests for registry/field wiring, and
  portal tests for employee ownership.
- Immutability is asserted with `expect(fn () => $model->update(...))->toThrow(LogicException::class)`.

## Extension recipes

### Add a new organization type or unit level

1. Extend the unit type constants on `App\Models\OrganizationUnit`.
2. Update `OrganizationUnitService` validation and the resource `Select`.
3. Add a migration only if the new level needs new columns; keep `parent_id` hierarchy intact.
4. Add a test in `tests/Feature/Hr/OrganizationStructureTest.php`.

### Add a government module

1. Create the model and service under `app/Jurisdictions/Philippines/Government/`.
2. Add the resource under the government `Admin/Resources` folder and register it in
   `GovernmentFeaturePack::adminMenu()`.
3. Add the migration with guarded `Schema::hasTable`/`hasColumn` calls.
4. Add service and resource tests under `tests/Feature/Hr/Government/`.

### Add a retirement formula type

1. Choose a new inert JSON shape (for example `{"type":"career_salary","rate":0.02}`).
2. Add the branch to `RetirementService::benefitAmounts()` and include the computed values in `outputs`.
3. Keep the rule version in `rule_version` and never auto-approve the result.
4. Add a fixture/practice test to `tests/Feature/Hr/Retirement/RetirementServiceTest.php`.
5. Document the shape in the retirement plan field help text and the release notes.

### Add a compliance rule

1. Register the authority in **Compliance → Sources**.
2. Register the rule in **Compliance → Rules** with code, sector, effective dates, and inert JSON
   configuration.
3. Submit the version for review, then approve it.
4. Consumers resolve it by code and as-of date:

```php
use App\Services\Compliance\ComplianceRegistry;

$resolved = app(ComplianceRegistry::class)->resolve('OT_PREMIUM', 'Private', $payDate);

$configuration = $resolved['configuration'];
```

Resolution throws a `ValidationException` when no approved rule is effective or when candidates
conflict. Do not fall back to a silent default in compliance-sensitive code.

### Add a workflow-backed HR process

1. Create or update a `workflow_definitions` record with steps and approver scopes.
2. Validate it through `WorkflowDefinitionValidator`.
3. Start an instance from the domain service with `subject_type`/`subject_id`.
4. Resolve approvers with `ApproverResolver`; keep due dates on tasks.
5. Render pending tasks through the shared Approvals inbox.

## API and integration boundaries

The release does not add a token-authenticated public API. The intended contract, when an auth package is
approved, is:

- `/api/v1/retirement/cases` — list and inspect retirement cases scoped to the authenticated tenant.
- `/api/v1/compliance/rules` — resolved, approved configurations as of a requested date.
- `/api/v1/performance/reviews` — read-only review status for integrations.

Requirements for that work:

- scoped authorization per route, mirroring the policies used by the admin;
- tenant and organization isolation;
- request validation and pagination/filtering;
- rate limits and idempotency keys for sensitive create/approve operations;
- consistent error format and audit events;
- versioning/deprecation policy and no mass assignment of protected status fields.

Until then, integration happens through:

- **External claim tracking** for GSIS/SSS, with reference, status, submission, release, and notes.
- **Retirement calculations** with recorded inputs, outputs, and rule versions.
- **Documents** attached to a case with classification and access logging.
- **Reminders** for due cases, stale claims, expiring certifications, and expiring compliance rules.
- **CSV exports** through the existing report/export patterns where a controlled extract is needed.

## Related documentation

- [Release notes — November 2026](../releases/2026-11-hr-platform.md)
- [HR platform modules — developer reference](hr-platform.md)
- [HR, workforce and talent — user guide](../user-guide/hr-and-workforce.md)
- [Private-sector HR — user guide](../user-guide/private-hr.md)
- [Government-sector HR — user guide](../user-guide/government-hr.md)
- [Architecture](architecture.md)
- [Admin panel](admin-panel.md)
- [Multi-tenancy](../tenancy.md)
- [Testing](testing.md)
