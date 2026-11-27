<?php

namespace App\Services\Separation;

use App\Models\ClearanceTask;
use App\Models\ClearanceTemplate;
use App\Models\Employee;
use App\Models\Employment;
use App\Models\FinalSettlement;
use App\Models\SeparationCase;
use App\Models\User;
use App\Services\Employment\EmploymentService;
use App\Services\Hr\AuditLogger;
use App\Services\Organization\PositionAssignmentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Resignation/termination workspace: clearance, effective dating and the
 * itemised inputs handed to payroll for final settlement.
 *
 * The service never determines legal entitlement; HR/finance record the
 * reviewed itemisation.
 */
class SeparationService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly EmploymentService $employments,
        private readonly PositionAssignmentService $assignments,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function initiate(Employee $employee, array $attributes, User $actor): SeparationCase
    {
        return DB::transaction(function () use ($employee, $attributes, $actor): SeparationCase {
            $case = SeparationCase::create([
                ...$attributes,
                'company_id' => $employee->company_id,
                'employee_id' => $employee->getKey(),
                'initiated_by' => $actor->getKey(),
                'status' => SeparationCase::STATUS_INITIATED,
            ]);

            $this->generateClearanceTasks($case, $attributes['template_id'] ?? null);

            $this->audit->record('separation.initiated', $case, after: $case->getAttributes(), metadata: [
                'actor_id' => $actor->getKey(),
            ]);

            return $case->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function transition(SeparationCase $case, string $status, User $actor, array $attributes = []): SeparationCase
    {
        return DB::transaction(function () use ($case, $status, $actor, $attributes): SeparationCase {
            $case = SeparationCase::withoutTenant()
                ->whereKey($case->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! $case->canTransitionTo($status)) {
                throw ValidationException::withMessages([
                    'status' => "A separation case in '{$case->status}' cannot move to '{$status}'.",
                ]);
            }

            $before = $case->getAttributes();
            $changes = [...$attributes, 'status' => $status];

            if ($status === SeparationCase::STATUS_APPROVED) {
                $changes += ['approved_by' => $actor->getKey(), 'approved_at' => now()];
            }

            $case->forceFill($changes)->save();

            if ($status === SeparationCase::STATUS_EFFECTIVE) {
                $this->applyEffective($case->refresh(), $actor);
            }

            $this->audit->record('separation.transitioned', $case, before: $before, after: $case->getAttributes(), metadata: [
                'actor_id' => $actor->getKey(),
                'to' => $status,
            ]);

            return $case->refresh();
        });
    }

    public function completeClearanceTask(ClearanceTask $task, string $status, User $actor, array $attributes = []): ClearanceTask
    {
        return DB::transaction(function () use ($task, $status, $actor, $attributes): ClearanceTask {
            if (! in_array($status, [ClearanceTask::STATUS_COMPLETED, ClearanceTask::STATUS_WAIVED], true)) {
                throw ValidationException::withMessages([
                    'status' => 'Unsupported clearance task status.',
                ]);
            }

            $task->forceFill([
                ...$attributes,
                'status' => $status,
                'completed_at' => now(),
            ])->save();

            $this->audit->record('separation.clearance_task_closed', $task, after: [
                'status' => $status,
            ], metadata: ['actor_id' => $actor->getKey()]);

            return $task->refresh();
        });
    }

    /**
     * Record the reviewed final-settlement itemisation. Amounts are supplied by
     * payroll/jurisdiction rules; this service only stores and totals them.
     *
     * @param  array<int, array{label: string, amount: float|int|string}>  $earnings
     * @param  array<int, array{label: string, amount: float|int|string}>  $deductions
     * @param  array<string, mixed>  $attributes
     */
    public function recordSettlement(SeparationCase $case, array $earnings, array $deductions, User $actor, array $attributes = []): FinalSettlement
    {
        return DB::transaction(function () use ($case, $earnings, $deductions, $actor, $attributes): FinalSettlement {
            $total = collect($earnings)->sum(fn (array $item) => (float) ($item['amount'] ?? 0))
                - collect($deductions)->sum(fn (array $item) => (float) ($item['amount'] ?? 0));

            $settlement = FinalSettlement::updateOrCreate(
                ['separation_case_id' => $case->getKey()],
                [
                    ...$attributes,
                    'company_id' => $case->company_id,
                    'earnings' => $earnings,
                    'deductions' => $deductions,
                    'total_amount' => round($total, 2),
                    'status' => $attributes['status'] ?? FinalSettlement::STATUS_REVIEW,
                ],
            );

            $this->audit->record('separation.settlement_recorded', $settlement, after: [
                'total_amount' => (string) $settlement->total_amount,
            ], metadata: ['actor_id' => $actor->getKey()]);

            return $settlement->refresh();
        });
    }

    /**
     * End employment, close the open placement and mark the employee separated.
     * Access revocation is recorded as a required operational task, not inferred.
     */
    protected function applyEffective(SeparationCase $case, User $actor): void
    {
        $effectiveDate = $case->effective_date ?? $case->proposed_effective_date;

        if ($effectiveDate === null) {
            throw ValidationException::withMessages([
                'effective_date' => 'An effective date is required before the separation can take effect.',
            ]);
        }

        $employee = Employee::withoutTenant()->whereKey($case->employee_id)->firstOrFail();

        $employment = Employment::withoutTenant()
            ->where('employee_id', $employee->getKey())
            ->where('status', Employment::STATUS_ACTIVE)
            ->first();

        if ($employment !== null) {
            $this->employments->endEmployment($employment, $effectiveDate->toDateString(), $case->reason, $actor);
        }

        $assignment = $employee->currentPositionAssignment;

        if ($assignment !== null) {
            $this->assignments->end($assignment, $effectiveDate->toDateString(), $case->reason, $actor);
        }

        $employee->forceFill([
            'lifecycle_status' => Employee::LIFECYCLE_SEPARATED,
            'date_resigned' => $effectiveDate->toDateString(),
        ])->save();

        $this->audit->record('separation.effective', $case, after: [
            'employee_id' => $employee->getKey(),
            'effective_date' => $effectiveDate->toDateString(),
        ], metadata: [
            'actor_id' => $actor->getKey(),
            'operational_task' => 'Revoke system access and recover company property.',
        ]);
    }

    protected function generateClearanceTasks(SeparationCase $case, ?int $templateId): void
    {
        $tasks = null;

        if ($templateId !== null) {
            $tasks = ClearanceTemplate::query()->find($templateId)?->tasks;
        }

        if (! is_array($tasks) || $tasks === []) {
            $tasks = [
                ['unit' => 'HR', 'task' => 'Return HR records and identification'],
                ['unit' => 'Supervisor', 'task' => 'Complete work handover'],
                ['unit' => 'Finance', 'task' => 'Reconcile advances, loans and liabilities'],
                ['unit' => 'IT', 'task' => 'Disable system access'],
                ['unit' => 'Admin', 'task' => 'Return equipment and keys'],
            ];
        }

        foreach ($tasks as $task) {
            ClearanceTask::create([
                'company_id' => $case->company_id,
                'separation_case_id' => $case->getKey(),
                'unit' => (string) ($task['unit'] ?? 'HR'),
                'task' => (string) ($task['task'] ?? 'Clearance item'),
                'status' => ClearanceTask::STATUS_PENDING,
            ]);
        }
    }
}
