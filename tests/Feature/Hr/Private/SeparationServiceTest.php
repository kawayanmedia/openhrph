<?php

use App\Models\ClearanceTask;
use App\Models\ClearanceTemplate;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Employment;
use App\Models\FinalSettlement;
use App\Models\SeparationCase;
use App\Models\User;
use App\Services\Employment\EmploymentService;
use App\Services\Separation\SeparationService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::factory()->create(['name' => 'Private Employer']);

    $this->admin = User::factory()->forCompany($this->company)->create([
        'owner' => true,
        'name' => 'Private Admin',
    ]);

    $this->actingAs($this->admin);

    app(TenantContext::class)->set($this->company->id);

    $this->employee = Employee::factory()->create(['company_id' => $this->company->id]);

    $this->service = app(SeparationService::class);

    $this->resignation = fn (Employee $employee, array $attributes = []): SeparationCase => $this->service->initiate($employee, [
        'separation_type' => SeparationCase::TYPE_RESIGNATION,
        'proposed_effective_date' => '2026-01-15',
        'reason' => 'Personal reasons',
        ...$attributes,
    ], $this->admin);
});

it('initiates a separation case with the five default clearance tasks', function () {
    $case = $this->service->initiate($this->employee, [
        'separation_type' => SeparationCase::TYPE_RESIGNATION,
        'proposed_effective_date' => '2026-01-15',
        'reason' => 'Personal reasons',
    ], $this->admin);

    expect($case->status)->toBe(SeparationCase::STATUS_INITIATED)
        ->and($case->company_id)->toBe($this->company->id)
        ->and($case->employee_id)->toBe($this->employee->id)
        ->and($case->initiated_by)->toBe($this->admin->id)
        ->and($case->proposed_effective_date->toDateString())->toBe('2026-01-15')
        ->and($case->clearanceTasks)->toHaveCount(5)
        ->and($case->clearanceTasks->pluck('status')->unique()->all())->toBe([ClearanceTask::STATUS_PENDING]);
});

it('uses the supplied clearance template tasks instead of the defaults', function () {
    $template = ClearanceTemplate::create([
        'company_id' => $this->company->id,
        'name' => 'Standard exit',
        'version' => 1,
        'tasks' => [
            ['unit' => 'Security', 'task' => 'Return identification badge'],
            ['unit' => 'Records', 'task' => 'File exit documents'],
        ],
        'is_active' => true,
    ]);

    $case = $this->service->initiate($this->employee, [
        'separation_type' => SeparationCase::TYPE_RESIGNATION,
        'proposed_effective_date' => '2026-01-15',
        'reason' => 'Personal reasons',
        'template_id' => $template->id,
    ], $this->admin);

    expect($case->clearanceTasks)->toHaveCount(2)
        ->and($case->clearanceTasks->pluck('unit')->sort()->values()->all())->toBe(['Records', 'Security'])
        ->and($case->clearanceTasks->pluck('task')->sort()->values()->all())
        ->toBe(['File exit documents', 'Return identification badge']);
});

it('rejects invalid transitions and allows the path to approved', function () {
    $case = ($this->resignation)($this->employee);

    expect(fn () => $this->service->transition($case, SeparationCase::STATUS_EFFECTIVE, $this->admin))
        ->toThrow(ValidationException::class);

    expect($case->refresh()->status)->toBe(SeparationCase::STATUS_INITIATED);

    foreach ([
        SeparationCase::STATUS_REVIEW,
        SeparationCase::STATUS_CLEARANCE_IN_PROGRESS,
        SeparationCase::STATUS_FINAL_PAY_REVIEW,
        SeparationCase::STATUS_APPROVED,
    ] as $status) {
        $case = $this->service->transition($case, $status, $this->admin);

        expect($case->status)->toBe($status);
    }

    expect($case->approved_by)->toBe($this->admin->id)
        ->and($case->approved_at)->not->toBeNull();
});

