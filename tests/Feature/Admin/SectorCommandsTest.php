<?php

use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function installSector(string $sector): void
{
    test()->artisan('openhr:install', [
        '--sector' => $sector,
        '--country' => 'PH',
        '--skip-statutory' => true,
    ])->assertExitCode(0);
}

it('re-pins company types to the installation sector', function () {
    installSector('Government');

    $company = Company::factory()->create();
    DB::table('companies')->where('id', $company->id)->update(['type' => 'Private']);

    $this->artisan('openhr:sector:repair')->assertExitCode(0);
    expect($company->fresh()->type)->toBe('Private');

    $this->artisan('openhr:sector:repair', ['--apply' => true])->assertExitCode(0);
    expect($company->fresh()->type)->toBe('Government');
});

it('deactivates foreign-sector catalogue rows only with apply', function () {
    installSector('Private');

    $company = Company::factory()->create();
    $row = [
        'company_id' => $company->id,
        'code' => 'GOV-TEST',
        'name' => 'Government test allowance',
        'sector' => 'Government',
        'status' => 'Active',
        'created_at' => now(),
        'updated_at' => now(),
    ];

    DB::table('compensations')->insert($row);

    $this->artisan('openhr:sector:prune')->assertExitCode(0);
    expect(DB::table('compensations')->where('code', 'GOV-TEST')->value('status'))->toBe('Active');

    $this->artisan('openhr:sector:prune', ['--apply' => true])->assertExitCode(0);
    expect(DB::table('compensations')->where('code', 'GOV-TEST')->value('status'))->toBe('Inactive');
});

it('fails a strict audit when foreign-sector rows exist', function () {
    installSector('Private');

    $company = Company::factory()->create();
    DB::table('compensations')->insert([
        'company_id' => $company->id,
        'code' => 'GOV-TEST',
        'name' => 'Government test allowance',
        'sector' => 'Government',
        'status' => 'Active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->artisan('openhr:sector:audit', ['--strict' => true])->assertExitCode(1);
});
