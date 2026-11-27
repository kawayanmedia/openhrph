<?php

use App\Models\Company;
use App\Models\Employee;
use App\Models\RetirementCalculation;
use App\Models\RetirementCase;
use App\Models\RetirementPlan;
use App\Models\User;
use App\Services\Retirement\RetirementEligibilityService;
use App\Services\Retirement\RetirementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->company = Company::factory()->create(['name' => 'Retirement Co']);
    $this->user = User::factory()->forCompany($this->company)->create();
    $this->actingAs($this->user);

    $this->employee = Employee::factory()->create([
        'company_id' => $this->company->id,
        'date_hired' => now()->subYears(20)->toDateString(),
        'basic_salary' => 40000,
        'daily_rate' => 1600,
    ]);

    $this->employee->forceFill(['birth_date' => now()->subYears(60)->subDay()->toDateString()])->save();

    $this->plan = RetirementPlan::create([
        'company_id' => $this->company->id,
        'name' => 'Private Defined Benefit',
        'sector' => 'Private',
        'plan_type' => RetirementPlan::TYPE_DEFINED_BENEFIT,
        'eligibility_age' => 60,
        'minimum_service_years' => 15,
        'benefit_formula' => ['type' => 'percentage_of_salary', 'rate' => 0.025],
        'effective_from' => now()->subYear()->toDateString(),
        'is_active' => true,
    ]);

    $this->service = app(RetirementService::class);
});

it('evaluates age and service eligibility for a plan', function (): void {
    $eligibility = app(RetirementEligibilityService::class);

    $eligible = $eligibility->evaluate($this->employee, $this->plan);

    expect($eligible['eligible'])->toBeTrue()
        ->and($eligible['age'])->toBe(60)
        ->and($eligible['service_years'])->toBeGreaterThanOrEqual(19.9);

    $this->employee->forceFill(['birth_date' => now()->subYears(45)->toDateString()])->save();

    $ineligible = $eligibility->evaluate($this->employee->refresh(), $this->plan);

    expect($ineligible['eligible'])->toBeFalse()
        ->and($ineligible['reasons'])->toContain('Minimum retirement age is 60.');
});

it('opens a retirement case with an eligibility snapshot', function (): void {
    $case = $this->service->openCase($this->employee, $this->plan, [
        'retirement_type' => RetirementCase::TYPE_OPTIONAL,
        'proposed_retirement_date' => now()->addMonths(3)->toDateString(),
    ], $this->user);

    expect($case->status)->toBe(RetirementCase::STATUS_DRAFT)
        ->and($case->eligibility_snapshot['eligible'])->toBeTrue()
        ->and((int) $case->company_id)->toBe($this->company->id);

    $assessed = $this->service->assessEligibility($case, $this->user);

    expect($assessed->status)->toBe(RetirementCase::STATUS_ELIGIBILITY_REVIEW);
});

it('keeps provisional calculations separate from approval', function (): void {
    $case = $this->service->openCase($this->employee, $this->plan, [
        'proposed_retirement_date' => now()->addMonths(3)->toDateString(),
    ], $this->user);

    $calculation = $this->service->calculate($case, [
        'service_years' => 20,
        'monthly_salary' => 40000,
    ], $this->user);

    expect($calculation->status)->toBe(RetirementCalculation::STATUS_DRAFT)
        ->and((float) $calculation->amount)->toBe(20000.0)
        ->and($calculation->outputs['provisional'])->toBeTrue()
        ->and($calculation->rule_version)->toStartWith('retirement_plan:');

    $approved = $this->service->reviewCalculation($calculation, $this->user, true, 'Checked against service record.');

    expect($approved->status)->toBe(RetirementCalculation::STATUS_APPROVED)
        ->and($approved->reviewed_at)->not->toBeNull();

    expect(fn () => $approved->update(['amount' => 1]))
        ->toThrow(LogicException::class);
});

it('requires an approved calculation before completing retirement', function (): void {
    $case = $this->service->openCase($this->employee, $this->plan, [
        'proposed_retirement_date' => now()->toDateString(),
    ], $this->user);

    $case = $this->service->transition($case, RetirementCase::STATUS_APPLIED, $this->user);
    $case = $this->service->transition($case, RetirementCase::STATUS_APPROVED, $this->user);

    expect(fn () => $this->service->transition($case, RetirementCase::STATUS_COMPLETED, $this->user))
        ->toThrow(ValidationException::class);

    $calculation = $this->service->calculate($case, ['service_years' => 20, 'monthly_salary' => 40000], $this->user);
    $this->service->reviewCalculation($calculation, $this->user, true);

    $completed = $this->service->transition($case->refresh(), RetirementCase::STATUS_COMPLETED, $this->user);

    expect($completed->status)->toBe(RetirementCase::STATUS_COMPLETED)
        ->and($completed->completed_at)->not->toBeNull()
        ->and($this->employee->refresh()->lifecycle_status)->toBe(Employee::LIFECYCLE_SEPARATED);
});

it('calculates terminal leave and tracks external claims', function (): void {
    $case = $this->service->openCase($this->employee, $this->plan, [], $this->user);

    $terminal = $this->service->calculate($case, [
        'days' => 30,
        'daily_rate' => 1600,
    ], $this->user, RetirementCalculation::TYPE_TERMINAL_LEAVE);

    expect((float) $terminal->amount)->toBe(48000.0)
        ->and($terminal->calculation_type)->toBe(RetirementCalculation::TYPE_TERMINAL_LEAVE);

    $claim = $this->service->trackExternalClaim($case, [
        'agency' => 'GSIS',
        'reference' => 'GSIS-2026-0001',
        'status' => 'submitted',
    ], $this->user);

    expect($claim->status)->toBe('submitted')
        ->and($claim->submitted_at)->not->toBeNull();

    $released = $this->service->trackExternalClaim($case, [
        'agency' => 'GSIS',
        'status' => 'released',
    ], $this->user);

    expect($released->id)->toBe($claim->id)
        ->and($released->released_at)->not->toBeNull();
});