it('applies the effective separation to employment and employee records', function () {
    $employment = app(EmploymentService::class)->start($this->employee, [
        'classification' => Employment::CLASSIFICATION_REGULAR,
        'start_date' => '2024-01-01',
    ], $this->admin);

    $case = ($this->resignation)($this->employee, [
        'proposed_effective_date' => '2026-02-28',
    ]);

    foreach ([
        SeparationCase::STATUS_REVIEW,
        SeparationCase::STATUS_CLEARANCE_IN_PROGRESS,
        SeparationCase::STATUS_FINAL_PAY_REVIEW,
        SeparationCase::STATUS_APPROVED,
    ] as $status) {
        $case = $this->service->transition($case, $status, $this->admin);
    }

    $case = $this->service->transition($case, SeparationCase::STATUS_EFFECTIVE, $this->admin, [
        'effective_date' => '2026-02-28',
    ]);

    expect($case->status)->toBe(SeparationCase::STATUS_EFFECTIVE)
        ->and($case->effective_date->toDateString())->toBe('2026-02-28');

    $employment->refresh();

    expect($employment->status)->toBe(Employment::STATUS_ENDED)
        ->and($employment->end_date->toDateString())->toBe('2026-02-28');

    $employee = $this->employee->refresh();

    expect($employee->lifecycle_status)->toBe(Employee::LIFECYCLE_SEPARATED)
        ->and($employee->date_resigned->toDateString())->toBe('2026-02-28');
});

it('marks clearance tasks completed or waived with a completion timestamp', function () {
    $case = ($this->resignation)($this->employee);
    $tasks = $case->clearanceTasks->values();

    $completed = $this->service->completeClearanceTask($tasks[0], ClearanceTask::STATUS_COMPLETED, $this->admin, [
        'notes' => 'Handed over documents',
    ]);

    expect($completed->status)->toBe(ClearanceTask::STATUS_COMPLETED)
        ->and($completed->completed_at)->not->toBeNull()
        ->and($completed->notes)->toBe('Handed over documents');

    $waived = $this->service->completeClearanceTask($tasks[1], ClearanceTask::STATUS_WAIVED, $this->admin);

    expect($waived->status)->toBe(ClearanceTask::STATUS_WAIVED)
        ->and($waived->completed_at)->not->toBeNull();

    expect(fn () => $this->service->completeClearanceTask($tasks[2], ClearanceTask::STATUS_PENDING, $this->admin))
        ->toThrow(ValidationException::class);
});

it('totals the final settlement and re-records into the same row', function () {
    $case = ($this->resignation)($this->employee);

    $settlement = $this->service->recordSettlement($case, [
        ['label' => 'Final pay', 'amount' => 10000],
        ['label' => 'Leave conversion', 'amount' => 2500.50],
    ], [
        ['label' => 'Loans', 'amount' => 1500],
        ['label' => 'Uniform', 'amount' => 500.50],
    ], $this->admin);

    expect((float) $settlement->total_amount)->toBe(10500.0)
        ->and($settlement->status)->toBe(FinalSettlement::STATUS_REVIEW)
        ->and($settlement->company_id)->toBe($this->company->id);

    $updated = $this->service->recordSettlement($case, [
        ['label' => 'Final pay', 'amount' => 12000],
    ], [
        ['label' => 'Loans', 'amount' => 2000],
    ], $this->admin, ['status' => FinalSettlement::STATUS_APPROVED]);

    expect($updated->id)->toBe($settlement->id)
        ->and((float) $updated->total_amount)->toBe(10000.0)
        ->and($updated->status)->toBe(FinalSettlement::STATUS_APPROVED)
        ->and(FinalSettlement::query()->where('separation_case_id', $case->id)->count())->toBe(1);
});

it('isolates separation cases and clearance tasks by tenant', function () {
    $otherCompany = Company::factory()->create(['name' => 'Other Employer']);

    $mine = ($this->resignation)($this->employee);

    $theirs = app(TenantContext::class)->run($otherCompany->id, function () use ($otherCompany): SeparationCase {
        $employee = Employee::factory()->create(['company_id' => $otherCompany->id]);

        return ($this->resignation)($employee);
    });

    app(TenantContext::class)->run($this->company->id, function () use ($mine, $theirs): void {
        expect(SeparationCase::query()->pluck('id')->all())->toBe([$mine->id])
            ->and(ClearanceTask::query()->pluck('separation_case_id')->unique()->values()->all())->toBe([$mine->id]);

        expect(SeparationCase::query()->whereKey($theirs->id)->exists())->toBeFalse();
    });

    expect(SeparationCase::withoutTenant()->count())->toBe(2)
        ->and($theirs->company_id)->toBe($otherCompany->id)
        ->and(ClearanceTask::withoutTenant()->where('separation_case_id', $theirs->id)->count())->toBe(5);
});
