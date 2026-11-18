<?php

use App\Enums\Sector;
use App\Livewire\Admin\Resource;
use App\Livewire\Admin\Resources\Employee as EmployeeResource;
use App\Livewire\Admin\Support\Navigation;
use App\Livewire\Admin\Support\ResourceRegistry;
use App\Models\Company;
use App\Models\Compensation;
use App\Models\Leave;
use App\Models\User;
use App\Sectors\SectorManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

class SectorGatedProbeResource extends Resource
{
    public static ?string $sector = 'Government';

    public function fields(Request $request): array
    {
        return [];
    }
}

it('seeds the government catalogues and hides private-only rows', function () {
    $company = Company::factory()->create(['type' => 'Private']);

    $this->artisan('openhr:install', [
        '--sector' => 'Government',
        '--country' => 'PH',
        '--skip-statutory' => true,
    ])->assertExitCode(0);

    $companyId = $company->getKey();

    expect(DB::table('employment_types')->where('company_id', $companyId)->where('code', 'PERM')->exists())->toBeTrue()
        ->and(DB::table('employment_types')->where('company_id', $companyId)->where('code', 'REG')->exists())->toBeFalse()
        ->and(DB::table('leave_types')->whereNull('company_id')->where('code', 'VL')->exists())->toBeTrue()
        ->and(DB::table('leave_types')->whereNull('company_id')->where('code', 'MON')->where('sector', 'Government')->exists())->toBeTrue()
        ->and(Leave::withoutGlobalScopes()->forSector(Sector::Government)->where('code', 'SIL')->exists())->toBeFalse()
        ->and(DB::table('loans')->where('company_id', $companyId)->where('code', 'GSIS-POLICY')->exists())->toBeTrue()
        ->and(DB::table('loans')->where('company_id', $companyId)->where('code', 'SSS-SALARY')->exists())->toBeFalse()
        ->and(DB::table('compensations')->whereNull('company_id')->where('code', 'YEAR-END BONUS')->where('sector', 'Government')->exists())->toBeTrue()
        ->and(Compensation::query()->forSector(Sector::Government)->where('code', '13TH MONTH PAY')->exists())->toBeFalse();
});

it('seeds the private catalogues and hides government-only rows', function () {
    $company = Company::factory()->create(['type' => 'Government']);

    $this->artisan('openhr:install', [
        '--sector' => 'Private',
        '--country' => 'PH',
        '--skip-statutory' => true,
    ])->assertExitCode(0);

    $companyId = $company->getKey();

    expect(DB::table('employment_types')->where('company_id', $companyId)->where('code', 'REG')->exists())->toBeTrue()
        ->and(DB::table('employment_types')->where('company_id', $companyId)->where('code', 'PERM')->exists())->toBeFalse()
        ->and(DB::table('leave_types')->whereNull('company_id')->where('code', 'SIL')->where('sector', 'Private')->exists())->toBeTrue()
        ->and(DB::table('leave_types')->whereNull('company_id')->where('code', 'VL')->exists())->toBeTrue()
        ->and(Leave::withoutGlobalScopes()->forSector(Sector::Private)->where('code', 'MON')->exists())->toBeFalse()
        ->and(DB::table('loans')->where('company_id', $companyId)->where('code', 'SSS-SALARY')->exists())->toBeTrue()
        ->and(DB::table('loans')->where('company_id', $companyId)->where('code', 'GSIS-POLICY')->exists())->toBeFalse()
        ->and(DB::table('compensations')->whereNull('company_id')->where('code', '13TH MONTH PAY')->where('sector', 'Private')->exists())->toBeTrue()
        ->and(Compensation::query()->forSector(Sector::Private)->where('code', 'YEAR-END BONUS')->exists())->toBeFalse();
});

it('filters the admin menu by sector', function () {
    $this->artisan('openhr:install', [
        '--sector' => 'Government',
        '--country' => 'PH',
        '--skip-statutory' => true,
    ])->assertExitCode(0);

    config()->set('admin.menu', [
        ['label' => 'Government only', 'sector' => 'Government', 'items' => []],
        ['label' => 'Private only', 'sector' => 'Private', 'items' => []],
        ['label' => 'Both sectors', 'items' => []],
    ]);

    $labels = Navigation::sections()->pluck('label');

    expect($labels)->toContain('Government only')
        ->toContain('Both sectors')
        ->not->toContain('Private only');
});

it('blocks a resource tagged for the other sector', function () {
    $method = new ReflectionMethod(ResourceRegistry::class, 'sectorAllows');
    $method->setAccessible(true);

    $this->artisan('openhr:install', [
        '--sector' => 'Government',
        '--country' => 'PH',
        '--skip-statutory' => true,
    ])->assertExitCode(0);

    expect($method->invoke(null, SectorGatedProbeResource::class))->toBeTrue();

    $this->artisan('openhr:install', [
        '--sector' => 'Private',
        '--country' => 'PH',
        '--skip-statutory' => true,
        '--force' => true,
    ])->assertExitCode(0);

    app(SectorManager::class)->reset();

    expect($method->invoke(null, SectorGatedProbeResource::class))->toBeFalse();
});

it('shows the sector statutory identifier on the employee form', function () {
    $company = Company::factory()->create(['type' => 'Private']);
    $user = User::factory()->create(['company_id' => $company->id]);

    $this->artisan('openhr:install', [
        '--sector' => 'Government',
        '--country' => 'PH',
        '--skip-statutory' => true,
    ])->assertExitCode(0);

    $this->actingAs($user);

    $attributes = collect((new EmployeeResource)->fields(Request::create('/')))
        ->pluck('attribute')
        ->filter()
        ->values();

    expect($attributes)->toContain('gsis_number')
        ->toContain('salary_grade')
        ->toContain('step')
        ->not->toContain('sss_number');
});

it('renders the sector settings page for the installation sector', function () {
    $company = Company::factory()->create();
    $user = User::factory()->create(['company_id' => $company->id, 'owner' => true]);

    $this->artisan('openhr:install', [
        '--sector' => 'Government',
        '--country' => 'PH',
        '--skip-statutory' => true,
    ])->assertExitCode(0);

    $this->actingAs($user)
        ->get('/app/settings/sector')
        ->assertOk()
        ->assertSee('Sector: Government')
        ->assertSee('GSIS contribution base');
});
