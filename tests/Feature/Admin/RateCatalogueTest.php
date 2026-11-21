<?php

use App\Models\Company;
use App\Models\Rate;
use App\Services\Payroll\RateCatalogue;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('seeds the day-type rates when a company is created', function () {
    $company = Company::factory()->create(['name' => 'New Workspace']);

    $codes = Rate::withoutTenant()
        ->where('company_id', $company->id)
        ->orderBy('code')
        ->pluck('code')
        ->all();

    expect($codes)->toHaveCount(12)
        ->and($codes)->toContain('regular', 'rd', 'rh', 'pl', 'upl');
});

it('does not duplicate or overwrite rates when syncing again', function () {
    $company = Company::factory()->create(['name' => 'Existing Workspace']);

    $restDay = Rate::withoutTenant()->where('company_id', $company->id)->where('code', 'rd')->firstOrFail();
    $restDay->regular = 1.35;
    $restDay->save();

    expect(RateCatalogue::sync((int) $company->id))->toBe(0);

    expect(Rate::withoutTenant()->where('company_id', $company->id)->count())->toBe(12)
        ->and((float) Rate::withoutTenant()->where('company_id', $company->id)->where('code', 'rd')->value('regular'))->toBe(1.35);
});

it('labels a timesheet rate even for a company with no rate rows', function () {
    $company = Company::factory()->create(['name' => 'Legacy Workspace']);

    Rate::withoutTenant()->where('company_id', $company->id)->delete();

    app(TenantContext::class)->run($company->id, function (): void {
        expect(hour_rates('regular'))->toHaveKey('ot')
            ->and(hour_rates('missing-code'))->toHaveKey('regular')
            ->and(hour_rates_value('missing-code', 'regular'))->toBe(1.0);
    });
});
