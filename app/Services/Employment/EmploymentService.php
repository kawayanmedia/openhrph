<?php

namespace App\Services\Employment;

use App\Models\Employee;
use App\Models\Employment;
use App\Models\EmploymentChange;
use App\Models\EmploymentContract;
use App\Models\ProbationaryReview;
use App\Models\RegularizationDecision;
use App\Models\User;
use App\Services\Hr\AuditLogger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Employment periods, contracts, probation and regularization.
 *
 * Regularization changes the classification only through an approved decision;
 * the system never treats elapsed time as an implicit regularization.
 */
class EmploymentService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function start(Employee $employee, array $attributes, ?User $actor = null): Employment
    {
        return DB::transaction(function () use ($employee, $attributes, $actor): Employment {
            $employee = Employee::withoutTenant()->whereKey($employee->getKey())->lockForUpdate()->firstOrFail();

            $existing = Employment::withoutTenant()
                ->where('employee_id', $employee->getKey())
                ->where('status', Employment::STATUS_ACTIVE)
                ->first();

            if ($existing !== null) {
                throw ValidationException::withMessages([
                    'employee_id' => 'This employee already has an active employment period.',
                ]);
            }

            $classification = $attributes['classification'] ?? Employment::CLASSIFICATION_REGULAR;
            $startDate = Carbon::parse($attributes['start_date'])->startOfDay();

            if ($classification === Employment::CLASSIFICATION_PROBATIONARY
                && empty($attributes['probation_end'])) {
                throw ValidationException::withMessages([
                    'probation_end' => 'A probationary employment requires a probation end date.',
                ]);
            }

            $employment = Employment::create([
                ...$attributes,
                'company_id' => $employee->company_id,
                'employee_id' => $employee->getKey(),
                'classification' => $classification,
                'start_date' => $startDate->toDateString(),
                'probation_start' => $classification === Employment::CLASSIFICATION_PROBATIONARY
                    ? ($attributes['probation_start'] ?? $startDate->toDateString())
                    : ($attributes['probation_start'] ?? null),
                'status' => Employment::STATUS_ACTIVE,
            ]);

            $employee->forceFill([
                'lifecycle_status' => Employee::LIFECYCLE_ACTIVE,
                'employment_type_id' => $attributes['employment_type_id'] ?? $employee->employment_type_id,
                'date_hired' => $employee->date_hired ?? $startDate->toDateString(),
            ])->save();

            EmploymentChange::create([
                'company_id' => $employee->company_id,
                'employee_id' => $employee->getKey(),
                'change_type' => EmploymentChange::TYPE_HIRE,
                'effective_date' => $startDate->toDateString(),
                'reason' => 'Employment started',
                'approved_by' => $actor?->getKey(),
                'metadata' => ['employment_id' => $employment->getKey(), 'classification' => $classification],
            ]);

            $this->audit->record('employment.started', $employment, after: $employment->getAttributes(), metadata: [
                'actor_id' => $actor?->getKey(),
            ]);

            return $employment->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function recordContract(Employment $employment, array $attributes, ?User $actor = null): EmploymentContract
    {
        return DB::transaction(function () use ($employment, $attributes, $actor): EmploymentContract {
            $version = (int) EmploymentContract::withoutTenant()
                ->where('employment_id', $employment->getKey())
                ->max('version') + 1;

            $contract = EmploymentContract::create([
                ...$attributes,
                'company_id' => $employment->company_id,
                'employment_id' => $employment->getKey(),
                'version' => $version,
                'status' => $attributes['status'] ?? EmploymentContract::STATUS_DRAFT,
            ]);

            if (in_array($contract->status, [EmploymentContract::STATUS_SIGNED, EmploymentContract::STATUS_ACTIVE], true)) {
                $employment->forceFill(['contract_reference' => $attributes['contract_reference'] ?? ('CONTRACT-'.$contract->version)])->save();
            }

            $this->audit->record('employment.contract_recorded', $contract, after: $contract->getAttributes(), metadata: [
                'actor_id' => $actor?->getKey(),
            ]);

            return $contract->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function recordProbationaryReview(Employment $employment, array $attributes, ?User $actor = null): ProbationaryReview
    {
        return DB::transaction(function () use ($employment, $attributes, $actor): ProbationaryReview {
            $review = ProbationaryReview::create([
                ...$attributes,
                'company_id' => $employment->company_id,
                'employment_id' => $employment->getKey(),
                'evaluator_id' => $attributes['evaluator_id'] ?? $actor?->getKey(),
            ]);

            $this->audit->record('employment.probation_reviewed', $review, after: $review->getAttributes(), metadata: [
                'actor_id' => $actor?->getKey(),
            ]);

            return $review->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function decideRegularization(Employment $employment, array $attributes, User $actor): RegularizationDecision
    {
        return DB::transaction(function () use ($employment, $attributes, $actor): RegularizationDecision {
            $employment = Employment::withoutTenant()->whereKey($employment->getKey())->lockForUpdate()->firstOrFail();

            $decision = $attributes['decision'];

            if (! in_array($decision, [
                RegularizationDecision::DECISION_REGULARIZE,
                RegularizationDecision::DECISION_EXTEND,
                RegularizationDecision::DECISION_SEPARATE,
            ], true)) {
                throw ValidationException::withMessages([
                    'decision' => 'Unsupported regularization decision.',
                ]);
            }

            $effectiveDate = Carbon::parse($attributes['effective_date'])->startOfDay();

            $record = RegularizationDecision::create([
                ...$attributes,
                'company_id' => $employment->company_id,
                'employment_id' => $employment->getKey(),
                'decision' => $decision,
                'effective_date' => $effectiveDate->toDateString(),
                'approved_by' => $actor->getKey(),
                'approved_at' => now(),
            ]);

            if ($decision === RegularizationDecision::DECISION_REGULARIZE) {
                $employment->forceFill([
                    'classification' => Employment::CLASSIFICATION_REGULAR,
                    'regularization_date' => $effectiveDate->toDateString(),
                    'status' => Employment::STATUS_ACTIVE,
                ])->save();

                EmploymentChange::create([
                    'company_id' => $employment->company_id,
                    'employee_id' => $employment->employee_id,
                    'change_type' => EmploymentChange::TYPE_REGULARIZATION,
                    'effective_date' => $effectiveDate->toDateString(),
                    'reason' => $attributes['notes'] ?? 'Regularization approved',
                    'approved_by' => $actor->getKey(),
                    'metadata' => ['employment_id' => $employment->getKey()],
                ]);
            }

            if ($decision === RegularizationDecision::DECISION_EXTEND && ! empty($attributes['probation_end'])) {
                $employment->forceFill(['probation_end' => $attributes['probation_end']])->save();
            }

            $this->audit->record('employment.regularization_decided', $record, after: $record->getAttributes(), metadata: [
                'actor_id' => $actor->getKey(),
                'decision' => $decision,
            ]);

            return $record->refresh();
        });
    }

    public function endEmployment(Employment $employment, string $endDate, ?string $reason = null, ?User $actor = null): Employment
    {
        return DB::transaction(function () use ($employment, $endDate, $reason, $actor): Employment {
            $employment = Employment::withoutTenant()->whereKey($employment->getKey())->lockForUpdate()->firstOrFail();

            $employment->forceFill([
                'status' => Employment::STATUS_ENDED,
                'end_date' => Carbon::parse($endDate)->toDateString(),
            ])->save();

            $this->audit->record('employment.ended', $employment, after: [
                'end_date' => $endDate,
                'reason' => $reason,
            ], metadata: ['actor_id' => $actor?->getKey()]);

            return $employment->refresh();
        });
    }
}
