<?php

use App\Enums\Sector;
use App\Livewire\Admin\Actions\OverrideLeaveType;
use App\Livewire\Admin\AdminForm;
use App\Livewire\Admin\Resources\Leave as LeaveResource;
use App\Models\Company;
use App\Models\Leave;
use App\Models\User;
use App\Sectors\Catalogues\LeaveTypeCatalogue;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('seeds the leave type catalogue as system-wide rows', function () {
    $catalogue = Leave::withoutGlobalScopes();

    expect($catalogue->whereNull('company_id')->count())
        ->toBe(count(LeaveTypeCatalogue::rows()))
        ->and($catalogue->whereNotNull('company_id')->count())->toBe(0);
});

it('syncs the catalogue without duplicating rows', function () {
    $before = Leave::withoutGlobalScopes()->count();

    LeaveTypeCatalogue::sync();
    LeaveTypeCatalogue::sync();

    expect(Leave::withoutGlobalScopes()->count())->toBe($before);
});

it('does not duplicate rows when synced twice with the same timestamp', function () {
    $now = now();

    LeaveTypeCatalogue::sync($now);
    $before = Leave::withoutGlobalScopes()->count();

    LeaveTypeCatalogue::sync($now);

    expect(Leave::withoutGlobalScopes()->count())->toBe($before);
});

it('matches a legacy name that carries an annotation', function () {
    DB::table('leave_types')
        ->whereNull('company_id')
        ->where('code', 'SPL')
        ->update(['name' => 'Special Privilege Leave(MC#6)']);

    $before = Leave::withoutGlobalScopes()->count();

    LeaveTypeCatalogue::sync();

    expect(Leave::withoutGlobalScopes()->count())->toBe($before)
        ->and(Leave::withoutGlobalScopes()->where('code', 'SPL')->count())->toBe(1);
});

it('exposes the system catalogue to every company', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();
    $vacation = Leave::withoutGlobalScopes()->where('name', 'Vacation Leave')->firstOrFail();

    foreach ([$companyA, $companyB] as $company) {
        app(TenantContext::class)->run($company->id, function () use ($vacation): void {
            expect(Leave::find($vacation->id))->not->toBeNull();
        });
    }
});

it('shadows a system row with the company override', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();
    $system = Leave::withoutGlobalScopes()->where('name', 'Vacation Leave')->firstOrFail();

    $override = Leave::overrideFor($companyA->id, $system);
    $override->allow_per_year = 20;
    $override->save();

    app(TenantContext::class)->run($companyA->id, function () use ($override, $system): void {
        $options = Leave::selectOptions();

        expect($options)->toHaveKey($override->id)
            ->not->toHaveKey($system->id);

        expect(Leave::query()->effective()->whereKey($system->id)->exists())->toBeFalse();
    });

    app(TenantContext::class)->run($companyB->id, function () use ($override, $system): void {
        $options = Leave::selectOptions();

        expect($options)->toHaveKey($system->id)
            ->not->toHaveKey($override->id);

        expect(Leave::find($override->id))->toBeNull();
    });
});

it('keeps a custom leave type private to the company that added it', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();

    $custom = new Leave;
    $custom->company_id = $companyA->id;
    $custom->code = 'BDAY';
    $custom->name = 'Birthday Leave';
    $custom->deductable = true;
    $custom->deduct_to = 0;
    $custom->allow_per_year = 1;
    $custom->is_active = true;
    $custom->save();

    app(TenantContext::class)->run($companyA->id, function () use ($custom): void {
        expect(Leave::query()->effective()->whereKey($custom->id)->exists())->toBeTrue();
    });

    app(TenantContext::class)->run($companyB->id, function () use ($custom): void {
        expect(Leave::query()->effective()->whereKey($custom->id)->exists())->toBeFalse();
    });
});

it('only lets a company edit its own rows', function () {
    $company = Company::factory()->create();
    $other = Company::factory()->create();
    $user = User::factory()->forCompany($company)->create(['owner' => true]);

    $this->actingAs($user);

    $system = Leave::withoutGlobalScopes()->where('name', 'Vacation Leave')->firstOrFail();
    $override = Leave::overrideFor($company->id, $system);
    $otherOverride = Leave::overrideFor($other->id, $system);

    expect(LeaveResource::authorizedToUpdate($system))->toBeFalse()
        ->and(LeaveResource::authorizedToUpdate($override))->toBeTrue()
        ->and(LeaveResource::authorizedToUpdate($otherOverride))->toBeFalse();
});

