<?php

namespace App\Jurisdictions\Philippines\Government\Services;

use App\Jurisdictions\Philippines\Government\Models\AppointmentRecord;
use App\Jurisdictions\Philippines\Government\Models\PersonnelAction;
use App\Jurisdictions\Philippines\Government\Models\PlantillaItem;
use App\Models\Employee;
use App\Models\User;
use App\Services\Hr\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Promotion, transfer, reassignment, detail and designation workflow.
 *
 * Completing an action creates a new appointment record rather than editing
 * the prior one, so the appointment and service history stays intact.
 */
class PersonnelActionService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly AppointmentService $appointments,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes, ?User $actor = null): PersonnelAction
    {
        return DB::transaction(function () use ($attributes, $actor): PersonnelAction {
            $employee = Employee::withoutTenant()->whereKey($attributes['employee_id'])->firstOrFail();

            $action = PersonnelAction::create([
                ...$attributes,
                'company_id' => $employee->company_id,
                'status' => $attributes['status'] ?? PersonnelAction::STATUS_DRAFT,
            ]);

            $this->audit->record('government.personnel_action_created', $action, after: $action->getAttributes(), metadata: [
                'actor_id' => $actor?->getKey(),
            ]);

            return $action->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function transition(PersonnelAction $action, string $status, User $actor, array $attributes = []): PersonnelAction
    {
        return DB::transaction(function () use ($action, $status, $actor, $attributes): PersonnelAction {
            $action = PersonnelAction::withoutTenant()->whereKey($action->getKey())->lockForUpdate()->firstOrFail();

            if (! $action->canTransitionTo($status)) {
                throw ValidationException::withMessages([
                    'status' => "A personnel action in '{$action->status}' cannot move to '{$status}'.",
                ]);
            }

            $before = $action->getAttributes();
            $changes = [...$attributes, 'status' => $status];

            if ($status === PersonnelAction::STATUS_APPROVED) {
                $changes += ['approved_by' => $actor->getKey(), 'approved_at' => now()];

                if (empty($changes['effective_date'])) {
                    $changes['effective_date'] = $action->proposed_effective_date?->toDateString();
                }
            }

            $action->forceFill($changes)->save();

            $this->audit->record('government.personnel_action_transitioned', $action, before: $before, after: $action->getAttributes(), metadata: [
                'actor_id' => $actor->getKey(),
                'to' => $status,
            ]);

            return $action->refresh();
        });
    }

    public function complete(PersonnelAction $action, User $actor): PersonnelAction
    {
        return DB::transaction(function () use ($action, $actor): PersonnelAction {
            $action = PersonnelAction::withoutTenant()->whereKey($action->getKey())->lockForUpdate()->firstOrFail();

            if (! $action->canTransitionTo(PersonnelAction::STATUS_COMPLETED)) {
                throw ValidationException::withMessages([
                    'status' => 'Only an approved personnel action can be completed.',
                ]);
            }

            $effectiveDate = $action->effective_date ?? $action->proposed_effective_date;

            if ($effectiveDate === null) {
                throw ValidationException::withMessages([
                    'effective_date' => 'An effective date is required before completing the action.',
                ]);
            }

            $item = $action->target_plantilla_item_id === null
                ? null
                : PlantillaItem::query()->find($action->target_plantilla_item_id);

            $employee = Employee::withoutTenant()->whereKey($action->employee_id)->firstOrFail();
            $currentAppointment = $action->currentAppointment;

            $appointment = AppointmentRecord::create([
                'company_id' => $action->company_id,
                'employee_id' => $action->employee_id,
                'plantilla_item_id' => $item?->getKey(),
                'plantilla_version_id' => $item?->plantilla_version_id,
                'office_id' => $action->target_office_id ?? $item?->office_id,
                'position_id' => $action->target_position_id ?? $item?->position_id,
                'appointment_type' => $action->action_type,
                'nature' => $currentAppointment?->nature ?? AppointmentRecord::NATURE_PERMANENT,
                'legal_basis' => $currentAppointment?->legal_basis,
                'reference_number' => $action->metadata['reference_number'] ?? null,
                'effectivity_date' => $effectiveDate->toDateString(),
                'status' => AppointmentRecord::STATUS_VALIDATED,
                'approved_by' => $action->approved_by,
                'approved_at' => $action->approved_at,
                'metadata' => ['personnel_action_id' => $action->getKey()],
            ]);

            $this->appointments->applyPlacement(
                $appointment,
                $actor,
                $currentAppointment?->getKey(),
            );

            $action->forceFill(['status' => PersonnelAction::STATUS_COMPLETED])->save();

            $this->audit->record('government.personnel_action_completed', $action, after: [
                'appointment_id' => $appointment->getKey(),
                'effective_date' => $effectiveDate->toDateString(),
                'employee_id' => $employee->getKey(),
            ], metadata: ['actor_id' => $actor->getKey()]);

            return $action->refresh();
        });
    }
}
