<?php

use App\Models\Company;
use App\Models\Payroll;
use App\Models\PayrollGroup;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function specialRunFixture(string $type): PayrollGroup
{
    return app(TenantContext::class)->run(1, function () use ($type): PayrollGroup {
        Company::factory()->create(['id' => 1, 'type' => $type]);

        return PayrollGroup::factory()->create(['id' => 1, 'company_id' => 1]);
    });
}

it('refuses a thirteenth-month run for a government agency', function (): void {
    $group = specialRunFixture('Government');

    $this->artisan('payroll:special', ['group' => $group->getKey(), 'type' => 'thirteenth_month'])
        ->expectsOutputToContain('does not apply to this sector')
        ->assertExitCode(1);

    // PD 851 covers rank-and-file private employees; government personnel
    // receive the mid-year and year-end bonus instead.
    expect(Payroll::withoutTenant()->count())->toBe(0);
});

it('creates a thirteenth-month run for a private employer', function (): void {
    $group = specialRunFixture('Private');

    $this->artisan('payroll:special', ['group' => $group->getKey(), 'type' => 'thirteenth_month'])
        ->assertExitCode(0);

    expect(Payroll::withoutTenant()->where('type', Payroll::TYPE_THIRTEENTH_MONTH)->count())->toBe(1);
});

it('still allows final pay for a government agency', function (): void {
    $group = specialRunFixture('Government');

    $this->artisan('payroll:special', [
        'group' => $group->getKey(),
        'type' => 'final_pay',
        '--separation-reason' => 'retirement',
    ])->assertExitCode(0);

    expect(Payroll::withoutTenant()->where('type', Payroll::TYPE_FINAL_PAY)->count())->toBe(1);
});
