<?php

use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::factory()->create(['name' => 'Acme']);

    app(TenantContext::class)->set($this->company->id);

    $this->employee = Employee::factory()->create([
        'company_id' => $this->company->id,
        'employee_number' => 'E-0001',
        'first_name' => 'Ana',
        'last_name' => 'Cruz',
    ]);

    $this->user = User::factory()->forCompany($this->company)->create([
        'employee_id' => $this->employee->id,
        'name' => 'Ana Cruz',
        'email' => 'ana@example.com',
    ]);
});

it('renders the dashboard for a portal employee', function () {
    $this->actingAs($this->user, 'portal')
        ->get('/portal/dashboard')
        ->assertOk()
        ->assertSee('Dashboard');
});
