<?php

namespace App\Services\Organization;

use App\Models\Employee;
use App\Models\EmploymentChange;
use App\Models\PositionAssignment;
use App\Models\ReportingRelationship;
use App\Models\User;
use App\Services\Hr\AuditLogger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Applies effective-dated employee placements.
 *
 * The previous assignment is closed the day before the new one starts, the
 * employee's current-value columns are updated for payroll/attendance, and an
 * employment change plus audit event preserves the movement history.
 */
class PositionAssignmentService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function assign(Employee $employee, array $attributes, ?User $actor = null): PositionAssignment
    {
        $from = Carbon::parse($attributes['effective_from'])->startOfDay();
        $until = $attributes['effective_until'] ?? null;
        $until = $until === null || $until === '' ? null : Carbon::parse($until)->startOfDay();

        if ($until !== null && $until->lt($from)) {
            throw ValidationException::withMessages([
                'effective_until' => 'The end date must be on or after the start date.',
            ]);
        }

        return DB::transaction(function () use ($employee, $attributes, $from, $until, $actor): PositionAssignment {
            $employee = Employee::withoutTenant()
                ->whereKey($employee->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $overlapping = PositionAssignment::withoutTenant()
                ->where('company_id', $employee->company_id)
                ->where('employee_id', $employee->getKey())
                ->where('effective_from', '<=', ($until ?? Carbon::parse('9999-12-31'))->toDateString())
                ->where(fn ($query) => $query->whereNull('effective_until')
                    ->orWhere('effective_until', '>=', $from->toDateString()))
                ->orderBy('effective_from')
                ->get();

            if ($overlapping->contains(fn (PositionAssignment $assignment) => $assignment->effective_from->toDateString() === $from->toDateString())) {
                throw ValidationException::withMessages([
                    'effective_from' => 'An assignment already starts on this date.',
                ]);
            }

            if ($overlapping->count() > 1) {
                throw ValidationException::withMessages([
                    'effective_from' => 'Existing assignments overlap. Correct the history before creating a new assignment.',
                ]);
            }

            $previous = $overlapping->first()
                ?? PositionAssignment::withoutTenant()
                    ->where('company_id', $employee->company_id)
                    ->where('employee_id', $employee->getKey())
                    ->where('effective_from', '<', $from->toDateString())
                    ->orderByDesc('effective_from')
                    ->first();

            if ($overlapping->first() !== null) {
                $overlapping->first()->forceFill([
                    'effective_until' => $from->copy()->subDay(),
                    'status' => PositionAssignment::STATUS_ENDED,
                ])->save();
            }

            $before = $this->employeeSnapshot($employee);

            $assignment = PositionAssignment::create([
                ...$attributes,
                'company_id' => $employee->company_id,
                'employee_id' => $employee->getKey(),
                'effective_from' => $from->toDateString(),
                'effective_until' => $until?->toDateString(),
                'approved_by' => $attributes['approved_by'] ?? $actor?->getKey(),
                'approved_at' => $attributes['approved_at'] ?? ($actor !== null ? now() : null),
            ]);

            $this->syncEmployee($employee, $assignment);
            $this->recordChange($employee, $assignment, $previous, $attributes);

            $this->audit->record(
                'position_assignment.created',
                $assignment,
                before: $before,
                after: $this->employeeSnapshot($employee->refresh()),
                metadata: ['assignment_id' => $assignment->getKey()],
            );

            return $assignment->refresh();
        });
    }

    /**
     * Finish an assignment created through the generic admin form.
     *
     * The row already exists, so an overlap deletes it and re-raises the
     * validation error instead of leaving an invalid placement behind.
     */
    public function applyCreatedAssignment(PositionAssignment $assignment, ?User $actor = null): PositionAssignment
    {
        return DB::transaction(function () use ($assignment, $actor): PositionAssignment {
            $assignment = PositionAssignment::withoutTenant()->whereKey($assignment->getKey())->lockForUpdate()->firstOrFail();
            $employee = Employee::withoutTenant()->whereKey($assignment->employee_id)->lockForUpdate()->firstOrFail();

            $from = $assignment->effective_from;
            $until = $assignment->effective_until;

            $conflicts = PositionAssignment::withoutTenant()
                ->where('company_id', $assignment->company_id)
                ->where('employee_id', $assignment->employee_id)
                ->whereKeyNot($assignment->getKey())
                ->where('effective_from', '<=', ($until ?? Carbon::parse('9999-12-31'))->toDateString())
                ->where(fn ($query) => $query->whereNull('effective_until')
                    ->orWhere('effective_until', '>=', $from->toDateString()))
                ->orderBy('effective_from')
                ->get();

            if ($conflicts->contains(fn (PositionAssignment $existing) => $existing->effective_from->toDateString() === $from->toDateString())
                || $conflicts->count() > 1) {
                $assignment->delete();

                throw ValidationException::withMessages([
                    'effective_from' => 'The assignment overlaps an existing placement.',
                ]);
            }

            $previous = $conflicts->first()
                ?? PositionAssignment::withoutTenant()
                    ->where('company_id', $assignment->company_id)
                    ->where('employee_id', $assignment->employee_id)
                    ->whereKeyNot($assignment->getKey())
                    ->where('effective_from', '<', $from->toDateString())
                    ->orderByDesc('effective_from')
                    ->first();

            if ($conflicts->first() !== null) {
                $conflicts->first()->forceFill([
                    'effective_until' => $from->copy()->subDay(),
                    'status' => PositionAssignment::STATUS_ENDED,
                ])->save();
            }

            $before = $this->employeeSnapshot($employee);

            $assignment->forceFill([
                'approved_by' => $assignment->approved_by ?? $actor?->getKey(),
                'approved_at' => $assignment->approved_at ?? ($actor !== null ? now() : null),
            ])->save();

            $this->syncEmployee($employee, $assignment);
            $this->recordChange($employee, $assignment, $previous, [
                'change_type' => $assignment->change_type ?? null,
            ]);

            $this->audit->record(
                'position_assignment.created',
                $assignment,
                before: $before,
                after: $this->employeeSnapshot($employee->refresh()),
                metadata: ['assignment_id' => $assignment->getKey()],
            );

            return $assignment->refresh();
        });
    }

    public function end(PositionAssignment $assignment, string $effectiveUntil, ?string $reason = null, ?User $actor = null): PositionAssignment
    {
        return DB::transaction(function () use ($assignment, $effectiveUntil, $reason, $actor): PositionAssignment {
            $assignment = PositionAssignment::withoutTenant()
                ->whereKey($assignment->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $until = Carbon::parse($effectiveUntil)->startOfDay();

            if ($until->lt($assignment->effective_from)) {
                throw ValidationException::withMessages([
                    'effective_until' => 'The end date cannot precede the assignment start date.',
                ]);
            }

            $before = $assignment->getAttributes();

            $assignment->forceFill([
                'effective_until' => $until->toDateString(),
                'status' => PositionAssignment::STATUS_ENDED,
                'approved_by' => $actor?->getKey() ?? $assignment->approved_by,
                'notes' => $reason ?? $assignment->notes,
            ])->save();

            $this->audit->record(
                'position_assignment.ended',
                $assignment,
                before: $before,
                after: $assignment->getAttributes(),
                metadata: ['reason' => $reason],
            );

            return $assignment->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function recordReportingRelationship(Employee $employee, Employee $supervisor, array $attributes = []): ReportingRelationship
    {
        if ((int) $employee->getKey() === (int) $supervisor->getKey()) {
            throw ValidationException::withMessages([
                'supervisor_employee_id' => 'An employee cannot report to themselves.',
            ]);
        }

        $from = Carbon::parse($attributes['effective_from'] ?? now()->toDateString())->startOfDay();

        return DB::transaction(function () use ($employee, $supervisor, $attributes, $from): ReportingRelationship {
            $open = ReportingRelationship::withoutTenant()
                ->where('company_id', $employee->company_id)
                ->where('employee_id', $employee->getKey())
                ->where(fn ($query) => $query->whereNull('effective_until')
                    ->orWhere('effective_until', '>=', $from->toDateString()))
                ->get();

            foreach ($open as $relationship) {
                $relationship->forceFill([
                    'effective_until' => $from->copy()->subDay(),
                ])->save();
            }

            $relationship = ReportingRelationship::create([
                'company_id' => $employee->company_id,
                'employee_id' => $employee->getKey(),
                'supervisor_employee_id' => $supervisor->getKey(),
                'relationship_type' => $attributes['relationship_type'] ?? ReportingRelationship::TYPE_SUPERVISOR,
                'effective_from' => $from->toDateString(),
                'effective_until' => $attributes['effective_until'] ?? null,
                'notes' => $attributes['notes'] ?? null,
            ]);

            $employee->forceFill([
                'immediate_head_employee_id' => $supervisor->getKey(),
            ])->save();

            $this->audit->record(
                'reporting_relationship.created',
                $relationship,
                after: $relationship->getAttributes(),
            );

            return $relationship->refresh();
        });
    }

    /**
     * Close conflicting relationships and project the supervisor onto the
     * employee record after a generic form save.
     */
    public function applyReportingRelationship(ReportingRelationship $relationship): void
    {
        DB::transaction(function () use ($relationship): void {
            $from = $relationship->effective_from;

            ReportingRelationship::withoutTenant()
                ->where('company_id', $relationship->company_id)
                ->where('employee_id', $relationship->employee_id)
                ->whereKeyNot($relationship->getKey())
                ->where(fn ($query) => $query->whereNull('effective_until')
                    ->orWhere('effective_until', '>=', $from->toDateString()))
                ->get()
                ->each(function (ReportingRelationship $existing) use ($from): void {
                    $existing->forceFill([
                        'effective_until' => $from->copy()->subDay(),
                    ])->save();
                });

            $relationship->employee?->forceFill([
                'immediate_head_employee_id' => $relationship->supervisor_employee_id,
            ])->save();

            $this->audit->record(
                'reporting_relationship.applied',
                $relationship,
                after: $relationship->getAttributes(),
            );
        });
    }

    protected function syncEmployee(Employee $employee, PositionAssignment $assignment): void
    {
        $position = $assignment->position;

        $employee->forceFill([
            'position_id' => $assignment->position_id,
            'position' => $position?->name ?? $employee->position,
            'department_id' => $assignment->department_id,
            'office_id' => $assignment->office_id,
            'organization_unit_id' => $assignment->organization_unit_id,
            'location_id' => $assignment->location_id,
            'cost_center_id' => $assignment->cost_center_id,
            'employment_type_id' => $assignment->employment_type_id ?? $employee->employment_type_id,
            'immediate_head_employee_id' => $assignment->supervisor_employee_id ?? $employee->immediate_head_employee_id,
        ])->save();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function recordChange(
        Employee $employee,
        PositionAssignment $assignment,
        ?PositionAssignment $previous,
        array $attributes,
    ): EmploymentChange {
        $changeType = $attributes['change_type'] ?? match (true) {
            $previous === null => EmploymentChange::TYPE_HIRE,
            (int) $previous->position_id !== (int) $assignment->position_id => EmploymentChange::TYPE_PROMOTION,
            (int) $previous->organization_unit_id !== (int) $assignment->organization_unit_id => EmploymentChange::TYPE_TRANSFER,
            default => EmploymentChange::TYPE_OTHER,
        };

        return EmploymentChange::create([
            'company_id' => $employee->company_id,
            'employee_id' => $employee->getKey(),
            'change_type' => $changeType,
            'effective_date' => $assignment->effective_from->toDateString(),
            'from_position_id' => $previous?->position_id,
            'to_position_id' => $assignment->position_id,
            'from_organization_unit_id' => $previous?->organization_unit_id,
            'to_organization_unit_id' => $assignment->organization_unit_id,
            'from_supervisor_employee_id' => $previous?->supervisor_employee_id,
            'to_supervisor_employee_id' => $assignment->supervisor_employee_id,
            'reason' => $attributes['reason'] ?? null,
            'approved_by' => $assignment->approved_by,
            'workflow_instance_id' => $attributes['workflow_instance_id'] ?? null,
            'metadata' => ['assignment_id' => $assignment->getKey()],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function employeeSnapshot(Employee $employee): array
    {
        return [
            'position_id' => $employee->position_id,
            'position' => $employee->position,
            'department_id' => $employee->department_id,
            'office_id' => $employee->office_id,
            'organization_unit_id' => $employee->organization_unit_id,
            'location_id' => $employee->location_id,
            'cost_center_id' => $employee->cost_center_id,
            'employment_type_id' => $employee->employment_type_id,
            'immediate_head_employee_id' => $employee->immediate_head_employee_id,
        ];
    }
}
