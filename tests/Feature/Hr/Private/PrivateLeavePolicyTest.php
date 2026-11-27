<?php

use App\Models\Company;
use App\Models\Employee;
use App\Models\Employment;
use App\Models\Leave;
use App\Models\LeavePolicy;
use App\Models\LeavePolicyRule;
use App\Models\User;
use App\Services\Leave\PrivateLeavePolicyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * @param  array<string, mixed>  $attributes
 */
function privateLeavePolicyRecord(Company $company, int $leaveTypeId, array $attributes = []): LeavePolicy
{
    return LeavePolicy::query()->create([
        'company_id' => $company->id,
        'leave_type_id' => $leaveTypeId,
        'entitlement_days' => 15,
        'accrual' => 'annual',
        'min_service_months' => 0,
        'is_active' => true,
        ...$attributes,
    ]);
}

/**
 * @param  array<string, mixed>  $attributes
 */
function privateLeavePolicyRule(Company $company, LeavePolicy $policy, array $attributes = []): LeavePolicyRule
{
    return LeavePolicyRule::query()->create([
        'company_id' => $company->id,
        'leave_policy_id' => $policy->id,
        'min_service_months' => 0,
        ...$attributes,
    ]);
}

/**
 * @param  array<string, mixed>  $attributes
 */
function privateLeavePolicyEmployment(Employee $employee, array $attributes = []): Employment
{
    return Employment::query()->create([
        'company_id' => $employee->company_id,
        'employee_id' => $employee->id,
        'classification' => Employment::CLASSIFICATION_REGULAR,
        'start_date' => '2025-01-01',
        'status' => Employment::STATUS_ACTIVE,
        ...$attributes,
    ]);
}

beforeEach(function () {
    $this->company = Company::factory()->create();
    $this->user = User::factory()->forCompany($this->company)->create();

    $this->actingAs($this->user);

    $this->service = app(PrivateLeavePolicyService::class);

    $this->employee = Employee::factory()->create([
        'company_id' => $this->company->id,
        'date_hired' => '2024-01-15',
    ]);

    $leaveTypeId = DB::table('leave_types')->insertGetId([
        'code' => 'VL',
        'name' => 'Vacation Leave',
        'deductable' => true,
        'deduct_to' => 0,
        'allow_per_year' => 15,
        'is_active' => true,
        'company_id' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->leaveType = Leave::query()->findOrFail($leaveTypeId);

    $this->asOf = Carbon::parse('2026-06-01');
});

it('reports a reason when no active policy exists', function () {
    $result = $this->service->resolve($this->employee, $this->leaveType, $this->asOf);

    expect($result['policy'])->toBeNull()
        ->and($result['rule'])->toBeNull()
        ->and($result['eligible'])->toBeFalse()
        ->and($result['reasons'])->toHaveCount(1)
        ->and($result['reasons'][0])->toContain('No active leave policy');
});

it('resolves an eligible employee against the matching rule', function () {
    $policy = privateLeavePolicyRecord($this->company, $this->leaveType->id, [
        'min_service_months' => 6,
    ]);

    $rule = privateLeavePolicyRule($this->company, $policy, [
        'employment_classification' => null,
        'min_service_months' => 6,
    ]);

    privateLeavePolicyEmployment($this->employee);

    $result = $this->service->resolve($this->employee, $this->leaveType, $this->asOf);

    expect($result['eligible'])->toBeTrue()
        ->and($result['reasons'])->toBe([])
        ->and($result['policy']->id)->toBe($policy->id)
        ->and($result['rule']->id)->toBe($rule->id);
});

it('reports insufficient service months as ineligible', function () {
    $policy = privateLeavePolicyRecord($this->company, $this->leaveType->id);

    privateLeavePolicyRule($this->company, $policy, [
        'min_service_months' => 24,
    ]);

    privateLeavePolicyEmployment($this->employee, ['start_date' => '2026-04-01']);

    $result = $this->service->resolve($this->employee, $this->leaveType, $this->asOf);

    expect($result['eligible'])->toBeFalse()
        ->and($result['reasons'])->toHaveCount(1)
        ->and($result['reasons'][0])->toContain('24')
        ->and($result['reasons'][0])->toContain('2 recorded');
});

it('prefers the classification specific rule over the generic rule', function () {
    $policy = privateLeavePolicyRecord($this->company, $this->leaveType->id);

    privateLeavePolicyRule($this->company, $policy, [
        'employment_classification' => null,
        'min_service_months' => 60,
    ]);

    $specific = privateLeavePolicyRule($this->company, $policy, [
        'employment_classification' => Employment::CLASSIFICATION_REGULAR,
        'min_service_months' => 0,
    ]);

    privateLeavePolicyEmployment($this->employee, [
        'classification' => Employment::CLASSIFICATION_REGULAR,
    ]);

    $result = $this->service->resolve($this->employee, $this->leaveType, $this->asOf);

    expect($result['eligible'])->toBeTrue()
        ->and($result['rule']->id)->toBe($specific->id)
        ->and($result['rule']->employment_classification)->toBe(Employment::CLASSIFICATION_REGULAR);
});

it('ignores rules that are not yet effective', function () {
    $policy = privateLeavePolicyRecord($this->company, $this->leaveType->id, [
        'min_service_months' => 0,
    ]);

    privateLeavePolicyRule($this->company, $policy, [
        'min_service_months' => 120,
        'effective_from' => '2027-01-01',
    ]);

    privateLeavePolicyEmployment($this->employee);

    $result = $this->service->resolve($this->employee, $this->leaveType, $this->asOf);

    expect($result['eligible'])->toBeTrue()
        ->and($result['rule'])->toBeNull()
        ->and($result['policy']->id)->toBe($policy->id);
});
