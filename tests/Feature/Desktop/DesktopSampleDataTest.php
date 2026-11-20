<?php

use App\Livewire\Admin\GettingStarted;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Office;
use App\Models\Payroll;
use App\Models\PayrollGroup;
use App\Models\Schedule;
use App\Models\Setting;
use App\Models\Timesheet;
use App\Models\User;
use App\Services\Demo\DemoWorkspaceSeeder;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['nativephp-internal.running' => true]);

    $this->owner = User::factory()->owner()->create();
    $this->company = Company::factory()->create(['user_id' => $this->owner->getKey()]);

    $this->owner->forceFill(['company_id' => $this->company->getKey()])->save();

    $this->actingAs($this->owner);

    $this->workspaceCounts = fn (): array => app(TenantContext::class)->run($this->company, fn (): array => [
        'offices' => Office::query()->count(),
        'departments' => Department::query()->count(),
        'schedules' => Schedule::query()->count(),
        'payroll_groups' => PayrollGroup::query()->count(),
        'employees' => Employee::query()->count(),
        'timesheets' => Timesheet::query()->count(),
        'payrolls' => Payroll::query()->count(),
        'settings' => Setting::query()->count(),
    ]);
});

it('seeds the sample workspace without duplicating it on a second run', function () {
    $seeder = app(DemoWorkspaceSeeder::class);

    $seeder->seed($this->company);

    $firstRun = ($this->workspaceCounts)();

    $seeder->seed($this->company);

    expect($firstRun['employees'])->toBe(3)
        ->and($firstRun['offices'])->toBe(1)
        ->and($firstRun['departments'])->toBe(1)
        ->and($firstRun['schedules'])->toBe(1)
        ->and($firstRun['payroll_groups'])->toBe(1)
        ->and($firstRun['timesheets'])->toBeGreaterThan(0)
        ->and($firstRun['payrolls'])->toBe(1)
        ->and($firstRun['settings'])->toBeGreaterThan(0)
        ->and(($this->workspaceCounts)())->toBe($firstRun)
        ->and($seeder->hasSampleData($this->company))->toBeTrue();
});

it('loads sample data from the getting started page in the desktop app', function () {
    Livewire::test(GettingStarted::class)
        ->assertSee('Load sample data')
        ->call('loadSampleData')
        ->assertHasNoErrors()
        ->assertDontSee('Load sample data');

    expect(app(DemoWorkspaceSeeder::class)->hasSampleData($this->company))->toBeTrue()
        ->and(($this->workspaceCounts)()['employees'])->toBe(3);
});

it('does not load sample data outside the desktop runtime', function () {
    config(['nativephp-internal.running' => false]);

    Livewire::test(GettingStarted::class)
        ->call('loadSampleData')
        ->assertStatus(404);

    expect(app(DemoWorkspaceSeeder::class)->hasSampleData($this->company))->toBeFalse();
});

it('refuses to mix sample data into a company that already has employees', function () {
    Employee::factory()->create(['employee_number' => 'EMP-900']);

    Livewire::test(GettingStarted::class)->call('loadSampleData');

    expect(app(DemoWorkspaceSeeder::class)->hasCustomEmployees($this->company))->toBeTrue()
        ->and(app(DemoWorkspaceSeeder::class)->hasSampleData($this->company))->toBeFalse();
});
