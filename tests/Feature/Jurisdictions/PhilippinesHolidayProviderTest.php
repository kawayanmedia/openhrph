<?php

use App\Jurisdictions\Philippines\Holidays\PhilippinesHolidayProvider;
use App\Models\Company;
use App\Support\Installation\Installation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('bundles the philippine holiday calendar', function () {
    $provider = new PhilippinesHolidayProvider;
    $rows = $provider->calendar(2026);

    expect($rows)->not->toBeEmpty()
        ->and($rows[0])->toHaveKeys(['date', 'name', 'type', 'code'])
        ->and(collect($rows)->pluck('type'))->toContain('regular')
        ->and(collect($rows)->pluck('type'))->toContain('special_non_working');
});

it('imports the active jurisdiction calendar with country scope', function () {
    $company = Company::factory()->create();

    $count = Installation::jurisdiction()->holidays()->import($company->getKey(), 2026);

    expect($count)->toBeGreaterThan(0)
        ->and(DB::table('holidays')->where('company_id', $company->getKey())->where('year', 2026)->count())->toBe($count)
        ->and(DB::table('holidays')->where('company_id', $company->getKey())->where('country_code', 'PH')->exists())->toBeTrue()
        ->and(DB::table('holidays')->where('company_id', $company->getKey())->whereNotNull('type')->exists())->toBeTrue();
});
