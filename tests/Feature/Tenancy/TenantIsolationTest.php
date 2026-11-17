<?php

use App\Livewire\Admin\AdminIndex;
use App\Models\Company;
use App\Models\Office;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->companyA = Company::factory()->create(['name' => 'Alpha']);
    $this->companyB = Company::factory()->create(['name' => 'Beta']);

    $this->user = User::factory()->forCompany($this->companyA)->create([
        'owner' => true,
        'name' => 'Alpha Admin',
        'email' => 'alpha@example.com',
        'password' => 'secret',
    ]);

    $this->officeA = app(TenantContext::class)->run($this->companyA->id, fn () => Office::factory()->create(['code' => 'A', 'name' => 'Alpha Office']));
    $this->officeB = app(TenantContext::class)->run($this->companyB->id, fn () => Office::factory()->create(['code' => 'B', 'name' => 'Beta Office']));
});

it('only lists records for the active company', function () {
    $this->actingAs($this->user);

    Livewire::test(AdminIndex::class, ['resource' => 'office'])
        ->assertSee('Alpha Office')
        ->assertDontSee('Beta Office');
});

it('returns 404 for another company record', function () {
    $this->actingAs($this->user);

    $this->get("/app/resources/office/{$this->officeB->id}")->assertNotFound();
});
