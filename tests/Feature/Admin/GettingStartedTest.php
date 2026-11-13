<?php

use App\Livewire\Admin\GettingStarted;
use App\Models\Employee;
use App\Models\EmploymentType;
use App\Models\Holiday;
use App\Models\MinimumWageRate;
use App\Models\Office;
use App\Models\Payroll;
use App\Models\PayrollDate;
use App\Models\PayrollGroup;
use App\Models\Schedule;
use App\Models\Setting;
use App\Models\Timesheet;
use App\Models\User;
use App\Services\Payroll\PayrollReadiness;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create([
        'company_id' => 1,
        'owner' => true,
        'name' => 'Admin User',
        'email' => 'admin@example.com',
        'password' => 'secret',
    ]);

    $this->actingAs($this->user);
});

it('shows the outstanding steps for a company without payroll data', function () {
    Livewire::test(GettingStarted::class)
        ->assertOk()
        ->assertSee('Getting started')
        ->assertSee('Add an office or location')
        ->assertSee('Create a payroll group')
        ->assertSee('Create your first payroll run')
        ->assertViewHas('readiness', function (array $readiness) {
            return $readiness['required_total'] === 9
                && $readiness['required_complete'] === 0
                && $readiness['required_pending'] === 9
                && $readiness['is_ready'] === false;
        });
});

it('reports readiness once every required step is complete', function () {
    Office::factory()->create(['name' => 'Head office']);
    EmploymentType::factory()->create(['name' => 'Regular']);

    $group = PayrollGroup::factory()->create([
        'name' => 'Monthly',
        'frequency' => PayrollGroup::FREQUENCY_MONTHLY,
        'days_year' => 261,
        'hours_day' => 8,
        'first_cut_off' => 25,
        'first_pay_date' => 30,
        'is_default' => true,
    ]);

    $period = PayrollDate::factory()->create([
        'payroll_group_id' => $group->id,
        'payroll_date' => now()->endOfMonth()->toDateString(),
        'attendance_date_start' => now()->startOfMonth()->toDateString(),
        'attendance_date_end' => now()->endOfMonth()->toDateString(),
        'status' => PayrollDate::STATUS_OPEN,
    ]);

    $employee = Employee::factory()->create([
        'office_id' => null,
        'payroll_group_id' => $group->id,
        'employee_number' => 'EMP-001',
        'first_name' => 'Ana',
        'last_name' => 'Reyes',
        'basic_salary' => 3000000,
        'payment_type' => Employee::PAYMENT_TYPE_MONTHLY,
        'timesheet' => true,
        'sss_number' => '00-0000000-0',
        'philhealth_number' => '00-000000000-0',
        'hdmf_number' => '0000-0000-0000',
        'tin_number' => '000-000-000-000',
        'tax_status' => 'S',
    ]);

    Timesheet::factory()->create([
        'employee_id' => $employee->id,
        'log_date' => $period->attendance_date_start->copy()->addDay()->toDateString(),
    ]);

    foreach (['tin', 'sss', 'phil_health', 'hdmf', 'sss_schedule', 'phil_health_schedule', 'hdmf_schedule'] as $key) {
        Setting::factory()->create(['key' => $key, 'value' => 'set']);
    }

    MinimumWageRate::factory()->create(['region' => 'NCR', 'industry' => 'Non-agriculture', 'wage_start' => 61000]);
    Schedule::factory()->create(['name' => 'Day shift']);
    Holiday::factory()->create(['date' => now()->toDateString(), 'name' => 'Company anniversary']);

    Payroll::factory()->create([
        'payroll_group_id' => $group->id,
        'payroll_date' => $period->payroll_date,
        'date_from' => $period->attendance_date_start,
        'date_to' => $period->attendance_date_end,
        'status' => Payroll::STATUS_PROCESSED,
    ]);

    Livewire::test(GettingStarted::class)
        ->assertOk()
        ->assertSee('Payroll is ready to run')
        ->assertViewHas('readiness', function (array $readiness) {
            return $readiness['is_ready'] === true
                && $readiness['percent'] === 100
                && $readiness['required_complete'] === $readiness['required_total']
                && $readiness['warnings'] === [];
        });
});

it('generates the pay schedule from the checklist', function () {
    PayrollGroup::factory()->create([
        'name' => 'Monthly',
        'frequency' => PayrollGroup::FREQUENCY_MONTHLY,
        'days_year' => 261,
        'hours_day' => 8,
        'first_cut_off' => 25,
        'first_pay_date' => 15,
        'is_default' => true,
    ]);

    expect(PayrollDate::query()->count())->toBe(0);

    Livewire::test(GettingStarted::class)
        ->call('generatePayrollDates')
        ->assertHasNoErrors()
        ->assertSee('open periods');

    expect(PayrollDate::query()->where('status', PayrollDate::STATUS_OPEN)->count())->toBeGreaterThan(0);
});

it('redirects guests to the admin login', function () {
    auth()->guard('web')->logout();
    auth()->guard('nova')->logout();

    $this->get('/app/getting-started')->assertRedirect(route('admin.login'));
});

it('shows the setup progress banner on the dashboard while steps remain', function () {
    $readiness = app(PayrollReadiness::class)->summary();

    $html = view('livewire.admin.dashboard', ['metrics' => collect(), 'readiness' => $readiness])->render();

    expect($html)
        ->toContain('Finish setting up payroll')
        ->toContain(route('admin.getting-started'));
});

it('hides the setup progress banner once payroll is ready', function () {
    $readiness = app(PayrollReadiness::class)->summary();
    $readiness['is_ready'] = true;

    $html = view('livewire.admin.dashboard', ['metrics' => collect(), 'readiness' => $readiness])->render();

    expect($html)->not->toContain('Finish setting up payroll');
});
