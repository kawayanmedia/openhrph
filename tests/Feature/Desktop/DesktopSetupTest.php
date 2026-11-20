<?php

use App\Livewire\Desktop\Setup;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Office;
use App\Models\Payroll;
use App\Models\PayrollGroup;
use App\Models\Schedule;
use App\Models\Setting;
use App\Models\Timesheet;
use App\Models\User;
use App\Services\Demo\DemoWorkspaceSeeder;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['nativephp-internal.running' => true]);

    $this->withoutVite();
});

it('is not reachable outside the desktop runtime', function () {
    config(['nativephp-internal.running' => false]);

    $this->get('/setup')->assertNotFound();
});

it('shows the setup wizard on a fresh desktop install', function () {
    $this->get('/setup')
        ->assertOk()
        ->assertSee('Set up OpenHR PH');
});

it('sends an existing owner to the login page', function () {
    User::factory()->create();

    $this->get('/setup')->assertRedirect(route('admin.login'));
});

it('creates the installation, company and owner account', function () {
    Livewire::test(Setup::class)
        ->set('sector', 'Private')
        ->set('country', 'PH')
        ->set('companyName', 'Desktop Company')
        ->set('name', 'Desktop Owner')
        ->set('email', 'owner@desktop.test')
        ->set('password', 'secret-password')
        ->set('password_confirmation', 'secret-password')
        ->call('install')
        ->assertHasNoErrors()
        ->assertRedirect(route('admin.dashboard'));

    $owner = User::query()->where('email', 'owner@desktop.test')->first();

    expect($owner)->not->toBeNull()
        ->and($owner->owner)->toBeTruthy()
        ->and($owner->company_id)->not->toBeNull()
        ->and(Company::query()->whereKey($owner->company_id)->value('name'))->toBe('Desktop Company')
        ->and($this->app['auth']->guard('web')->check())->toBeTrue();

    $this->assertDatabaseHas('installation_settings', [
        'key' => 'sector',
        'value' => 'Private',
    ]);

    $this->assertDatabaseHas('installation_settings', [
        'key' => 'country_code',
        'value' => 'PH',
    ]);
});

it('fills the new company with the demo workspace', function () {
    Livewire::test(Setup::class)
        ->set('sector', 'Private')
        ->set('country', 'PH')
        ->set('companyName', 'Desktop Company')
        ->set('name', 'Desktop Owner')
        ->set('email', 'owner@desktop.test')
        ->set('password', 'secret-password')
        ->set('password_confirmation', 'secret-password')
        ->call('install')
        ->assertHasNoErrors();

    $owner = User::query()->where('email', 'owner@desktop.test')->firstOrFail();
    $company = Company::query()->findOrFail($owner->company_id);

    app(TenantContext::class)->run($company, function (): void {
        expect(Office::query()->where('name', 'Head Office')->exists())->toBeTrue()
            ->and(Department::query()->where('name', 'Human Resources')->exists())->toBeTrue()
            ->and(Schedule::query()->where('name', 'Default 8am to 5pm')->exists())->toBeTrue()
            ->and(PayrollGroup::query()->where('name', 'Default Kinsenas Katapusan')->exists())->toBeTrue()
            ->and(Employee::query()->whereIn('employee_number', DemoWorkspaceSeeder::SAMPLE_EMPLOYEE_NUMBERS)->count())->toBe(3)
            ->and(Timesheet::query()->count())->toBeGreaterThan(0)
            ->and(Payroll::query()->where('status', Payroll::STATUS_OPEN)->count())->toBe(1)
            ->and(Setting::query()->where('key', 'company_name')->value('value'))->toBe('Desktop Company');
    });
});
