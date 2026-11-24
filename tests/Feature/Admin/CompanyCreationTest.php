<?php

use App\Livewire\Admin\AdminForm;
use App\Livewire\Admin\AdminIndex;
use App\Models\Company;
use App\Models\Group;
use App\Models\PayrollGroup;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function actingCompanyOwner(Company $company): User
{
    app(TenantContext::class)->set($company->id);

    $owner = User::factory()->forCompany($company)->create([
        'owner' => false,
        'email' => 'owner-'.uniqid().'@example.com',
    ]);

    $owner->companies()->attach($company->id, ['is_owner' => true]);

    return $owner;
}

it('lets a company owner create another company with its defaults', function () {
    $existing = Company::factory()->create(['name' => 'Alpha', 'type' => 'Private']);
    $owner = actingCompanyOwner($existing);

    $this->actingAs($owner);

    Livewire::test(AdminForm::class, ['resource' => 'company'])
        ->set('form.name', 'Beta Holdings')
        ->set('form.code', 'BETA')
        ->set('form.type', 'Private')
        ->set('form.tin', '123-456-789-000')
        ->set('form.rdo', '049')
        ->set('form.hdmf', '1234-5678-9012')
        ->set('form.phil_health', '12-345678901-2')
        ->call('save');

    $beta = Company::query()->where('name', 'Beta Holdings')->first();

    expect($beta)->not->toBeNull()
        ->and((int) $beta->user_id)->toBe($owner->getKey())
        ->and($owner->fresh()->ownsCompany((int) $beta->getKey()))->toBeTrue()
        ->and($owner->fresh()->belongsToCompany((int) $beta->getKey()))->toBeTrue()
        ->and(PayrollGroup::withoutTenant()->where('company_id', $beta->getKey())->exists())->toBeTrue()
        ->and(Group::withoutTenant()->where('company_id', $beta->getKey())->where('name', 'Administrator')->exists())->toBeTrue();
});

it('denies company creation to a user who owns no company', function () {
    $company = Company::factory()->create(['type' => 'Private']);
    app(TenantContext::class)->set($company->id);

    $staff = User::factory()->forCompany($company)->create([
        'owner' => false,
        'email' => 'staff@example.com',
    ]);

    expect($staff->can('create', Company::class))->toBeFalse();
});

it('only lists companies the user belongs to', function () {
    $alpha = Company::factory()->create(['name' => 'Alpha', 'type' => 'Private']);
    $beta = Company::factory()->create(['name' => 'Beta', 'type' => 'Private']);
    $gamma = Company::factory()->create(['name' => 'Gamma', 'type' => 'Private']);

    $owner = actingCompanyOwner($alpha);
    $owner->companies()->attach($beta->id, ['is_owner' => false]);

    $this->actingAs($owner);

    Livewire::test(AdminIndex::class, ['resource' => 'company'])
        ->assertSee('Alpha')
        ->assertSee('Beta')
        ->assertDontSee('Gamma');
});
