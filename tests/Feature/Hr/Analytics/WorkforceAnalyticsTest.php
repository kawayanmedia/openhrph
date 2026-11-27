<?php

use App\Models\Company;
use App\Models\Employee;
use App\Models\RetirementCase;
use App\Models\RetirementPlan;
use App\Models\TrainingEnrollment;
use App\Models\User;
use App\Services\Analytics\WorkforceAnalytics;
use App\Services\Compliance\ComplianceRegistry;
use App\Services\Learning\LearningService;
use App\Services\Performance\PerformanceService;
use App\Services\Retirement\RetirementService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->company = Company::factory()->create(['name' => 'Analytics Co']);
    $this->user = User::factory()->forCompany($this->company)->create();
    $this->actingAs($this->user);

    $this->employee = Employee::factory()->create([
        'company_id' => $this->company->id,
        'date_hired' => now()->subMonths(2)->toDateString(),
    ]);

    Employee::factory()->create([
        'company_id' => $this->company->id,
        'date_hired' => now()->subYears(3)->toDateString(),
    ]);

    Employee::factory()->create([
        'company_id' => $this->company->id,
        'date_hired' => now()->subYears(4)->toDateString(),
        'lifecycle_status' => Employee::LIFECYCLE_SEPARATED,
    ]);
});

it('summarises headcount, hires and retirement exposure', function (): void {
    $plan = RetirementPlan::create([
        'company_id' => $this->company->id,
        'name' => 'Statutory Plan',
        'sector' => 'Government',
        'plan_type' => RetirementPlan::TYPE_GSIS,
        'eligibility_age' => 60,
        'minimum_service_years' => 15,
        'is_active' => true,
    ]);

    $case = app(RetirementService::class)->openCase($this->employee, $plan, [
        'proposed_retirement_date' => now()->addMonths(6)->toDateString(),
    ], $this->user);

    app(RetirementService::class)->transition($case, RetirementCase::STATUS_APPLIED, $this->user);

    $summary = app(WorkforceAnalytics::class)->summary();

    expect($summary['active_headcount'])->toBe(2)
        ->and($summary['new_hires_90_days'])->toBe(1)
        ->and($summary['pending_retirements'])->toBe(1)
        ->and($summary['retirement_forecast_12_months'])->toBe(1);
});

it('summarises learning, performance and compliance completion', function (): void {
    $learning = app(LearningService::class);
    $course = $learning->createCourse([
        'company_id' => $this->company->id,
        'code' => 'MAND-01',
        'title' => 'Mandatory Orientation',
    ], $this->user);
    $session = $learning->scheduleSession($course, ['starts_at' => now()->subWeek()], $this->user);
    $enrollment = $learning->enroll($session, $this->employee, $this->user);
    $learning->recordCompletion($enrollment, TrainingEnrollment::STATUS_COMPLETED, 90, null, $this->user);

    $performance = app(PerformanceService::class);
    $cycle = $performance->createCycle([
        'company_id' => $this->company->id,
        'name' => '2026 Review',
        'period_start' => '2026-01-01',
        'period_end' => '2026-12-31',
    ], $this->user);
    $cycle = $performance->activateCycle($cycle, $this->user);
    $review = $performance->startReview($cycle, $this->employee, null, $this->user);
    $performance->recordRating($review, 'Quality', 4, 100, null, $this->user);
    $performance->finalize($review->refresh(), $this->user);

    $registry = app(ComplianceRegistry::class);
    $source = $registry->registerSource([
        'authority' => 'DOLE',
        'title' => 'Labor advisories',
        'effective_from' => '2020-01-01',
    ], $this->user);
    $rule = $registry->registerRule($source, [
        'code' => 'ANALYTICS_RULE',
        'name' => 'Analytics rule',
        'sector' => 'Private',
        'effective_from' => now()->subMonth()->toDateString(),
        'version' => '1.0.0',
    ], ['rate' => 1], $this->user);
    $version = $rule->versions->first();
    $registry->submitForReview($version, $this->user);
    $registry->approveVersion($version->refresh(), $this->user);

    $summary = app(WorkforceAnalytics::class)->summary();

    expect($summary['training_completion_rate'])->toBe(100.0)
        ->and($summary['performance_completion_rate'])->toBe(100.0)
        ->and($summary['approved_compliance_rules'])->toBe(1);
});
