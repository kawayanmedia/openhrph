<?php

use App\Livewire\Admin\Dashboards\Main;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('shows no placeholder or cross-tenant data on a new company dashboard', function () {
    $user = User::factory()->create([
        'company_id' => 1,
        'owner' => true,
        'name' => 'New Owner',
        'email' => 'new-owner@example.com',
        'password' => 'secret',
    ]);

    User::factory()->create(['company_id' => 2, 'name' => 'Other Tenant']);
    User::factory()->create(['company_id' => 2, 'name' => 'Another Tenant']);

    $this->actingAs($user);
    app(TenantContext::class)->set(1);

    Livewire::test(Main::class)
        ->assertOk()
        ->assertDontSee('Tricia Gonzales')
        ->assertDontSee('Trudis Santos')
        ->assertDontSee('May 2017')
        ->assertDontSee('April 2017')
        ->assertSee('Total Users');
});
