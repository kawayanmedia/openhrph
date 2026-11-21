<?php

use App\Livewire\Admin\AdminForm;
use App\Livewire\Admin\Resources\Employee;
use App\Models\Company;
use App\Models\Deduction;
use App\Models\Employee as EmployeeModel;
use App\Models\EmployeeDeduction;
use App\Models\PayrollGroup;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::factory()->create(['name' => 'Acme']);

    app(TenantContext::class)->set($this->company->id);

    $this->user = User::factory()->create([
        'company_id' => $this->company->id,
        'owner' => true,
        'email' => 'admin@example.com',
    ]);

    $this->actingAs($this->user);
});

it('defaults the statutory premium switches to on for a new employee', function () {
    $form = Livewire::test(AdminForm::class, ['resource' => 'employee']);

    foreach ([
        'timesheet',
        'regular_holiday_pay',
        'special_holiday_pay',
        'premium_holiday_pay',
        'rest_day_pay',
        'overtime_pay',
        'deminimis',
        'night_differential',
    ] as $attribute) {
        expect($form->get("form.{$attribute}"))->toBeTrue("{$attribute} should default to on");
    }
});

it('rejects an employee number longer than ten characters', function () {
    Livewire::test(AdminForm::class, ['resource' => 'employee'])
        ->set('form.employee_number', 'E2E-OB-0001')
        ->call('save')
        ->assertHasErrors(['form.employee_number']);
});

it('accepts a fixed deduction without a rate', function () {
    Livewire::test(AdminForm::class, ['resource' => 'deduction'])
        ->set('form.name', 'Uniform')
        ->set('form.calculation', 'fixed')
        ->set('form.amount', 500)
        ->call('save')
        ->assertHasNoErrors();

    expect(Deduction::query()->where('name', 'Uniform')->exists())->toBeTrue();
});

it('stores a non-recurring employee deduction without a frequency', function () {
    $employee = EmployeeModel::factory()->create([
        'company_id' => $this->company->id,
        'employee_number' => 'E-0001',
    ]);

    $deduction = (new Deduction)->forceFill([
        'company_id' => $this->company->id,
        'name' => 'One-time',
    ]);
    $deduction->save();

    Livewire::test(AdminForm::class, ['resource' => 'employee-deduction'])
        ->set('form.employee', $employee->id)
        ->set('form.deduction', $deduction->id)
        ->set('form.amount', 500)
        ->set('form.is_recurring', false)
        ->call('save')
        ->assertHasNoErrors();

    expect(EmployeeDeduction::query()->where('employee_id', $employee->id)->first()?->frequency)->toBeNull();
});

it('renders the payroll group name on the employee detail field', function () {
    $group = PayrollGroup::factory()->create([
        'company_id' => $this->company->id,
        'name' => 'Monthly Rank and File',
    ]);

    $employee = EmployeeModel::factory()->create([
        'company_id' => $this->company->id,
        'employee_number' => 'E-0002',
        'payroll_group_id' => $group->id,
    ]);

    $field = collect(app(Employee::class)->formFields(request(), false))
        ->first(fn ($field) => $field->attribute === 'payroll_group');

    expect($field)->not->toBeNull()
        ->and($field->resolveForDisplay($employee))->toBe('Monthly Rank and File');
});
