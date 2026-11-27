<?php

namespace App\Jurisdictions\Philippines\Government\Services;

use App\Jurisdictions\Philippines\Government\Models\AppointmentAction;
use App\Jurisdictions\Philippines\Government\Models\AppointmentRecord;
use App\Jurisdictions\Philippines\Government\Models\AssumptionRecord;
use App\Jurisdictions\Philippines\Government\Models\PlantillaItem;
use App\Jurisdictions\Philippines\Government\Models\QualificationScreening;
use App\Jurisdictions\Philippines\Government\Models\ServiceRecord;
use App\Models\Employee;
use App\Models\User;
use App\Services\Hr\AuditLogger;
use App\Services\Organization\PositionAssignmentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Appointment state machine and placement.
 *
 * Issuance, service, assumption and CSC action are separate events. Placement
 * is applied once (`applied_at`) and only after the appointment is effective
 * and evidenced.
 */
class AppointmentService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly PlantillaService $plantilla,
        private readonly PositionAssignmentService $assignments,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes, ?User $actor = null): AppointmentRecord
    {
        return DB::transaction(function () use ($attributes, $actor): AppointmentRecord {
            $employee = Employee::withoutTenant()
                ->whereKey($attributes['employee_id'])
                ->firstOrFail();

            $item = isset($attributes['plantilla_item_id'])
                ? PlantillaItem::query()->findOrFail($attributes['plantilla_item_id'])
                : null;

            if ($item !== null) {
                $this->plantilla->assertItemAvailable($item, (int) $employee->getKey());
            }

            if (! empty($attributes['qualification_screening_id'])) {
                $screening = QualificationScreening::query()
                    ->where('employee_id', $employee->getKey())
                    ->whereKey($attributes['qualification_screening_id'])
                    ->first();

                if ($screening !== null && ! $screening->isQualified()) {
                    throw ValidationException::withMessages([
                        'qualification_screening_id' => 'The recorded qualification screening is disqualified.',
                    ]);
                }
            }

            $appointment = AppointmentRecord::create([
                ...$attributes,
                'company_id' => $employee->company_id,
                'status' => $attributes['status'] ?? AppointmentRecord::STATUS_DRAFT,
                'position_id' => $attributes['position_id'] ?? $item?->position_id,
                'plantilla_version_id' => $attributes['plantilla_version_id'] ?? $item?->plantilla_version_id,
                'office_id' => $attributes['office_id'] ?? $item?->office_id,
            ]);

            $this->audit->record('government.appointment_created', $appointment, after: $appointment->getAttributes(), metadata: [
                'actor_id' => $actor?->getKey(),
            ]);

            return $appointment->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function transition(AppointmentRecord $appointment, string $status, User $actor, array $attributes = []): AppointmentRecord
    {
        return DB::transaction(function () use ($appointment, $status, $actor, $attributes): AppointmentRecord {
            $appointment = AppointmentRecord::withoutTenant()
                ->whereKey($appointment->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! $appointment->canTransitionTo($status)) {
                throw ValidationException::withMessages([
                    'status' => "An appointment in '{$appointment->status}' cannot move to '{$status}'.",
                ]);
            }

            $before = $appointment->getAttributes();
            $changes = [...$attributes, 'status' => $status];

            match ($status) {
                AppointmentRecord::STATUS_APPROVED_FOR_ISSUANCE => $changes += [
                    'approved_by' => $actor->getKey(),
                    'approved_at' => now(),
                ],
                AppointmentRecord::STATUS_ISSUED => $changes += ['issued_at' => now()],
                AppointmentRecord::STATUS_SERVED => $changes += ['served_at' => now()],
                AppointmentRecord::STATUS_SUBMITTED_TO_CSC => $changes += ['submitted_to_csc_at' => now()],
                AppointmentRecord::STATUS_VALIDATED => $changes += ['csc_action_at' => now()],
                AppointmentRecord::STATUS_RETURNED => $changes += ['csc_action_at' => now()],
                default => null,
            };

            $appointment->forceFill($changes)->save();

            $this->audit->record('government.appointment_transitioned', $appointment, before: $before, after: $appointment->getAttributes(), metadata: [
                'actor_id' => $actor->getKey(),
                'to' => $status,
            ]);

            if ($status === AppointmentRecord::STATUS_VALIDATED) {
                $this->applyPlacement($appointment->refresh(), $actor);
            }

            return $appointment->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function recordAssumption(AppointmentRecord $appointment, array $attributes, User $actor): AssumptionRecord
    {
        return DB::transaction(function () use ($appointment, $attributes, $actor): AssumptionRecord {
            $appointment = AppointmentRecord::withoutTenant()
                ->whereKey($appointment->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (in_array($appointment->status, [AppointmentRecord::STATUS_VALIDATED, AppointmentRecord::STATUS_CANCELLED], true)) {
                throw ValidationException::withMessages([
                    'status' => 'This appointment is already closed.',
                ]);
            }

            if (empty($attributes['assumption_date'])) {
                throw ValidationException::withMessages([
                    'assumption_date' => 'The assumption date is required.',
                ]);
            }

            $record = AssumptionRecord::create([
                ...$attributes,
                'company_id' => $appointment->company_id,
                'appointment_id' => $appointment->getKey(),
            ]);

            $appointment->forceFill([
                'assumption_date' => $attributes['assumption_date'],
                'served_at' => $appointment->served_at ?? now(),
                'status' => $appointment->status === AppointmentRecord::STATUS_ISSUED
                    ? AppointmentRecord::STATUS_SERVED
                    : $appointment->status,
            ])->save();

            $this->applyPlacement($appointment->refresh(), $actor);

            $this->audit->record('government.appointment_assumed', $appointment, after: [
                'assumption_id' => $record->getKey(),
                'assumption_date' => $record->assumption_date?->toDateString(),
            ], metadata: ['actor_id' => $actor->getKey()]);

            return $record->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function recordAction(AppointmentRecord $appointment, string $actionType, array $attributes, User $actor): AppointmentAction
    {
        return DB::transaction(function () use ($appointment, $actionType, $attributes, $actor): AppointmentAction {
            $appointment = AppointmentRecord::withoutTenant()
                ->whereKey($appointment->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $action = AppointmentAction::create([
                ...$attributes,
                'company_id' => $appointment->company_id,
                'appointment_id' => $appointment->getKey(),
                'action_type' => $actionType,
                'recorded_by' => $actor->getKey(),
            ]);

            $target = match ($actionType) {
                AppointmentAction::TYPE_CSC_RECEIPT, AppointmentAction::TYPE_RESUBMISSION => AppointmentRecord::STATUS_SUBMITTED_TO_CSC,
                AppointmentAction::TYPE_CSC_APPROVAL => AppointmentRecord::STATUS_VALIDATED,
                AppointmentAction::TYPE_CSC_RETURN, AppointmentAction::TYPE_CSC_DENIAL => AppointmentRecord::STATUS_RETURNED,
                default => null,
            };

            if ($target !== null && $appointment->canTransitionTo($target)) {
                $this->transition($appointment, $target, $actor);
            } else {
                $appointment->refresh();
            }

            $this->audit->record('government.appointment_action', $appointment, after: [
                'action_id' => $action->getKey(),
                'action_type' => $actionType,
            ], metadata: ['actor_id' => $actor->getKey()]);

            return $action->refresh();
        });
    }

    /**
     * Apply the appointment exactly once: placement history, employee
     * projection, plantilla occupancy and the CSC service record.
     */
    public function applyPlacement(AppointmentRecord $appointment, ?User $actor = null, ?int $ignoreAppointmentId = null): AppointmentRecord
    {
        return DB::transaction(function () use ($appointment, $actor, $ignoreAppointmentId): AppointmentRecord {
            $appointment = AppointmentRecord::withoutTenant()
                ->whereKey($appointment->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($appointment->isApplied()) {
                return $appointment;
            }

            if ($appointment->status === AppointmentRecord::STATUS_CANCELLED) {
                throw ValidationException::withMessages([
                    'status' => 'A cancelled appointment cannot be applied.',
                ]);
            }

            $employee = Employee::withoutTenant()->whereKey($appointment->employee_id)->firstOrFail();
            $item = $appointment->plantilla_item_id === null
                ? null
                : PlantillaItem::query()->find($appointment->plantilla_item_id);

            if ($item !== null) {
                $this->plantilla->assertItemAvailable($item, (int) $employee->getKey(), array_filter([(int) $appointment->getKey(), $ignoreAppointmentId]));
            }

            $position = $appointment->position_id === null
                ? $item?->position
                : $appointment->position;

            $this->assignments->assign($employee, [
                'position_id' => $appointment->position_id ?? $item?->position_id,
                'organization_unit_id' => $item?->organization_unit_id ?? $position?->organization_unit_id,
                'department_id' => $position?->department_id,
                'office_id' => $appointment->office_id ?? $item?->office_id,
                'location_id' => $position?->location_id,
                'cost_center_id' => $position?->cost_center_id,
                'employment_type_id' => $employee->employment_type_id,
                'assignment_type' => $this->assignmentType($appointment->nature),
                'effective_from' => $appointment->effectivity_date->toDateString(),
                'reference' => $appointment->reference_number,
                'change_type' => $this->changeType($appointment->appointment_type),
                'reason' => $appointment->legal_basis,
            ], $actor);

            $employee->forceFill([
                'salary_grade' => $item?->salary_grade ?? $employee->salary_grade,
                'step' => $item?->step ?? $employee->step,
                'item_number' => $item?->item_number ?? $employee->item_number,
            ])->save();

            if ($item !== null) {
                $this->plantilla->occupy($item, $appointment);
            }

            $this->recordServiceHistory($appointment, $employee, $item);

            $appointment->forceFill(['applied_at' => now()])->save();

            $this->audit->record('government.appointment_applied', $appointment, after: [
                'employee_id' => $employee->getKey(),
                'plantilla_item_id' => $item?->getKey(),
                'position_id' => $appointment->position_id,
            ], metadata: ['actor_id' => $actor?->getKey()]);

            return $appointment->refresh();
        });
    }

    protected function recordServiceHistory(AppointmentRecord $appointment, Employee $employee, ?PlantillaItem $item): ServiceRecord
    {
        return ServiceRecord::create([
            'company_id' => $appointment->company_id,
            'employee_id' => $employee->getKey(),
            'date_from' => $appointment->effectivity_date->toDateString(),
            'date_to' => null,
            'designation' => $appointment->position?->name ?? $item?->position_title ?? $employee->position,
            'status' => $appointment->nature,
            'salary' => $item?->salary_grade !== null
                ? 'SG '.$item->salary_grade.'/'.$item->step
                : (string) $employee->basic_salary,
            'office_entity' => $appointment->office?->name ?? $item?->office?->name,
            'remarks' => 'Generated from appointment #'.$appointment->getKey(),
            'plantilla_item_id' => $item?->getKey(),
            'appointment_id' => $appointment->getKey(),
        ]);
    }

    protected function assignmentType(string $nature): string
    {
        return match ($nature) {
            AppointmentRecord::NATURE_PERMANENT => 'regular',
            AppointmentRecord::NATURE_TEMPORARY => 'acting',
            AppointmentRecord::NATURE_CASUAL => 'casual',
            AppointmentRecord::NATURE_CONTRACTUAL, AppointmentRecord::NATURE_CO_TERMINOUS => 'contractual',
            default => 'regular',
        };
    }

    protected function changeType(string $appointmentType): string
    {
        return match ($appointmentType) {
            AppointmentRecord::TYPE_PROMOTION => 'promotion',
            AppointmentRecord::TYPE_TRANSFER, AppointmentRecord::TYPE_REASSIGNMENT => 'transfer',
            AppointmentRecord::TYPE_DEMOTION => 'demotion',
            AppointmentRecord::TYPE_DESIGNATION, AppointmentRecord::TYPE_DETAIL => 'designation',
            default => 'other',
        };
    }
}
