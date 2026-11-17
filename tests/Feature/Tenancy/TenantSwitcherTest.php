<?php

use App\Livewire\Admin\CompanySwitcher;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->companyA = Company::factory()->create(['name' => 'Alpha']);
    $this->companyB = Company::factory()->create(['name' => 'Beta']);
    $this->companyC = Company::factory()->create(['name' => 'Gamma']);

    $this->user = User::factory()->forCompany($this->companyA)->create([
        'owner' => true,
        'email' => 'multi@example.com',
        'password' => 'secret',
    ]);

    $this->user->companies()->attach($this->companyA->id, ['is_owner' => true]);
    $this->user->companies()->attach($this->companyB->id, ['is_owner' => false]);
});

it('switches the active company for a member', function () {
    $this->actingAs($this->user);

    Livewire::test(CompanySwitcher::class)
        ->call('switchTo', $this->companyB->id)
        ->assertRedirect(route('admin.dashboard'));

    expect($this->user->refresh()->company_id)->toBe($this->companyB->id)
        ->and(session(config('tenancy.session_key')))->toBe($this->companyB->id);
});

it('rejects switching to a company the user does not belong to', function () {
    $this->actingAs($this->user);

    expect(fn () => (new CompanySwitcher)->switchTo($this->companyC->id))
        ->toThrow(HttpException::class);

    expect($this->user->refresh()->company_id)->toBe($this->companyA->id);
});