it('creates an override from the admin action', function () {
    $company = Company::factory()->create();
    $user = User::factory()->forCompany($company)->create(['owner' => true]);
    $system = Leave::withoutGlobalScopes()->where('name', 'Vacation Leave')->firstOrFail();

    $request = Request::create('/', 'POST');
    $request->setUserResolver(fn () => $user);

    (new OverrideLeaveType)->handle($request, [], new Collection([$system]));

    expect(Leave::withoutGlobalScopes()
        ->where('company_id', $company->id)
        ->where('name', 'Vacation Leave')
        ->exists())->toBeTrue();
});

it('creates a custom leave type for the company through the admin form', function () {
    $company = Company::factory()->create();
    $user = User::factory()->forCompany($company)->create(['owner' => true]);

    $this->actingAs($user);

    Livewire::test(AdminForm::class, ['resource' => 'leave'])
        ->set('form.name', 'Birthday Leave')
        ->set('form.code', 'BDAY')
        ->set('form.allow_per_year', 1)
        ->call('save')
        ->assertHasNoErrors();

    $leave = Leave::withoutGlobalScopes()->where('name', 'Birthday Leave')->firstOrFail();

    expect((int) $leave->company_id)->toBe($company->id)
        ->and((bool) $leave->deductable)->toBeFalse()
        ->and((int) $leave->deduct_to)->toBe(0)
        ->and((bool) $leave->is_active)->toBeTrue();
});

it('keeps the shared identity fields read-only when editing', function () {
    $company = Company::factory()->create();
    $user = User::factory()->forCompany($company)->create(['owner' => true]);

    $this->actingAs($user);

    $identity = ['name', 'code', 'description'];

    $editing = collect((new LeaveResource)->formFields(Request::create('/'), false));
    $creating = collect((new LeaveResource)->formFields(Request::create('/'), true));

    expect($editing->whereIn('attribute', $identity)->every(fn ($field) => $field->readonly))->toBeTrue()
        ->and($creating->whereIn('attribute', $identity)->every(fn ($field) => ! $field->readonly))->toBeTrue();
});

it('shows the catalogue read-only in the admin panel', function () {
    $company = Company::factory()->create();
    $user = User::factory()->forCompany($company)->create(['owner' => true]);

    $this->actingAs($user);

    $system = Leave::withoutGlobalScopes()->where('name', 'Vacation Leave')->firstOrFail();

    $this->get('/app/resources/leave')
        ->assertOk()
        ->assertSee('Vacation Leave');

    $this->get("/app/resources/leave/{$system->id}/edit")->assertForbidden();

    $override = Leave::overrideFor($company->id, $system);

    $this->get("/app/resources/leave/{$override->id}/edit")
        ->assertOk()
        ->assertSee('Allow per year');
});

it('seeds the default rows plus the configured sector only', function () {
    DB::table('leave_types')->delete();

    LeaveTypeCatalogue::sync(null, Sector::Private);

    $private = DB::table('leave_types')->whereNull('company_id')->pluck('code');

    expect($private)->toContain('SIL')
        ->toContain('VL')
        ->not->toContain('MON');

    DB::table('leave_types')->delete();

    LeaveTypeCatalogue::sync(null, Sector::Government);

    $government = DB::table('leave_types')->whereNull('company_id')->pluck('code');

    expect($government)->toContain('MON')
        ->toContain('VL')
        ->not->toContain('SIL');
});

it('limits the catalogue to the rows shared by both sectors plus the current one', function () {
    $government = Leave::withoutGlobalScopes()->forSector(Sector::Government);
    $private = Leave::withoutGlobalScopes()->forSector(Sector::Private);

    expect($government->where('code', 'MON')->exists())->toBeTrue()
        ->and($government->where('code', 'SIL')->exists())->toBeFalse()
        ->and($private->where('code', 'SIL')->exists())->toBeTrue()
        ->and($private->where('code', 'MON')->exists())->toBeFalse();
});
