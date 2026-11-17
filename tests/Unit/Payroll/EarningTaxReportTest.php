<?php

use App\Models\PayrollDetail;
use App\Services\Payroll\Computation\BenefitFlags;
use App\Services\Payroll\Computation\Config;
use App\Services\Payroll\Computation\DeMinimisBenefit;
use App\Services\Payroll\Computation\Earning;
use App\Services\Payroll\Computation\Employee;
use App\Services\Payroll\EarningTaxReport;

function taxReportConfig(): Config
{
    return new Config(
        frequency: Config::FREQUENCY_SEMI_MONTHLY,
        daysPerYear: 261,
        hoursPerDay: 8,
        deMinimis: [new DeMinimisBenefit('rice', 250000, DeMinimisBenefit::BASIS_MONTHLY)],
        payrollDate: '2026-03-15',
    );
}

function taxReportEmployee(): Employee
{
    return new Employee(
        id: 1,
        monthlyBasic: 3000000,
        dailyRate: 114943,
        benefits: new BenefitFlags(deMinimis: true),
        allowances: [Earning::allowance('rice', 'Rice Subsidy', 300000, taxable: false)],
        compensations: [new Earning(
            code: 'NON TAXABLE COMPENSATION',
            name: 'Non-taxable compensation',
            kind: Earning::KIND_COMPENSATION,
            amount: 200000,
            taxable: false,
        )],
    );
}

function taxReportDetail(array $overrides = []): PayrollDetail
{
    $detail = new PayrollDetail;
    $detail->setRawAttributes(array_merge([
        'company_id' => 1,
        'payroll_id' => 1,
        'employee_id' => 1,
        'basic_salary' => 1500000,
        'regular_pay' => 1500000,
        'overtime_pay' => 50000,
        'night_pay' => 0,
        'rest_day_pay' => 0,
        'holiday_pay' => 0,
        'allowance' => 300000,
        'incentive' => 200000,
        'bonus' => 0,
        'commission' => 0,
        'thirteenth_month' => 0,
        'leave_conversion' => 0,
        'separation_pay' => 0,
        'gross_income' => 2050000,
        'non_taxable_income' => 325000,
        'benefit_exempt' => 0,
        'minimum_wage_exemption' => 0,
        'taxable_income' => 1725000,
        'compensations' => json_encode([]),
    ], $overrides), true);

    return $detail;
}

function taxReportRow(array $rows, string $label): array
{
    foreach ($rows as $row) {
        if ($row['label'] === $label) {
            return $row;
        }
    }

    throw new RuntimeException("Row [{$label}] not found.");
}

it('reports each earning line with its tax treatment and the amount above the ceiling', function () {
    $rows = EarningTaxReport::rows(taxReportConfig(), taxReportEmployee(), taxReportDetail());

    $rice = taxReportRow($rows, 'Rice Subsidy');

    expect($rice['amount'])->toBe(3000.0)
        ->and($rice['taxable'])->toBeTrue()
        ->and($rice['exempt'])->toBe(2500.0)
        ->and($rice['ceiling'])->toBe(2500.0)
        ->and($rice['basis'])->toBe(DeMinimisBenefit::BASIS_MONTHLY)
        ->and($rice['excess'])->toBe(500.0);

    $basic = taxReportRow($rows, 'Basic pay (period)');

    expect($basic['taxable'])->toBeTrue()
        ->and($basic['excess'])->toBe(0.0);

    expect(taxReportRow($rows, 'Overtime')['amount'])->toBe(500.0);

    $compensation = taxReportRow($rows, 'Non-taxable compensation');

    expect($compensation['taxable'])->toBeFalse()
        ->and($compensation['exempt'])->toBe(2000.0)
        ->and($compensation['ceiling'])->toBeNull()
        ->and($compensation['excess'])->toBe(0.0);
});

it('skips zero-value earnings', function () {
    $rows = EarningTaxReport::rows(taxReportConfig(), taxReportEmployee(), taxReportDetail());

    expect(collect($rows)->pluck('label'))
        ->not->toContain('Attendance and premiums')
        ->not->toContain('Night differential');
});

it('falls back to the persisted compensation lines when the run input is unavailable', function () {
    $detail = taxReportDetail([
        'compensations' => json_encode([
            ['code' => 'rice', 'name' => 'Rice Subsidy', 'kind' => 'compensation', 'amount' => 300000, 'taxable' => false, 'non_taxable_ceiling' => 250000],
        ]),
    ]);

    $rows = EarningTaxReport::persistedRows($detail);
    $rice = taxReportRow($rows, 'Rice Subsidy');

    expect($rice['exempt'])->toBe(2500.0)
        ->and($rice['ceiling'])->toBe(2500.0)
        ->and($rice['excess'])->toBe(500.0)
        ->and(taxReportRow($rows, 'Allowances (total)')['taxable'])->toBeNull();
});
