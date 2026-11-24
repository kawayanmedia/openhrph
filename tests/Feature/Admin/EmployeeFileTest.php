<?php

use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders the printable 201 file for an employee', function () {
    $company = Company::factory()->create(['name' => 'Acme', 'type' => 'Private']);
    app(TenantContext::class)->set($company->id);

    $employee = Employee::factory()->create([
        'company_id' => $company->id,
        'first_name' => 'Ana',
        'last_name' => 'Cruz',
        'tin_number' => '123-456-789-000',
        'gsis_number' => '0100-0000-0001',
    ]);

    $user = User::factory()->forCompany($company)->create(['owner' => true]);

    $this->actingAs($user)
        ->get(route('admin.employees.file', $employee))
        ->assertOk()
        ->assertSee('Employee 201 File')
        ->assertSee('Cruz')
        ->assertSee('123-456-789-000')
        ->assertSee('0100-0000-0001');
});

it('does not expose another company employee', function () {
    $companyA = Company::factory()->create(['name' => 'Alpha', 'type' => 'Private']);
    $companyB = Company::factory()->create(['name' => 'Beta', 'type' => 'Private']);

    $employeeB = app(TenantContext::class)->run($companyB->id, fn (): Employee => Employee::factory()->create([
        'company_id' => $companyB->id,
        'last_name' => 'Hidden',
    ]));

    $user = User::factory()->forCompany($companyA)->create(['owner' => true]);

    $this->actingAs($user)
        ->get(route('admin.employees.file', $employeeB))
        ->assertNotFound();
});
