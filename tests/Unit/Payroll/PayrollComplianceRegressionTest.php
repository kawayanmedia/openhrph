<?php

use App\Jurisdictions\Philippines\Statutory\Bir\TaxPeriod;
use App\Jurisdictions\Philippines\Statutory\Bir\WithholdingTaxTable;
use App\Jurisdictions\Philippines\Statutory\Sss\SssSchedule;
use App\Services\Payroll\Computation\BenefitFlags;
use App\Services\Payroll\Computation\Computer;
use App\Services\Payroll\Computation\Config;
use App\Services\Payroll\Computation\Earning;
use App\Services\Payroll\Computation\Employee;
use App\Services\Payroll\Computation\Holiday;
use App\Services\Payroll\Computation\TimesheetLine;
use App\Services\Payroll\Computation\YtdTotals;

/**
 * Regressions found by the 27 November 2026 compliance audit.
 *
 * Each expectation is derived from the statute, not from the engine: Labor
 * Code Arts. 94 and 116, RA 9504 (minimum wage earners), RA 10963 / NIRC
 * Sec. 32(B)(7)(e) (the ₱90,000 benefit exclusion), and RA 11199, RA 11223 and
 * RA 9679 (contribution bases).
 */
function complianceConfig(array $overrides = []): Config
{
    return new Config(...array_merge([
        'frequency' => Config::FREQUENCY_SEMI_MONTHLY,
        'cutoff' => 'first_pay',
        'sssSchedule' => 'every_pay',
        'philHealthSchedule' => 'every_pay',
        'pagIbigSchedule' => 'every_pay',
        'taxSchedule' => 'every_pay',
        'sssBrackets' => SssSchedule::preset2025(),
        'taxBrackets' => WithholdingTaxTable::trainForPeriod(TaxPeriod::SEMI_MONTHLY),
        'annualTaxBrackets' => WithholdingTaxTable::trainForPeriod(TaxPeriod::ANNUAL),
        'daysPerYear' => 261,
        'hoursPerDay' => 8,
        'payrollDate' => '2026-11-15',
    ], $overrides));
}

function complianceBenefits(): BenefitFlags
{
    return new BenefitFlags(
        restDayPay: true,
        regularHolidayPay: true,
        specialHolidayPay: true,
        premiumHolidayPay: true,
        regularHolidaySpecialHolidayPay: true,
        overtimePay: true,
        nightDifferential: true,
        deMinimis: true,
    );
}

/**
 * @return list<TimesheetLine>
 */
function complianceDays(int $count, string $from = '2026-11-03'): array
{
    $lines = [];
    $cursor = new DateTimeImmutable($from);

    while (count($lines) < $count) {
        $cursor = $cursor->modify('+1 day');

        if ((int) $cursor->format('N') === 7) {
            continue;
        }

        $lines[] = new TimesheetLine(date: $cursor->format('Y-m-d'), dayType: 'regular', hours: 8);
    }

    return $lines;
}

it('pays one unworked regular holiday when the calendar has duplicate rows', function (): void {
    $employee = new Employee(
        id: 1,
        monthlyBasic: 0,
        dailyRate: 120_000, // ₱1,200.00
        paymentType: Employee::PAYMENT_DAILY,
        benefits: complianceBenefits(),
        timesheets: [new TimesheetLine(date: '2026-11-27', dayType: 'regular', hours: 8)],
    );

    $holiday = new Holiday(date: '2026-11-30', code: 'rh');

    $oneRow = Computer::compute(complianceConfig([
        'payUnworkedRegularHolidays' => true,
        'holidays' => [$holiday],
    ]), $employee);

    $fourRows = Computer::compute(complianceConfig([
        'payUnworkedRegularHolidays' => true,
        'holidays' => [$holiday, $holiday, $holiday, $holiday],
    ]), $employee);

    // Art. 94: one day's pay for the unworked regular holiday, whatever the
    // number of reference rows.
    expect($oneRow->holidayPay)->toBe(120_000)
        ->and($fourRows->holidayPay)->toBe(120_000)
        ->and($fourRows->gross)->toBe($oneRow->gross);
});

