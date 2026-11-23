<?php

use App\Livewire\Admin\Resources\Shift;
use App\Livewire\Admin\Resources\ShiftAssignment;
use App\Models\Company;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::factory()->create(['name' => 'Acme']);

    app(TenantContext::class)->set($this->company->id);

    $this->actingAs(User::factory()->create(['company_id' => $this->company->id, 'owner' => true]));
});

it('builds the shift and assignment forms', function () {
    $shiftFields = (new Shift)->fields(request());
    $assignmentFields = (new ShiftAssignment)->fields(request());

    expect($shiftFields)->not->toBeEmpty()
        ->and(collect($shiftFields)->pluck('attribute')->all())->toContain('segments')
        ->and($assignmentFields)->not->toBeEmpty()
        ->and(collect($assignmentFields)->pluck('attribute')->all())->toContain('effective_from');
});
