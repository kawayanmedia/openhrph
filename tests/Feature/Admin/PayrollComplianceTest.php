<?php

use App\Models\JournalEntry;
use App\Models\Payroll;
use App\Models\PayrollDetail;
use App\Models\PayrollGroup;
use App\Models\User;
use App\Services\Payroll\PayrollJournalService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

beforeEach(function () {
    $user = User::factory()->create([
        'company_id' => 1,
        'name' => 'Owner',
        'email' => 'owner@example.com',
        'password' => 'secret',
        'owner' => true,
    ]);

    $this->actingAs($user);

    $this->group = app(TenantContext::class)->run(1, fn () => PayrollGroup::factory()->create([
        'company_id' => 1,
        'name' => 'Semi-monthly',
    ]));

    $this->payroll = app(TenantContext::class)->run(1, fn () => Payroll::factory()->create([
        'company_id' => 1,
        'payroll_group_id' => $this->group->id,
        'payroll_date' => '2025-06-15',
        'date_from' => '2025-06-01',
        'date_to' => '2025-06-15',
        'cut_off' => 'first_pay',
        'status' => Payroll::STATUS_PROCESSED,
        'gross_amount' => 3000000,
        'net_amount' => 2500000,
    ]));

    app(TenantContext::class)->run(1, fn () => PayrollDetail::factory()->create([
        'company_id' => 1,
        'payroll_id' => $this->payroll->id,
        'employee_id' => 1,
        // The model accessors take pesos and store centavos.
        'basic_salary' => 15000,
        'regular_pay' => 15000,
        'gross_income' => 30000,
        'taxable_income' => 25000,
        'withholding_tax' => 2000,
        'sss_employee_total' => 1000,
        'sss_employer_total' => 2000,
        'philhealth_employee' => 750,
        'philhealth_employer' => 750,
        'total_philhealth' => 1500,
        'hdmf_employee' => 100,
        'hdmf_employer' => 100,
        'hdmf_total' => 200,
        'loans' => 500,
        'total_employee' => 1850,
        'total_employer' => 2850,
        'total_deductions' => 4350,
        'net_pay' => 25650,
    ]));
});

it('posts a balanced journal entry when a payroll is approved', function () {
    $entry = app(TenantContext::class)->run(1, fn () => app(PayrollJournalService::class)->post($this->payroll));

    expect($entry)->not->toBeNull();

    $debits = (int) $entry->lines()->sum('debit_centavos');
    $credits = (int) $entry->lines()->sum('credit_centavos');

    expect($debits)->toBe($credits)
        ->and($debits)->toBe(3000000 + 285000);

    $accounts = $entry->lines()->pluck('account_code')->all();
    expect($accounts)->toContain('2100', '2110', '2120', '2130', '2140', '2200');
});

it('reverses the journal entry when the payroll is reopened', function () {
    app(TenantContext::class)->run(1, function () {
        app(PayrollJournalService::class)->post($this->payroll);
        app(PayrollJournalService::class)->reverse($this->payroll, 'Correction');
    });

    expect(Schema::hasTable('journal_entries'))->toBeTrue()
        ->and(JournalEntry::count())->toBe(0);
});

it('exports the payroll register and remittance reconciliation', function () {
    $this->artisan('payroll:register', ['payroll' => $this->payroll->id])->assertSuccessful();
    $this->artisan('payroll:remittance', ['payroll' => $this->payroll->id])->assertSuccessful();
});
