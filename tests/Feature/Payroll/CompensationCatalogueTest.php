<?php

use App\Livewire\Admin\Actions\OverrideCompensation;
use App\Livewire\Admin\Resources\Compensation as CompensationResource;
use App\Models\Company;
use App\Models\Compensation;
use App\Models\User;
use App\Support\Payroll\CompensationCatalogue;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('seeds the compensation catalogue as system-wide rows', function () {
    $catalogue = Compensation::withoutGlobalScopes();

    expect($catalogue->whereNull('company_id')->count())
        ->toBe(count(CompensationCatalogue::rows()))
        ->and($catalogue->whereNotNull('company_id')->count())->toBe(0);
});

it('syncs the catalogue without duplicating rows', function () {
    $before = Compensation::withoutGlobalScopes()->count();

    CompensationCatalogue::sync();
    CompensationCatalogue::sync();

    expect(Compensation::withoutGlobalScopes()->count())->toBe($before);
});

it('matches catalogue codes case-insensitively', function () {
    DB::table('compensations')->where('code', 'rice')->update(['code' => 'RICE']);

    $before = Compensation::withoutGlobalScopes()->count();

    CompensationCatalogue::sync();

    expect(Compensation::withoutGlobalScopes()->count())->toBe($before)
        ->and(Compensation::withoutGlobalScopes()->where('code', 'rice')->exists())->toBeTrue();
});

it('does not duplicate rows when synced twice with the same timestamp', function () {
    $now = now();

    CompensationCatalogue::sync($now);
    $before = Compensation::withoutGlobalScopes()->count();

    CompensationCatalogue::sync($now);

    expect(Compensation::withoutGlobalScopes()->count())->toBe($before);
});

it('exposes the system catalogue to every company', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();
    $rice = Compensation::withoutGlobalScopes()->where('code', 'rice')->firstOrFail();

    foreach ([$companyA, $companyB] as $company) {
        app(TenantContext::class)->run($company->id, function () use ($rice): void {
            expect(Compensation::find($rice->id))->not->toBeNull();
        });
    }
});

it('shadows a system row with the company override', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();
    $system = Compensation::withoutGlobalScopes()->where('code', 'rice')->firstOrFail();

    $override = Compensation::overrideFor($companyA->id, $system);
    $override->amount = 1500;
    $override->save();

    app(TenantContext::class)->run($companyA->id, function () use ($override, $system): void {
        $options = Compensation::optionsForGroup(Compensation::GROUP_ALLOWANCE);

        expect($options)->toHaveKey($override->id)
            ->not->toHaveKey($system->id);

        expect(Compensation::query()->effective()->whereKey($system->id)->exists())->toBeFalse();
    });

    app(TenantContext::class)->run($companyB->id, function () use ($override, $system): void {
        $options = Compensation::optionsForGroup(Compensation::GROUP_ALLOWANCE);

        expect($options)->toHaveKey($system->id)
            ->not->toHaveKey($override->id);

        expect(Compensation::find($override->id))->toBeNull();
    });
});

it('keeps the thirteenth month exclusion at ninety thousand pesos', function () {
    $thirteenthMonth = Compensation::withoutGlobalScopes()
        ->where('code', '13TH MONTH PAY')
        ->firstOrFail();

    expect($thirteenthMonth->getRawOriginal('maximum_non_tax'))->toBe(9000000)
        ->and($thirteenthMonth->maximum_non_tax)->toBe(90000);
});

it('only lets a company edit its own override', function () {
    $company = Company::factory()->create();
    $other = Company::factory()->create();
    $user = User::factory()->forCompany($company)->create(['owner' => true]);

    $this->actingAs($user);

    $system = Compensation::withoutGlobalScopes()->where('code', 'rice')->firstOrFail();
    $override = Compensation::overrideFor($company->id, $system);
    $otherOverride = Compensation::overrideFor($other->id, $system);

    expect(CompensationResource::authorizedToCreate())->toBeFalse()
        ->and(CompensationResource::authorizedToUpdate($system))->toBeFalse()
        ->and(CompensationResource::authorizedToUpdate($override))->toBeTrue()
        ->and(CompensationResource::authorizedToUpdate($otherOverride))->toBeFalse();
});

it('creates an override from the admin action', function () {
    $company = Company::factory()->create();
    $user = User::factory()->forCompany($company)->create(['owner' => true]);
    $system = Compensation::withoutGlobalScopes()->where('code', 'rice')->firstOrFail();

    $request = Request::create('/', 'POST');
    $request->setUserResolver(fn () => $user);

    (new OverrideCompensation)->handle($request, [], new Collection([$system]));

    expect(Compensation::withoutGlobalScopes()
        ->where('company_id', $company->id)
        ->where('code', 'rice')
        ->exists())->toBeTrue();
});

it('refreshes the classification of overrides without touching their values', function () {
    $company = Company::factory()->create();
    $system = Compensation::withoutGlobalScopes()->where('code', 'rice')->firstOrFail();
    $override = Compensation::overrideFor($company->id, $system);

    $override->amount = 1500;
    $override->save();

    DB::table('compensations')->where('id', $override->id)->update([
        'name' => 'Stale name',
        'tax_class' => 'Stale class',
    ]);

    CompensationCatalogue::sync();

    $refreshed = Compensation::withoutGlobalScopes()->findOrFail($override->id);

    expect($refreshed->name)->toBe($system->name)
        ->and((int) $refreshed->getRawOriginal('amount'))->toBe(150000)
        ->and($refreshed->getRawOriginal('maximum_non_tax'))->toBe($system->getRawOriginal('maximum_non_tax'));
});

it('shows the catalogue read-only in the admin panel', function () {
    $company = Company::factory()->create();
    $user = User::factory()->forCompany($company)->create(['owner' => true]);

    $this->actingAs($user);

    $system = Compensation::withoutGlobalScopes()->where('code', 'rice')->firstOrFail();

    $this->get('/app/resources/compensation')
        ->assertOk()
        ->assertSee('BASIC PAY ADJUSTMENT');

    $this->get('/app/resources/compensation/new')->assertForbidden();
    $this->get("/app/resources/compensation/{$system->id}/edit")->assertForbidden();

    $override = Compensation::overrideFor($company->id, $system);

    $this->get("/app/resources/compensation/{$override->id}/edit")
        ->assertOk()
        ->assertSee('Maximum non-taxable amount');
});

it('publishes the annual de minimis ceiling on each capped allowance', function (string $code, int $centavos) {
    $row = Compensation::withoutGlobalScopes()->where('code', $code)->firstOrFail();

    expect((int) $row->getRawOriginal('maximum_non_tax'))->toBe($centavos);
})->with([
    'rice' => ['rice', 3000000],
    'uniform' => ['clothing', 800000],
    'medical cash allowance' => ['medical', 400000],
    'laundry' => ['laundry', 480000],
    'medical assistance' => ['medical_assistance', 1200000],
    'achievement award' => ['achievement_award', 1200000],
    'gifts' => ['gifts', 600000],
    'cba productivity' => ['cba_productivity', 1200000],
    'pera' => ['PERA', 2400000],
]);

it('keeps a null ceiling on benefits whose cap is not a fixed peso amount', function (string $code) {
    $row = Compensation::withoutGlobalScopes()->where('code', $code)->firstOrFail();

    expect($row->getRawOriginal('maximum_non_tax'))->toBeNull();
})->with([
    'overtime meal allowance' => ['meal'],
    'monetised leave' => ['MONETIZED LEAVE'],
    'subsistence allowance' => ['SUBSISTENCE ALLOWANCE'],
]);