it('assesses a daily-paid employee on the monthly equivalent of the daily rate', function (): void {
    $employee = new Employee(
        id: 32,
        monthlyBasic: 700_000,   // ₱7,000 stored in the basic-salary column
        dailyRate: 700_000,      // ₱7,000.00 a day
        paymentType: Employee::PAYMENT_DAILY,
        benefits: complianceBenefits(),
        timesheets: complianceDays(10),
    );

    $slip = Computer::compute(complianceConfig(), $employee);

    // ₱7,000 x 261 / 12 = ₱152,250 a month: the maximum salary credit and a
    // premium at the PhilHealth ceiling.
    expect($slip->sssEmployee)->toBe(50_000)        // 5% of ₱20,000
        ->and($slip->sssProvident)->toBe(37_500)    // 5% of the ₱15,000 MPF
        ->and($slip->philHealthEmployee)->toBe(125_000) // 2.5% of ₱100,000
        ->and($slip->pagIbigEmployee)->toBe(10_000);    // 2% of ₱10,000
});

it('shares one 90,000 exclusion across every benefit paid in the year', function (): void {
    $employee = new Employee(
        id: 2,
        monthlyBasic: 10_000_000, // ₱100,000.00
        paymentType: Employee::PAYMENT_MONTHLY,
        benefits: complianceBenefits(),
        timesheets: complianceDays(9),
        bonuses: [
            new Earning(code: 'YEAR-END BONUS', name: 'Year-End Bonus', amount: 6_000_000, taxable: false, nonTaxableCeiling: 9_000_000),
            new Earning(code: 'CASH GIFT', name: 'Cash Gift', amount: 6_000_000, taxable: false, nonTaxableCeiling: 9_000_000),
        ],
    );

    $slip = Computer::compute(complianceConfig(), $employee);

    // 120,000 of benefits less the single 90,000 exclusion leaves 30,000
    // taxable.
    expect($slip->benefitExempt)->toBe(9_000_000)
        ->and($slip->taxableIncome)->toBe($slip->gross - 9_000_000 - $slip->sssEmployee - $slip->sssProvident - $slip->philHealthEmployee - $slip->pagIbigEmployee);
});

it('withholds the 13th-month excess at the employee marginal rate', function (): void {
    $employee = new Employee(
        id: 3,
        monthlyBasic: 5_000_000, // ₱50,000.00
        paymentType: Employee::PAYMENT_MONTHLY,
        benefits: complianceBenefits(),
        ytd: new YtdTotals(
            basicEarned: 60_000_000,      // ₱600,000.00 earned
            otherBenefits: 40_000_000,    // ₱400,000.00 already exempted
            taxableIncome: 57_400_000,    // ₱574,000.00 regular taxable
        ),
    );

    $slip = Computer::compute(complianceConfig(['runType' => Config::RUN_THIRTEENTH_MONTH]), $employee);

    // ₱600,000 / 12 = ₱50,000, all of it excess after ₱400,000 of benefits.
    // Annual taxable ₱574,000 and ₱624,000 both sit in the 20% band, so the
    // incremental withholding is 20% x 50,000 = 10,000.
    expect($slip->thirteenthMonth)->toBe(5_000_000)
        ->and($slip->taxableIncome)->toBe(5_000_000)
        ->and($slip->withholdingTax)->toBe(1_000_000);
});

it('exempts the hazard pay of a minimum wage earner', function (): void {
    $employee = new Employee(
        id: 4,
        monthlyBasic: 1_642_125, // ₱755.00 x 261 / 12
        dailyRate: 75_500,
        paymentType: Employee::PAYMENT_DAILY,
        isMinimumWage: true,
        minimumWage: 75_500,
        benefits: complianceBenefits(),
        timesheets: complianceDays(12),
        compensations: [
            new Earning(code: 'HAZARD PAY', name: 'Hazard Pay', amount: 1_500_000, taxable: true),
        ],
    );

    $slip = Computer::compute(complianceConfig(), $employee);

    // RA 9504 exempts the statutory wage (₱755 x 12 days = ₱9,060.00) plus
    // holiday, overtime, night and hazard pay. Without the hazard exemption
    // ₱14,282.71 would stay taxable and ₱579.86 would be withheld.
    expect($slip->minimumWageExemption)->toBe(906_000 + 1_500_000)
        ->and($slip->withholdingTax)->toBe(0)
        ->and($slip->netPay)->toBeGreaterThan(0);
});
