<?php

use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::factory()->create(['name' => 'Acme', 'type' => 'Private']);
    app(TenantContext::class)->set($this->company->id);

    $this->employee = Employee::factory()->create([
        'company_id' => $this->company->id,
        'employee_number' => 'E-201',
        'first_name' => 'Ana',
        'last_name' => 'Cruz',
        'tin_number' => '123-456-789-000',
        'sss_number' => '00-0000001-0',
    ]);

    $this->user = User::factory()->forCompany($this->company)->create(['owner' => true]);
});

it('renders the employee master file', function () {
    $this->actingAs($this->user)
        ->get(route('admin.reports.employee-master-file'))
        ->assertOk()
        ->assertSee('Employee master file')
        ->assertSee('Cruz')
        ->assertSee('E-201');
});

it('exports the employee master file as CSV', function () {
    $response = $this->actingAs($this->user)
        ->get(route('admin.reports.employee-master-file.csv'));

    $response->assertOk();

    expect($response->headers->get('content-type'))->toContain('text/csv')
        ->and($response->streamedContent())->toContain('E-201')
        ->and($response->streamedContent())->toContain('Cruz');
});
