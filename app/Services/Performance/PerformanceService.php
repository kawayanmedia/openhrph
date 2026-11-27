<?php

namespace App\Services\Performance;

use App\Models\DevelopmentPlan;
use App\Models\Employee;
use App\Models\PerformanceCycle;
use App\Models\PerformanceGoal;
use App\Models\PerformanceRating;
use App\Models\PerformanceReview;
use App\Models\User;
use App\Services\Hr\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Configurable performance cycles and reviews.
 *
 * A final rating never changes salary, discipline or promotion by itself.
 */
class PerformanceService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createCycle(array $attributes, User $actor): PerformanceCycle
    {
        return DB::transaction(function () use ($attributes, $actor): PerformanceCycle {
            $cycle = PerformanceCycle::create([
                ...$attributes,
                'status' => $attributes['status'] ?? PerformanceCycle::STATUS_DRAFT,
                'created_by' => $actor->getKey(),
            ]);

            $this->audit->record('performance.cycle_created', $cycle, after: $cycle->getAttributes(), metadata: [
                'actor_id' => $actor->getKey(),
            ]);

            return $cycle->refresh();
        });
    }

    public function activateCycle(PerformanceCycle $cycle, User $actor): PerformanceCycle
    {
        return DB::transaction(function () use ($cycle, $actor): PerformanceCycle {
            $cycle = PerformanceCycle::withoutTenant()->whereKey($cycle->getKey())->lockForUpdate()->firstOrFail();

            if ($cycle->status !== PerformanceCycle::STATUS_DRAFT) {
                throw ValidationException::withMessages([
                    'status' => 'Only a draft cycle can be activated.',
                ]);
            }

            $cycle->forceFill(['status' => PerformanceCycle::STATUS_ACTIVE])->save();

            $this->audit->record('performance.cycle_activated', $cycle, metadata: ['actor_id' => $actor->getKey()]);

            return $cycle->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function setGoal(PerformanceCycle $cycle, Employee $employee, array $attributes, User $actor): PerformanceGoal
    {
        return DB::transaction(function () use ($cycle, $employee, $attributes, $actor): PerformanceGoal {
            if ($cycle->status !== PerformanceCycle::STATUS_ACTIVE) {
                throw ValidationException::withMessages([
                    'performance_cycle_id' => 'Goals can only be set in an active cycle.',
                ]);
            }

            $goal = PerformanceGoal::create([
                ...$attributes,
                'company_id' => $cycle->company_id,
                'performance_cycle_id' => $cycle->getKey(),
                'employee_id' => $employee->getKey(),
                'status' => $attributes['status'] ?? PerformanceGoal::STATUS_DRAFT,
            ]);

            $this->audit->record('performance.goal_set', $goal, after: $goal->getAttributes(), metadata: [
                'actor_id' => $actor->getKey(),
            ]);

            return $goal->refresh();
        });
    }

    public function agreeGoals(PerformanceCycle $cycle, Employee $employee, User $actor): int
    {
        return DB::transaction(function () use ($cycle, $employee, $actor): int {
            $count = PerformanceGoal::withoutTenant()
                ->where('performance_cycle_id', $cycle->getKey())
                ->where('employee_id', $employee->getKey())
                ->where('status', PerformanceGoal::STATUS_DRAFT)
                ->update(['status' => PerformanceGoal::STATUS_AGREED]);

            $this->audit->record('performance.goals_agreed', $cycle, metadata: [
                'actor_id' => $actor->getKey(),
                'employee_id' => $employee->getKey(),
                'goals' => $count,
            ]);

            return $count;
        });
    }

    public function startReview(PerformanceCycle $cycle, Employee $employee, ?Employee $reviewer, User $actor): PerformanceReview
    {
        return DB::transaction(function () use ($cycle, $employee, $reviewer, $actor): PerformanceReview {
            if ($cycle->status !== PerformanceCycle::STATUS_ACTIVE) {
                throw ValidationException::withMessages([
                    'performance_cycle_id' => 'Reviews can only start in an active cycle.',
                ]);
            }

            $review = PerformanceReview::firstOrCreate(
                [
                    'performance_cycle_id' => $cycle->getKey(),
                    'employee_id' => $employee->getKey(),
                ],
                [
                    'company_id' => $cycle->company_id,
                    'reviewer_employee_id' => $reviewer?->getKey(),
                    'status' => PerformanceReview::STATUS_DRAFT,
                ],
            );

            $this->audit->record('performance.review_started', $review, metadata: ['actor_id' => $actor->getKey()]);

            return $review->refresh();
        });
    }

    public function submitSelfAssessment(PerformanceReview $review, string $assessment, User $actor): PerformanceReview
    {
        return DB::transaction(function () use ($review, $assessment, $actor): PerformanceReview {
            $review = $this->openReview($review);

            $review->forceFill([
                'self_assessment' => $assessment,
                'status' => PerformanceReview::STATUS_SELF_ASSESSMENT,
            ])->save();

            $this->audit->record('performance.self_assessment_submitted', $review, metadata: ['actor_id' => $actor->getKey()]);

            return $review->refresh();
        });
    }

    public function recordRating(
        PerformanceReview $review,
        string $criterion,
        float $score,
        ?float $weight,
        ?string $comments,
        User $actor,
    ): PerformanceRating {
        return DB::transaction(function () use ($review, $criterion, $score, $weight, $comments, $actor): PerformanceRating {
            $review = $this->openReview($review);

            $rating = PerformanceRating::create([
                'company_id' => $review->company_id,
                'performance_review_id' => $review->getKey(),
                'criterion' => $criterion,
                'score' => $score,
                'weight' => $weight,
                'comments' => $comments,
            ]);

            $review->forceFill(['status' => PerformanceReview::STATUS_UNDER_REVIEW])->save();

            $this->audit->record('performance.rating_recorded', $review, after: $rating->getAttributes(), metadata: [
                'actor_id' => $actor->getKey(),
            ]);

            return $rating->refresh();
        });
    }

    public function finalize(PerformanceReview $review, User $actor, ?float $overall = null): PerformanceReview
    {
        return DB::transaction(function () use ($review, $actor, $overall): PerformanceReview {
            $review = $this->openReview($review);

            $ratings = $review->ratings()->get();

            if ($ratings->isEmpty()) {
                throw ValidationException::withMessages([
                    'ratings' => 'At least one criterion rating is required before finalizing.',
                ]);
            }

            if ($overall === null) {
                $weightTotal = $ratings->sum(fn (PerformanceRating $rating) => (float) ($rating->weight ?? 0));

                $overall = $weightTotal > 0
                    ? $ratings->sum(fn (PerformanceRating $rating) => (float) $rating->score * (float) $rating->weight) / $weightTotal
                    : $ratings->avg(fn (PerformanceRating $rating) => (float) $rating->score);
            }

            $review->forceFill([
                'status' => PerformanceReview::STATUS_FINALIZED,
                'overall_rating' => round((float) $overall, 2),
                'finalized_at' => now(),
            ])->save();

            $this->audit->record('performance.review_finalized', $review, after: [
                'overall_rating' => $review->overall_rating,
            ], metadata: ['actor_id' => $actor->getKey()]);

            return $review->refresh();
        });
    }

    public function acknowledge(PerformanceReview $review, User $actor): PerformanceReview
    {
        return DB::transaction(function () use ($review, $actor): PerformanceReview {
            $review = PerformanceReview::withoutTenant()->whereKey($review->getKey())->lockForUpdate()->firstOrFail();

            if (! $review->isFinalized()) {
                throw ValidationException::withMessages([
                    'status' => 'Only a finalized review can be acknowledged.',
                ]);
            }

            $review->forceFill([
                'status' => PerformanceReview::STATUS_ACKNOWLEDGED,
                'acknowledged_at' => now(),
            ])->save();

            $this->audit->record('performance.review_acknowledged', $review, metadata: ['actor_id' => $actor->getKey()]);

            return $review->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createDevelopmentPlan(Employee $employee, array $attributes, User $actor): DevelopmentPlan
    {
        return DB::transaction(function () use ($employee, $attributes, $actor): DevelopmentPlan {
            $plan = DevelopmentPlan::create([
                ...$attributes,
                'company_id' => $employee->company_id,
                'employee_id' => $employee->getKey(),
                'status' => $attributes['status'] ?? DevelopmentPlan::STATUS_DRAFT,
            ]);

            $this->audit->record('performance.development_plan_created', $plan, metadata: [
                'actor_id' => $actor->getKey(),
            ]);

            return $plan->refresh();
        });
    }

    protected function openReview(PerformanceReview $review): PerformanceReview
    {
        $review = PerformanceReview::withoutTenant()->whereKey($review->getKey())->lockForUpdate()->firstOrFail();

        if ($review->isFinalized()) {
            throw ValidationException::withMessages([
                'status' => 'A finalized review is immutable; create a new cycle instead.',
            ]);
        }

        return $review;
    }
}
