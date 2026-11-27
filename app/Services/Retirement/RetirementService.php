<?php

namespace App\Services\Retirement;

use App\Models\Document;
use App\Models\Employee;
use App\Models\ExternalClaimTracking;
use App\Models\RetirementCalculation;
use App\Models\RetirementCase;
use App\Models\RetirementPlan;
use App\Models\User;
use App\Services\Hr\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Retirement case lifecycle. Amounts stay provisional until a calculation is
 * reviewed and approved; the service never auto-approves an entitlement.
 */
class RetirementService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly RetirementEligibilityService $eligibility,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function openCase(Employee $employee, ?RetirementPlan $plan, array $attributes, User $actor): RetirementCase
    {
        return DB::transaction(function () use ($employee, $plan, $attributes, $actor): RetirementCase {
            $asOf = isset($attributes['proposed_retirement_date'])
                ? (string) $attributes['proposed_retirement_date']
                : null;

            $case = RetirementCase::create([
                ...$attributes,
                'company_id' => $employee->company_id,
                'employee_id' => $employee->getKey(),
                'retirement_plan_id' => $plan?->getKey(),
                'retirement_type' => $attributes['retirement_type'] ?? RetirementCase::TYPE_OPTIONAL,
                'status' => RetirementCase::STATUS_DRAFT,
                'eligibility_snapshot' => $this->eligibility->evaluate($employee, $plan, $asOf),
                'created_by' => $actor->getKey(),
            ]);

            $this->audit->record('retirement.case_opened', $case, after: $case->getAttributes(), metadata: [
                'actor_id' => $actor->getKey(),
            ]);

            return $case->refresh();
        });
    }

    public function assessEligibility(RetirementCase $case, User $actor): RetirementCase
    {
        return DB::transaction(function () use ($case, $actor): RetirementCase {
            $case = $this->lockCase($case);

            if (! $case->canTransitionTo(RetirementCase::STATUS_ELIGIBILITY_REVIEW)) {
                throw ValidationException::withMessages([
                    'status' => 'Eligibility can only be assessed for a draft retirement case.',
                ]);
            }

            $snapshot = $this->eligibility->evaluate(
                $case->employee,
                $case->plan,
                $case->proposed_retirement_date?->toDateString(),
            );

            $case->forceFill([
                'status' => RetirementCase::STATUS_ELIGIBILITY_REVIEW,
                'eligibility_snapshot' => $snapshot,
            ])->save();

            $this->audit->record('retirement.eligibility_assessed', $case, after: $snapshot, metadata: [
                'actor_id' => $actor->getKey(),
            ]);

            return $case->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function transition(RetirementCase $case, string $status, User $actor, array $attributes = []): RetirementCase
    {
        return DB::transaction(function () use ($case, $status, $actor, $attributes): RetirementCase {
            $case = $this->lockCase($case);

            if (! $case->canTransitionTo($status)) {
                throw ValidationException::withMessages([
                    'status' => "A retirement case in '{$case->status}' cannot move to '{$status}'.",
                ]);
            }

            $changes = [...$attributes, 'status' => $status];

            if ($status === RetirementCase::STATUS_APPLIED) {
                $changes['applied_at'] = now();
            }

            if ($status === RetirementCase::STATUS_APPROVED) {
                $changes['approved_by'] = $actor->getKey();
                $changes['approved_at'] = now();
            }

            if ($status === RetirementCase::STATUS_COMPLETED) {
                $this->assertReviewedCalculation($case);
                $changes['completed_at'] = now();
            }

            $case->forceFill($changes)->save();

            if ($status === RetirementCase::STATUS_COMPLETED) {
                $this->markEmployeeRetired($case);
            }

            $this->audit->record('retirement.case_'.$status, $case, metadata: [
                'actor_id' => $actor->getKey(),
            ]);

            return $case->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $inputs
     */
    public function calculate(
        RetirementCase $case,
        array $inputs,
        User $actor,
        string $type = RetirementCalculation::TYPE_RETIREMENT_BENEFIT,
    ): RetirementCalculation {
        return DB::transaction(function () use ($case, $inputs, $actor, $type): RetirementCalculation {
            $case = $this->lockCase($case);

            if ($case->isClosed()) {
                throw ValidationException::withMessages([
                    'status' => 'A closed retirement case cannot be recalculated.',
                ]);
            }

            if (! in_array($type, RetirementCalculation::TYPES, true)) {
                throw ValidationException::withMessages(['calculation_type' => 'Unsupported calculation type.']);
            }

            [$amount, $outputs, $ruleVersion] = $type === RetirementCalculation::TYPE_TERMINAL_LEAVE
                ? $this->terminalLeaveAmounts($case, $inputs)
                : $this->benefitAmounts($case, $inputs);

            $calculation = RetirementCalculation::create([
                'company_id' => $case->company_id,
                'retirement_case_id' => $case->getKey(),
                'calculation_type' => $type,
                'rule_version' => $ruleVersion,
                'inputs' => $inputs,
                'outputs' => $outputs,
                'amount' => $amount,
                'status' => RetirementCalculation::STATUS_DRAFT,
                'computed_by' => $actor->getKey(),
                'computed_at' => now(),
            ]);

            $this->audit->record('retirement.calculated', $calculation, after: $outputs, metadata: [
                'actor_id' => $actor->getKey(),
                'calculation_type' => $type,
            ]);

            return $calculation->refresh();
        });
    }

    public function reviewCalculation(
        RetirementCalculation $calculation,
        User $actor,
        bool $approve,
        ?string $notes = null,
    ): RetirementCalculation {
        return DB::transaction(function () use ($calculation, $actor, $approve, $notes): RetirementCalculation {
            $calculation = RetirementCalculation::withoutTenant()
                ->whereKey($calculation->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($calculation->status, [
                RetirementCalculation::STATUS_DRAFT,
                RetirementCalculation::STATUS_REVIEWED,
            ], true)) {
                throw ValidationException::withMessages([
                    'status' => 'Only a draft or reviewed calculation can be reviewed.',
                ]);
            }

            $calculation->forceFill([
                'status' => $approve
                    ? RetirementCalculation::STATUS_APPROVED
                    : RetirementCalculation::STATUS_REJECTED,
                'reviewed_by' => $actor->getKey(),
                'reviewed_at' => now(),
                'reviewer_notes' => $notes,
            ])->save();

            $this->audit->record('retirement.calculation_reviewed', $calculation, metadata: [
                'actor_id' => $actor->getKey(),
                'approved' => $approve,
            ]);

            return $calculation->refresh();
        });
    }

    public function attachDocument(
        RetirementCase $case,
        Document $document,
        string $documentType,
        User $actor,
    ): RetirementCase {
        return DB::transaction(function () use ($case, $document, $documentType, $actor): RetirementCase {
            $case = $this->lockCase($case);

            $case->documents()->firstOrCreate(
                ['document_id' => $document->getKey()],
                ['company_id' => $case->company_id, 'document_type' => $documentType],
            );

            $this->audit->record('retirement.document_attached', $case, metadata: [
                'actor_id' => $actor->getKey(),
                'document_id' => $document->getKey(),
                'document_type' => $documentType,
            ]);

            return $case->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function trackExternalClaim(RetirementCase $case, array $attributes, User $actor): ExternalClaimTracking
    {
        return DB::transaction(function () use ($case, $attributes, $actor): ExternalClaimTracking {
            $case = $this->lockCase($case);

            $status = (string) ($attributes['status'] ?? ExternalClaimTracking::STATUS_SUBMITTED);

            if (! in_array($status, ExternalClaimTracking::STATUSES, true)) {
                throw ValidationException::withMessages(['status' => 'Unsupported external claim status.']);
            }

            $claim = $case->externalClaims()->firstOrNew([
                'agency' => $attributes['agency'] ?? ExternalClaimTracking::AGENCY_GSIS,
            ]);

            $claim->fill([
                ...$attributes,
                'company_id' => $case->company_id,
                'status' => $status,
                'submitted_at' => $claim->submitted_at
                    ?? (in_array($status, [
                        ExternalClaimTracking::STATUS_SUBMITTED,
                        ExternalClaimTracking::STATUS_IN_PROCESS,
                        ExternalClaimTracking::STATUS_APPROVED,
                        ExternalClaimTracking::STATUS_RELEASED,
                    ], true) ? now() : null),
                'released_at' => $status === ExternalClaimTracking::STATUS_RELEASED
                    ? ($claim->released_at ?? now())
                    : $claim->released_at,
                'last_checked_at' => now(),
            ])->save();

            $this->audit->record('retirement.external_claim_updated', $claim, after: [
                'agency' => $claim->agency,
                'status' => $claim->status,
            ], metadata: ['actor_id' => $actor->getKey()]);

            return $claim->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $inputs
     * @return array{0: float, 1: array<string, mixed>, 2: string}
     */
    protected function benefitAmounts(RetirementCase $case, array $inputs): array
    {
        $plan = $case->plan;

        if ($plan === null) {
            throw ValidationException::withMessages([
                'retirement_plan_id' => 'A retirement plan is required before calculating benefits.',
            ]);
        }

        $snapshot = $case->eligibility_snapshot ?? [];
        $serviceYears = (float) ($inputs['service_years'] ?? $snapshot['service_years'] ?? 0);
        $monthlySalary = (float) ($inputs['monthly_salary'] ?? $case->employee?->basic_salary ?? 0);

        $formula = $plan->benefit_formula ?? ['type' => 'percentage_of_salary', 'rate' => 0.025];
        $type = (string) ($formula['type'] ?? 'percentage_of_salary');

        $amount = match ($type) {
            'percentage_of_salary' => $monthlySalary * $serviceYears * (float) ($formula['rate'] ?? 0.025),
            'fixed_amount' => (float) ($formula['amount'] ?? 0),
            'accumulated_contributions' => (float) ($inputs['accumulated_contributions'] ?? 0),
            default => throw ValidationException::withMessages([
                'benefit_formula' => 'Unsupported retirement benefit formula.',
            ]),
        };

        $outputs = [
            'formula' => $type,
            'service_years' => round($serviceYears, 2),
            'monthly_salary' => round($monthlySalary, 2),
            'amount' => round($amount, 2),
            'provisional' => true,
        ];

        return [round($amount, 2), $outputs, $this->ruleVersion($plan)];
    }

    /**
     * @param  array<string, mixed>  $inputs
     * @return array{0: float, 1: array<string, mixed>, 2: string}
     */
    protected function terminalLeaveAmounts(RetirementCase $case, array $inputs): array
    {
        $days = (float) ($inputs['days'] ?? 0);

        if ($days < 0) {
            throw ValidationException::withMessages(['days' => 'Terminal leave days cannot be negative.']);
        }

        $dailyRate = (float) ($inputs['daily_rate'] ?? $case->employee?->daily_rate ?? 0);
        $amount = $days * $dailyRate;

        return [
            round($amount, 2),
            [
                'days' => round($days, 2),
                'daily_rate' => round($dailyRate, 2),
                'amount' => round($amount, 2),
                'provisional' => true,
            ],
            'terminal_leave:'.$case->company_id,
        ];
    }

    protected function ruleVersion(RetirementPlan $plan): string
    {
        return 'retirement_plan:'.$plan->getKey().':'.($plan->updated_at?->toIso8601String() ?? 'v1');
    }

    protected function assertReviewedCalculation(RetirementCase $case): void
    {
        $approved = $case->calculations()
            ->where('calculation_type', RetirementCalculation::TYPE_RETIREMENT_BENEFIT)
            ->where('status', RetirementCalculation::STATUS_APPROVED)
            ->exists();

        if (! $approved) {
            throw ValidationException::withMessages([
                'status' => 'An approved retirement benefit calculation is required before completing the case.',
            ]);
        }
    }

    protected function markEmployeeRetired(RetirementCase $case): void
    {
        $case->employee?->forceFill([
            'lifecycle_status' => Employee::LIFECYCLE_SEPARATED,
            'date_resigned' => $case->proposed_retirement_date?->toDateString(),
        ])->save();
    }

    protected function lockCase(RetirementCase $case): RetirementCase
    {
        return RetirementCase::withoutTenant()
            ->whereKey($case->getKey())
            ->lockForUpdate()
            ->firstOrFail();
    }
}
