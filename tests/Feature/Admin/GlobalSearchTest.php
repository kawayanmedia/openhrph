<?php

use App\Livewire\Admin\GlobalSearch;
use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use App\Services\Search\GlobalSearchService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('finds employees and navigation in the command palette', function () {
    $company = Company::factory()->create(['name' => 'Acme', 'type' => 'Private']);
    app(TenantContext::class)->set($company->id);

    $employee = Employee::factory()->create([
        'company_id' => $company->id,
        'first_name' => 'Ana',
        'last_name' => 'Cruz',
    ]);

    $user = User::factory()->forCompany($company)->create(['owner' => true]);

    $this->actingAs($user);

    Livewire::test(GlobalSearch::class)
        ->set('query', 'Cruz')
        ->assertSee('Cruz')
        ->set('query', 'payroll')
        ->assertSee('Payroll');
});

it('does not leak other tenant records', function () {
    $companyA = Company::factory()->create(['name' => 'Alpha', 'type' => 'Private']);
    $companyB = Company::factory()->create(['name' => 'Beta', 'type' => 'Private']);

    app(TenantContext::class)->run($companyB->id, fn () => Employee::factory()->create([
        'company_id' => $companyB->id,
        'first_name' => 'Beta',
        'last_name' => 'Hidden',
    ]));

    $user = User::factory()->forCompany($companyA)->create(['owner' => true]);

    $this->actingAs($user);

    $groups = app(GlobalSearchService::class)->search($user, 'Hidden');
    $labels = collect($groups)->flatten(1)->pluck('label');

    expect($labels->contains(fn (string $label): bool => str_contains($label, 'Hidden')))->toBeFalse();
});
