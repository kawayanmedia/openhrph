<?php

use App\Models\Company;
use App\Models\Employee;
use App\Models\PerformanceCycle;
use App\Models\PerformanceGoal;
use App\Models\PerformanceReview;
use App\Models\User;
use App\Services\Performance\PerformanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->company = Company::factory()->create(['name' => 'Performance Co']);
    $this->user = User::factory()->forCompany($this->company)->create();
    $this->actingAs($this->user);

    $this->employee = Employee::factory()->create(['company_id' => $this->company->id]);
    $this->reviewer = Employee::factory()->create(['company_id' => $this->company->id]);

    $this->service = app(PerformanceService::class);

    $this->cycle = $this->service->createCycle([
        'company_id' => $this->company->id,
        'name' => '2026 Annual Review',
        'period_start' => '2026-01-01',
        'period_end' => '2026-12-31',
    ], $this->user);
});

it('activates a draft cycle once', function (): void {
    $cycle = $this->service->activateCycle($this->cycle, $this->user);

    expect($cycle->status)->toBe(PerformanceCycle::STATUS_ACTIVE)
        ->and((int) $cycle->created_by)->toBe($this->user->id);

    expect(fn () => $this->service->activateCycle($cycle, $this->user))
        ->toThrow(ValidationException::class);
});

it('only sets goals on an active cycle and agrees draft goals', function (): void {
    expect(fn () => $this->service->setGoal($this->cycle, $this->employee, [
        'title' => 'Ship the HR module',
    ], $this->user))->toThrow(ValidationException::class);

    $cycle = $this->service->activateCycle($this->cycle, $this->user);

    $this->service->setGoal($cycle, $this->employee, [
        'title' => 'Ship the HR module',
        'measures' => 'Released by Q4',
        'weight' => 60,
    ], $this->user);

    $this->service->setGoal($cycle, $this->employee, [
        'title' => 'Reduce payroll errors',
        'weight' => 40,
    ], $this->user);

    expect($this->service->agreeGoals($cycle, $this->employee, $this->user))->toBe(2)
        ->and(PerformanceGoal::withoutTenant()->where('status', PerformanceGoal::STATUS_AGREED)->count())->toBe(2);
});

it('computes a weighted overall rating when finalizing a review', function (): void {
    $cycle = $this->service->activateCycle($this->cycle, $this->user);

    $review = $this->service->startReview($cycle, $this->employee, $this->reviewer, $this->user);

    expect($review->status)->toBe(PerformanceReview::STATUS_DRAFT);

    $this->service->recordRating($review, 'Quality', 5, 60, null, $this->user);
    $this->service->recordRating($review, 'Timeliness', 3, 40, null, $this->user);

    $finalized = $this->service->finalize($review->refresh(), $this->user);

    expect((float) $finalized->overall_rating)->toBe(4.2)
        ->and($finalized->status)->toBe(PerformanceReview::STATUS_FINALIZED)
        ->and($finalized->finalized_at)->not->toBeNull();
});

it('requires ratings, allows an explicit overall override and freezes finalized reviews', function (): void {
    $cycle = $this->service->activateCycle($this->cycle, $this->user);
    $review = $this->service->startReview($cycle, $this->employee, null, $this->user);

    expect(fn () => $this->service->finalize($review, $this->user))
        ->toThrow(ValidationException::class);

    $this->service->recordRating($review, 'Quality', 4, 100, null, $this->user);

    $finalized = $this->service->finalize($review->refresh(), $this->user, 4.75);

    expect((float) $finalized->overall_rating)->toBe(4.75);

    expect(fn () => $this->service->recordRating($finalized, 'Quality', 1, 100, null, $this->user))
        ->toThrow(ValidationException::class);
});

it('acknowledges only finalized reviews', function (): void {
    $cycle = $this->service->activateCycle($this->cycle, $this->user);
    $review = $this->service->startReview($cycle, $this->employee, null, $this->user);

    expect(fn () => $this->service->acknowledge($review, $this->user))
        ->toThrow(ValidationException::class);

    $this->service->recordRating($review, 'Quality', 4, 100, null, $this->user);
    $this->service->finalize($review->refresh(), $this->user);

    $acknowledged = $this->service->acknowledge($review->refresh(), $this->user);

    expect($acknowledged->status)->toBe(PerformanceReview::STATUS_ACKNOWLEDGED)
        ->and($acknowledged->acknowledged_at)->not->toBeNull();
});
