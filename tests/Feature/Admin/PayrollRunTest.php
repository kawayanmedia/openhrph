<?php

use App\Livewire\Admin\Payroll\PayrollRun;
use App\Models\Employee;
use App\Models\Payroll;
use App\Models\PayrollDetail;
use App\Models\PayrollGroup;
use App\Models\Timesheet;
use App\Models\User;
use App\Services\Payroll\Engine\EngineManager;
use App\Services\Payroll\Engine\EngineResult;
use App\Services\Payroll\Engine\PayrollEngine;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create([
        'company_id' => 1,
        'name' => 'Owner',
        'email' => 'owner@example.com',
        'password' => 'secret',
        'owner' => true,
    ]);

    $this->actingAs($this->user);

    $group = PayrollGroup::factory()->create(['company_id' => 1, 'name' => 'Semi-monthly']);

    $employee = Employee::factory()->create([
        'company_id' => 1,
        'payroll_group_id' => $group->id,
        'employee_number' => 'E-001',
        'first_name' => 'Ana',
        'last_name' => 'Reyes',
        'basic_salary' => 3000000,
        'office_id' => null,
    ]);

    $this->payroll = Payroll::factory()->create([
        'company_id' => 1,
        'payroll_group_id' => $group->id,
        'payroll_date' => now()->toDateString(),
        'date_from' => now()->startOfMonth()->toDateString(),
        'date_to' => now()->toDateString(),
        'status' => Payroll::STATUS_OPEN,
    ]);

    Timesheet::factory()->create([
        'company_id' => 1,
        'employee_id' => $employee->id,
        'employee_number' => 'E-001',
        'log_date' => now()->toDateString(),
    ]);

    $this->employee = $employee;
});

function fakeEngine(EngineResult $result): PayrollEngine
{
    return new class($result) implements PayrollEngine
    {
        public function __construct(private EngineResult $result) {}

        public function preview(Payroll $payroll): EngineResult
        {
            return $this->result;
        }

        public function process(Payroll $payroll, bool $force = false): EngineResult
        {
            return $this->result;
        }

        public function version(): ?string
        {
            return 'fake-1.0.0';
        }
    };
}

function previewResult(Payroll $payroll): EngineResult
{
    return new EngineResult(
        payrollId: (int) $payroll->getKey(),
        employees: 1,
        gross: '9654.96',
        deductions: '1225.00',
        net: '8429.96',
        payslips: [[
            'employee_id' => 1,
            'employee_number' => 'E-001',
            'name' => 'Ana Reyes',
            'gross' => '9654.96',
            'sss_employee' => '750.00',
            'philhealth_employee' => '375.00',
            'pagibig_employee' => '100.00',
            'withholding_tax' => '0.00',
            'total_deductions' => '1225.00',
            'net_pay' => '8429.96',
        ]],
        warnings: ['sample warning'],
        engineVersion: 'fake-1.0.0',
        dryRun: true,
    );
}

it('previews a payroll without writing details', function () {
    $this->app->instance(EngineManager::class, new class($this->payroll) extends EngineManager
    {
        public function __construct(private Payroll $payroll) {}

        public function engine(): PayrollEngine
        {
            return fakeEngine(previewResult($this->payroll));
        }
    });

    Livewire::test(PayrollRun::class, ['payroll' => $this->payroll])
        ->assertSet('previewed', true)
        ->assertSee('Ana Reyes')
        ->assertSee('8,429.96')
        ->assertSee('sample warning');

    expect(PayrollDetail::query()->count())->toBe(0)
        ->and($this->payroll->refresh()->status)->toBe(Payroll::STATUS_OPEN);
});

it('processes a payroll through the queue job and records the engine version', function () {
    $result = previewResult($this->payroll);

    $this->app->instance(EngineManager::class, new class($result) extends EngineManager
    {
        public function __construct(private EngineResult $result) {}

        public function engine(): PayrollEngine
        {
            return fakeEngine($this->result);
        }
    });

    Livewire::test(PayrollRun::class, ['payroll' => $this->payroll])
        ->call('process');

    $this->payroll->refresh();

    expect($this->payroll->status)->toBe(Payroll::STATUS_PROCESSED)
        ->and($this->payroll->engine_version)->toBe('fake-1.0.0')
        ->and($this->payroll->processed_employees)->toBe(1)
        ->and($this->payroll->processed_at)->not->toBeNull();
});

it('approves a processed payroll and locks it', function () {
    $this->payroll->forceFill([
        'status' => Payroll::STATUS_PROCESSED,
        'processed_at' => now(),
    ])->save();

    Livewire::test(PayrollRun::class, ['payroll' => $this->payroll])
        ->call('review')
        ->call('approve')
        ->assertHasNoErrors();

    $this->payroll->refresh();

    expect($this->payroll->status)->toBe(Payroll::STATUS_APPROVED)
        ->and($this->payroll->approved_by)->toBe($this->user->id)
        ->and($this->payroll->approved_at)->not->toBeNull();
});

it('refuses to approve a payroll that has not been processed', function () {
    Livewire::test(PayrollRun::class, ['payroll' => $this->payroll])
        ->call('approve')
        ->assertSet('error', 'Only a processed payroll can be approved.');

    expect($this->payroll->refresh()->status)->toBe(Payroll::STATUS_OPEN);
});

it('only lets the owner reopen an approved payroll', function () {
    $this->payroll->forceFill([
        'status' => Payroll::STATUS_APPROVED,
        'approved_at' => now(),
        'approved_by' => $this->user->id,
    ])->save();

    Livewire::test(PayrollRun::class, ['payroll' => $this->payroll])
        ->set('reopenReason', 'Correcting a missed allowance')
        ->call('reopen');

    expect($this->payroll->refresh()->status)->toBe(Payroll::STATUS_PROCESSED)
        ->and($this->payroll->reopen_reason)->toBe('Correcting a missed allowance');
});

it('requires a reason before reopening an approved payroll', function () {
    $this->payroll->forceFill([
        'status' => Payroll::STATUS_APPROVED,
        'approved_at' => now(),
        'approved_by' => $this->user->id,
    ])->save();

    Livewire::test(PayrollRun::class, ['payroll' => $this->payroll])
        ->call('reopen')
        ->assertSet('error', 'Enter a reason before reopening an approved payroll.');

    expect($this->payroll->refresh()->status)->toBe(Payroll::STATUS_APPROVED);
});

it('hides another company payroll', function () {
    $other = app(TenantContext::class)->run(2, fn () => Payroll::factory()->create([
        'company_id' => 2,
        'payroll_group_id' => 1,
        'status' => Payroll::STATUS_OPEN,
    ]));

    Livewire::test(PayrollRun::class, ['payroll' => $other])->assertNotFound();
});

it('requires an override reason when attendance is missing', function () {
    Timesheet::query()->delete();

    Livewire::test(PayrollRun::class, ['payroll' => $this->payroll])
        ->call('process')
        ->assertSet('status', Payroll::STATUS_OPEN);

    $this->payroll->refresh();

    expect($this->payroll->status)->toBe(Payroll::STATUS_OPEN);
});

it('requires a review or an approval override', function () {
    $this->payroll->forceFill([
        'status' => Payroll::STATUS_PROCESSED,
        'processed_at' => now(),
    ])->save();

    Livewire::test(PayrollRun::class, ['payroll' => $this->payroll])
        ->call('approve')
        ->assertSet('status', Payroll::STATUS_PROCESSED);

    $this->payroll->refresh();

    expect($this->payroll->status)->toBe(Payroll::STATUS_PROCESSED);
});
