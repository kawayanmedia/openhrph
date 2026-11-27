<?php

use App\Models\Company;
use App\Models\Employee;
use App\Models\Employment;
use App\Models\EmploymentChange;
use App\Models\EmploymentContract;
use App\Models\EmploymentType;
use App\Models\ProbationaryReview;
use App\Models\RegularizationDecision;
use App\Models\User;
use App\Services\Employment\EmploymentService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::factory()->create();
    $this->user = User::factory()->forCompany($this->company)->create();

    $this->actingAs($this->user);

    $this->service = app(EmploymentService::class);

    $this->employmentType = EmploymentType::factory()->create([
        'company_id' => $this->company->id,
    ]);

    $this->employee = Employee::factory()->create([
        'company_id' => $this->company->id,
        'date_hired' => null,
    ]);
});

it('starts a regular employment and projects it onto the employee', function () {
    $employment = $this->service->start($this->employee, [
        'employment_type_id' => $this->employmentType->id,
        'start_date' => '2026-01-05',
    ], $this->user);

    expect($employment->status)->toBe(Employment::STATUS_ACTIVE)
        ->and($employment->classification)->toBe(Employment::CLASSIFICATION_REGULAR)
        ->and($employment->company_id)->toBe($this->company->id)
        ->and($employment->employee_id)->toBe($this->employee->id)
        ->and($employment->start_date->toDateString())->toBe('2026-01-05');

    $employee = $this->employee->fresh();

    expect($employee->lifecycle_status)->toBe(Employee::LIFECYCLE_ACTIVE)
        ->and($employee->employment_type_id)->toBe($this->employmentType->id)
        ->and($employee->date_hired->toDateString())->toBe('2026-01-05');

    $change = EmploymentChange::withoutTenant()
        ->where('employee_id', $this->employee->id)
        ->sole();

    expect($change->change_type)->toBe(EmploymentChange::TYPE_HIRE)
        ->and($change->effective_date->toDateString())->toBe('2026-01-05')
        ->and($change->approved_by)->toBe($this->user->id)
        ->and($change->metadata['employment_id'])->toBe($employment->id)
        ->and($change->metadata['classification'])->toBe(Employment::CLASSIFICATION_REGULAR);
});

it('requires a probation end date for probationary employment', function () {
    expect(fn () => $this->service->start($this->employee, [
        'classification' => Employment::CLASSIFICATION_PROBATIONARY,
        'start_date' => '2026-01-05',
    ], $this->user))->toThrow(ValidationException::class);

    expect(Employment::withoutTenant()->where('employee_id', $this->employee->id)->count())->toBe(0);

    $employment = $this->service->start($this->employee, [
        'classification' => Employment::CLASSIFICATION_PROBATIONARY,
        'start_date' => '2026-01-05',
        'probation_end' => '2026-07-05',
    ], $this->user);

    expect($employment->classification)->toBe(Employment::CLASSIFICATION_PROBATIONARY)
        ->and($employment->probation_start->toDateString())->toBe('2026-01-05')
        ->and($employment->probation_end->toDateString())->toBe('2026-07-05');
});

it('rejects a second active employment period', function () {
    $this->service->start($this->employee, [
        'start_date' => '2026-01-05',
    ], $this->user);

    expect(fn () => $this->service->start($this->employee, [
        'start_date' => '2026-03-01',
    ], $this->user))->toThrow(ValidationException::class);

    expect(Employment::withoutTenant()->where('employee_id', $this->employee->id)->count())->toBe(1);
});

it('versions contracts and records the reference of a signed contract', function () {
    $employment = $this->service->start($this->employee, [
        'start_date' => '2026-01-05',
    ], $this->user);

    $first = $this->service->recordContract($employment, [
        'template_name' => 'Probationary Contract',
        'start_date' => '2026-01-05',
        'end_date' => '2026-07-05',
    ], $this->user);

    expect($first->version)->toBe(1)
        ->and($first->status)->toBe(EmploymentContract::STATUS_DRAFT);

    expect($employment->fresh()->contract_reference)->toBeNull();

    $second = $this->service->recordContract($employment, [
        'template_name' => 'Regular Contract',
        'start_date' => '2026-07-06',
        'status' => EmploymentContract::STATUS_SIGNED,
        'contract_reference' => 'CT-2026-002',
    ], $this->user);

    expect($second->version)->toBe(2)
        ->and($second->status)->toBe(EmploymentContract::STATUS_SIGNED)
        ->and($employment->fresh()->contract_reference)->toBe('CT-2026-002');
});

