<?php

use App\Enums\Sector;
use App\Livewire\Admin\Settings\Settings;
use App\Models\Company;
use App\Sectors\SectorManager;
use App\Support\Installation\Installation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('normalizes sector aliases and maps non-profit companies to private', function () {
    expect(Sector::tryFromValue('government'))->toBe(Sector::Government)
        ->and(Sector::tryFromValue('gov'))->toBe(Sector::Government)
        ->and(Sector::tryFromValue('public'))->toBe(Sector::Government)
        ->and(Sector::tryFromValue('private'))->toBe(Sector::Private)
        ->and(Sector::tryFromValue('Non-Profit'))->toBe(Sector::Private)
        ->and(Sector::tryFromValue('unknown'))->toBeNull()
        ->and(Sector::fromValue('unknown'))->toBe(Sector::Private);
});

it('resolves the installation sector and its capabilities', function () {
    $this->artisan('openhr:install', [
        '--sector' => 'Government',
        '--country' => 'PH',
        '--skip-statutory' => true,
    ])->assertExitCode(0);

    $manager = app(SectorManager::class);

    expect(Installation::sectorEnum())->toBe(Sector::Government)
        ->and($manager->current()->sector())->toBe(Sector::Government)
        ->and($manager->supports('plantilla'))->toBeTrue()
        ->and($manager->supports('thirteenth_month'))->toBeFalse()
        ->and($manager->showsMenu('plantilla'))->toBeTrue()
        ->and($manager->showsMenu('thirteenth-month'))->toBeFalse();
});

it('resolves a company sector independently of the installation default', function () {
    $this->artisan('openhr:install', [
        '--sector' => 'Private',
        '--country' => 'PH',
        '--skip-statutory' => true,
    ])->assertExitCode(0);

    $company = Company::factory()->create();
    DB::table('companies')
        ->where('id', $company->getKey())
        ->update(['type' => 'Government']);

    $manager = app(SectorManager::class);

    expect($manager->forCompany($company->fresh())->sector())->toBe(Sector::Government)
        ->and($manager->current()->sector())->toBe(Sector::Private);
});

it('keeps both sector profiles structurally aligned', function () {
    $government = Sector::Government;
    $private = Sector::Private;

    $governmentKeys = array_keys(config('sectors.profiles.'.$government->value));
    $privateKeys = array_keys(config('sectors.profiles.'.$private->value));

    sort($governmentKeys);
    sort($privateKeys);

    expect($governmentKeys)->toBe($privateKeys);
});

it('exposes the sector settings page', function () {
    $this->artisan('openhr:install', [
        '--sector' => 'Government',
        '--country' => 'PH',
        '--skip-statutory' => true,
    ])->assertExitCode(0);

    expect(Settings::pages())->toHaveKey('sector');
});
