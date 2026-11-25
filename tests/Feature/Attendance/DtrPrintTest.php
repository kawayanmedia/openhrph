<?php

use App\Models\Company;
use App\Models\Employee;
use App\Models\Schedule;
use App\Models\Timesheet;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->company = Company::factory()->create(['name' => 'Acme']);

    app(TenantContext::class)->set($this->company->id);

    Schedule::factory()->create([
        'company_id' => $this->company->id,
        'am_in' => '08:00',
        'am_out' => '12:00',
        'pm_in' => '13:00',
        'pm_out' => '17:00',
        'is_default' => true,
    ]);

    $this->employee = Employee::factory()->create([
        'company_id' => $this->company->id,
        'status' => 1,
    ]);

    Timesheet::factory()->create([
        'company_id' => $this->company->id,
        'employee_id' => $this->employee->id,
        'log_date' => '2026-03-02',
        'am_login' => '08:05',
        'am_logout' => '12:00',
        'pm_login' => '13:00',
        'pm_logout' => '17:10',
    ]);

    $this->owner = User::factory()->create([
        'company_id' => $this->company->id,
        'owner' => true,
    ]);
});

it('renders a DTR pdf from the employee timesheets', function (): void {
    $response = $this->actingAs($this->owner)->get(route('timesheet.print', [
        'employee_id' => $this->employee->id,
        'month' => 3,
        'year' => 2026,
    ]));

    $response->assertOk();

    expect($response->headers->get('content-type'))->toContain('application/pdf')
        ->and($response->getContent())->toStartWith('%PDF');
});

it('renders the DTR for an entire office', function (): void {
    $response = $this->actingAs($this->owner)->get(route('timesheet.print', [
        'office_id' => $this->employee->office_id,
        'month' => 3,
        'year' => 2026,
        'period_from' => '2026-03-01',
        'period_to' => '15',
    ]));

    $response->assertOk();

    expect($response->getContent())->toStartWith('%PDF');
});

it('requires authentication to print a DTR', function (): void {
    $this->get(route('timesheet.print', ['employee_id' => $this->employee->id]))
        ->assertRedirect(route('admin.login'));
});