it('records a probationary review', function () {
    $employment = $this->service->start($this->employee, [
        'classification' => Employment::CLASSIFICATION_PROBATIONARY,
        'start_date' => '2026-01-05',
        'probation_end' => '2026-07-05',
    ], $this->user);

    $review = $this->service->recordProbationaryReview($employment, [
        'review_date' => '2026-04-05',
        'standards_reference' => 'STANDARDS-2026',
        'outcome' => ProbationaryReview::OUTCOME_PASS,
        'supervisor_comments' => 'Meets the performance standards.',
    ], $this->user);

    expect($review->employment_id)->toBe($employment->id)
        ->and($review->company_id)->toBe($this->company->id)
        ->and($review->review_date->toDateString())->toBe('2026-04-05')
        ->and($review->outcome)->toBe(ProbationaryReview::OUTCOME_PASS)
        ->and($review->evaluator_id)->toBe($this->user->id);

    $this->assertDatabaseHas('probationary_reviews', [
        'id' => $review->id,
        'employment_id' => $employment->id,
    ]);
});

it('regularizes employment through an approved decision', function () {
    $employment = $this->service->start($this->employee, [
        'classification' => Employment::CLASSIFICATION_PROBATIONARY,
        'start_date' => '2026-01-05',
        'probation_end' => '2026-07-05',
    ], $this->user);

    $decision = $this->service->decideRegularization($employment, [
        'decision' => RegularizationDecision::DECISION_REGULARIZE,
        'effective_date' => '2026-07-06',
        'notes' => 'Passed probation.',
    ], $this->user);

    expect($decision->decision)->toBe(RegularizationDecision::DECISION_REGULARIZE)
        ->and($decision->approved_by)->toBe($this->user->id);

    $employment = $employment->fresh();

    expect($employment->classification)->toBe(Employment::CLASSIFICATION_REGULAR)
        ->and($employment->regularization_date->toDateString())->toBe('2026-07-06')
        ->and($employment->status)->toBe(Employment::STATUS_ACTIVE);

    $change = EmploymentChange::withoutTenant()
        ->where('employee_id', $this->employee->id)
        ->where('change_type', EmploymentChange::TYPE_REGULARIZATION)
        ->sole();

    expect($change->effective_date->toDateString())->toBe('2026-07-06')
        ->and($change->metadata['employment_id'])->toBe($employment->id);
});

it('extends probation through the regularization decision', function () {
    $employment = $this->service->start($this->employee, [
        'classification' => Employment::CLASSIFICATION_PROBATIONARY,
        'start_date' => '2026-01-05',
        'probation_end' => '2026-07-05',
    ], $this->user);

    $this->service->decideRegularization($employment, [
        'decision' => RegularizationDecision::DECISION_EXTEND,
        'effective_date' => '2026-07-05',
        'probation_end' => '2026-10-05',
    ], $this->user);

    $employment = $employment->fresh();

    expect($employment->probation_end->toDateString())->toBe('2026-10-05')
        ->and($employment->classification)->toBe(Employment::CLASSIFICATION_PROBATIONARY)
        ->and($employment->regularization_date)->toBeNull();
});

it('rejects an unsupported regularization decision', function () {
    $employment = $this->service->start($this->employee, [
        'classification' => Employment::CLASSIFICATION_PROBATIONARY,
        'start_date' => '2026-01-05',
        'probation_end' => '2026-07-05',
    ], $this->user);

    expect(fn () => $this->service->decideRegularization($employment, [
        'decision' => 'promote',
        'effective_date' => '2026-07-05',
    ], $this->user))->toThrow(ValidationException::class);

    expect(RegularizationDecision::withoutTenant()->where('employment_id', $employment->id)->count())->toBe(0)
        ->and($employment->fresh()->classification)->toBe(Employment::CLASSIFICATION_PROBATIONARY);
});

it('ends an employment period', function () {
    $employment = $this->service->start($this->employee, [
        'start_date' => '2026-01-05',
    ], $this->user);

    $ended = $this->service->endEmployment($employment, '2026-09-30', 'Resignation', $this->user);

    expect($ended->status)->toBe(Employment::STATUS_ENDED)
        ->and($ended->end_date->toDateString())->toBe('2026-09-30');
});

it('isolates employments by tenant', function () {
    $this->service->start($this->employee, [
        'start_date' => '2026-01-05',
    ], $this->user);

    $otherCompany = Company::factory()->create();
    $otherUser = User::factory()->forCompany($otherCompany)->create();

    app(TenantContext::class)->run($otherCompany, function () use ($otherCompany, $otherUser): void {
        $otherEmployee = Employee::factory()->create([
            'company_id' => $otherCompany->id,
            'date_hired' => null,
        ]);

        $this->service->start($otherEmployee, [
            'start_date' => '2026-02-01',
        ], $otherUser);

        expect(Employment::query()->count())->toBe(1)
            ->and(Employment::query()->sole()->employee_id)->toBe($otherEmployee->id)
            ->and(Employment::query()->sole()->company_id)->toBe($otherCompany->id);
    });

    expect(Employment::query()->count())->toBe(1)
        ->and(Employment::query()->sole()->employee_id)->toBe($this->employee->id)
        ->and(Employment::withoutTenant()->count())->toBe(2);
});
