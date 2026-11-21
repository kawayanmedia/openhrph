<?php

use App\Services\Payroll\Computation\BenefitFlags;
use App\Services\Payroll\Computation\Computer;
use App\Services\Payroll\Computation\Config;
use App\Services\Payroll\Computation\Employee;
use App\Services\Payroll\Computation\Holiday;
use App\Services\Payroll\Computation\Payslip;
use App\Services\Payroll\Computation\Rules;
use App\Services\Payroll\Computation\TimesheetLine;

/**
 * Day-type payroll proof.
 *
 * A monthly basic of ₱26,100.00 over 261 working days gives a clean
 * ₱1,200.00 daily rate and a ₱150.00 hourly rate, so every premium can be
 * checked by hand against the DOLE Handbook on Workers' Statutory Monetary
 * Benefits premium table.
 */
function timesheetPayrollConfig(array $overrides = []): Config
{
    // first_pay schedules with a last_pay cut-off put every statutory
    // deduction on the other half of the month, isolating earnings.
    return Config::fromArray(array_merge([
        'frequency' => 'semi-monthly',
        'cutoff' => 'last_pay',
        'days_per_year' => 261,
        'hours_per_day' => 8,
        'sss_schedule' => 'first_pay',
        'philhealth_schedule' => 'first_pay',
        'pagibig_schedule' => 'first_pay',
        'tax_schedule' => 'first_pay',
        'payroll_date' => '2026-11-15',
    ], $overrides));
}

function timesheetPayrollBenefits(): BenefitFlags
{
    return new BenefitFlags(
        restDayPay: true,
        regularHolidayPay: true,
        specialHolidayPay: true,
        premiumHolidayPay: true,
        regularHolidaySpecialHolidayPay: true,
        overtimePay: true,
        nightDifferential: true,
    );
}

/**
 * @param  list<TimesheetLine>  $lines
 */
function timesheetPayrollEmployee(
    array $lines,
    string $paymentType = Employee::PAYMENT_DAILY,
    ?BenefitFlags $benefits = null,
): Employee {
    return new Employee(
        id: 1,
        number: 'E-0001',
        name: 'TEST EMPLOYEE',
        monthlyBasic: 2_610_000, // ₱26,100.00
        dailyRate: 120_000,      // ₱1,200.00
        paymentType: $paymentType,
        benefits: $benefits ?? timesheetPayrollBenefits(),
        timesheets: $lines,
    );
}

function timesheetPayrollRun(Employee $employee, ?Config $config = null): Payslip
{
    return Computer::computeRegular($config ?? timesheetPayrollConfig(), $employee);
}

function timesheetPayrollLine(
    string $dayType,
    float $hours = 8.0,
    float $overtime = 0.0,
    float $night = 0.0,
    float $nightOT = 0.0,
    bool $absent = false,
    bool $paidLeave = false,
    string $date = '2026-11-02',
    float $leaveFraction = 1.0,
    bool $leave = false,
): TimesheetLine {
    return new TimesheetLine(
        date: $date,
        dayType: $dayType,
        hours: $hours,
        overtime: $overtime,
        night: $night,
        nightOT: $nightOT,
        absent: $absent,
        leave: $leave,
        paidLeave: $paidLeave,
        leaveFraction: $leaveFraction,
    );
}

dataset('stored timesheet day codes', [
    'regular day' => ['regular', 1.00],
    'rest day' => ['rd', 1.30],
    'special (non-working) day' => ['sh', 1.30],
    'special day on a rest day' => ['rd-sh', 1.50],
    'regular holiday' => ['rh', 2.00],
    'regular holiday on a rest day' => ['rd-rh', 2.60],
    'double regular holiday' => ['2rh', 3.00],
    'double regular holiday on a rest day' => ['rd-2rh', 3.90],
    'regular + special holiday' => ['rh-sh', 2.60],
    'regular + special holiday on a rest day' => ['rd-rh-sh', 3.00],
]);

dataset('daily-paid eight-hour day', [
    // day type, multiplier, regular pay, holiday premium, rest-day premium
    'regular day' => ['regular', 1.00, 120_000, 0, 0],
    'rest day' => ['rd', 1.30, 156_000, 0, 36_000],
    'special day' => ['sh', 1.30, 156_000, 36_000, 0],
    'special day on rest day' => ['rd-sh', 1.50, 180_000, 60_000, 0],
    'regular holiday' => ['rh', 2.00, 240_000, 120_000, 0],
    'regular holiday on rest day' => ['rd-rh', 2.60, 312_000, 192_000, 0],
    'double regular holiday' => ['2rh', 3.00, 360_000, 240_000, 0],
    'double regular holiday on rest day' => ['rd-2rh', 3.90, 468_000, 348_000, 0],
    'regular + special holiday' => ['rh-sh', 2.60, 312_000, 192_000, 0],
    'regular + special holiday on rest day' => ['rd-rh-sh', 3.00, 360_000, 240_000, 0],
]);

dataset('monthly-paid premium add-on', [
    // day type, premium-only pay on top of the ₱13,050.00 period basic
    'regular day' => ['regular', 0, 0],
    'rest day' => ['rd', 36_000, 0],
    'special day' => ['sh', 36_000, 36_000],
    'special day on rest day' => ['rd-sh', 60_000, 60_000],
    'regular holiday' => ['rh', 120_000, 120_000],
    'regular holiday on rest day' => ['rd-rh', 192_000, 192_000],
    'double regular holiday' => ['2rh', 240_000, 240_000],
    'double regular holiday on rest day' => ['rd-2rh', 348_000, 348_000],
    'regular + special holiday' => ['rh-sh', 192_000, 192_000],
    'regular + special holiday on rest day' => ['rd-rh-sh', 240_000, 240_000],
]);

dataset('overtime and night differential on premium days', [
    // day type, regular, overtime, night, night OT, gross
    // Article 87, par. 2: premium-day OT is the day rate plus 30%.
    'regular day' => ['regular', 120_000, 37_500, 3_000, 3_750, 164_250],
    'rest day' => ['rd', 156_000, 50_700, 3_900, 5_070, 215_670],
    'special day' => ['sh', 156_000, 50_700, 3_900, 5_070, 215_670],
    'special day on rest day' => ['rd-sh', 180_000, 58_500, 4_500, 5_850, 248_850],
    'regular holiday' => ['rh', 240_000, 78_000, 6_000, 7_800, 331_800],
    'regular holiday on rest day' => ['rd-rh', 312_000, 101_400, 7_800, 10_140, 431_340],
    'double regular holiday' => ['2rh', 360_000, 117_000, 9_000, 11_700, 497_700],
    'double regular holiday on rest day' => ['rd-2rh', 468_000, 152_100, 11_700, 15_210, 647_010],
    'regular + special holiday' => ['rh-sh', 312_000, 101_400, 7_800, 10_140, 431_340],
    'regular + special holiday on rest day' => ['rd-rh-sh', 360_000, 117_000, 9_000, 11_700, 497_700],
]);

it('maps every stored day code to its statutory multiplier', function (string $code, float $multiplier) {
    expect(Rules::dayMultiplier($code))->toBe($multiplier)
        ->and(Rules::dayMultiplier(Rules::normalize($code)))->toBe($multiplier);
})->with('stored timesheet day codes');

it('treats unknown or blank day values as an ordinary day', function (?string $dayType) {
    expect(Rules::dayMultiplier($dayType))->toBe(1.00);
})->with([
    'blank' => [''],
    'null' => [null],
    'unknown' => ['training'],
]);

it('pays a full eight-hour day at every premium for a daily-paid employee', function (
    string $dayType,
    float $multiplier,
    int $regularPay,
    int $holidayPay,
    int $restDayPay,
) {
    $slip = timesheetPayrollRun(timesheetPayrollEmployee([
        timesheetPayrollLine($dayType),
    ]));

    expect($slip->regularPay)->toBe($regularPay)
        ->and($slip->holidayPay)->toBe($holidayPay)
        ->and($slip->restDayPay)->toBe($restDayPay)
        ->and($slip->regularHours)->toBe(8.0)
        ->and($slip->basicEarned)->toBe(120_000)
        ->and($slip->gross)->toBe($regularPay)
        ->and($slip->dailyRate)->toBe(120_000);
})->with('daily-paid eight-hour day');

it('adds only the premium on top of the fixed basic for a monthly-paid employee', function (
    string $dayType,
    int $premium,
    int $holidayPay,
) {
    $slip = timesheetPayrollRun(timesheetPayrollEmployee(
        [timesheetPayrollLine($dayType)],
        Employee::PAYMENT_MONTHLY,
    ));

    expect($slip->periodBasic)->toBe(1_305_000)      // ₱13,050.00 fixed half-month
        ->and($slip->regularPay)->toBe(1_305_000 + $premium)
        ->and($slip->holidayPay)->toBe($holidayPay)
        ->and($slip->basicEarned)->toBe(1_305_000)
        ->and($slip->gross)->toBe(1_305_000 + $premium);
})->with('monthly-paid premium add-on');

it('pays overtime, night differential and night overtime on top of every day type', function (
    string $dayType,
    int $regularPay,
    int $overtimePay,
    int $nightPay,
    int $nightOTPay,
    int $gross,
) {
    $slip = timesheetPayrollRun(timesheetPayrollEmployee([
        timesheetPayrollLine($dayType, hours: 8, overtime: 2, night: 2, nightOT: 2),
    ]));

    expect($slip->regularPay)->toBe($regularPay)
        ->and($slip->overtimePay)->toBe($overtimePay)
        ->and($slip->nightPay)->toBe($nightPay)
        ->and($slip->nightOTPay)->toBe($nightOTPay)
        ->and($slip->gross)->toBe($gross)
        ->and($slip->overtimeHours)->toBe(2.0)
        ->and($slip->nightHours)->toBe(4.0);
})->with('overtime and night differential on premium days');

it('pays the actual hours of an undertime day', function () {
    $slip = timesheetPayrollRun(timesheetPayrollEmployee([
        timesheetPayrollLine(Rules::DAY_UNDERTIME, hours: 4),
    ]));

    expect($slip->regularPay)->toBe(60_000)
        ->and($slip->regularHours)->toBe(4.0)
        ->and($slip->gross)->toBe(60_000);
});

it('pays a paid leave day without attendance for a daily-paid employee', function () {
    $slip = timesheetPayrollRun(timesheetPayrollEmployee([
        timesheetPayrollLine(Rules::DAY_PAID_LEAVE, hours: 0, paidLeave: true),
    ]));

    expect($slip->regularPay)->toBe(120_000)
        ->and($slip->regularHours)->toBe(8.0)
        ->and($slip->gross)->toBe(120_000);
});

it('keeps the fixed basic on a paid leave day for a monthly-paid employee', function () {
    $slip = timesheetPayrollRun(timesheetPayrollEmployee(
        [timesheetPayrollLine(Rules::DAY_PAID_LEAVE, hours: 0, paidLeave: true)],
        Employee::PAYMENT_MONTHLY,
    ));

    expect($slip->regularPay)->toBe(1_305_000)
        ->and($slip->gross)->toBe(1_305_000);
});

it('deducts an unpaid leave and an absence from the monthly fixed basic', function (string $dayType, bool $absent) {
    $slip = timesheetPayrollRun(timesheetPayrollEmployee(
        [timesheetPayrollLine($dayType, hours: 0, absent: $absent)],
        Employee::PAYMENT_MONTHLY,
    ));

    expect($slip->regularPay)->toBe(1_185_000) // ₱13,050.00 - ₱1,200.00
        ->and($slip->basicEarned)->toBe(1_185_000)
        ->and($slip->gross)->toBe(1_185_000);
})->with([
    'unpaid leave' => [Rules::DAY_UNPAID_LEAVE, false],
    'absence' => [Rules::DAY_ABSENT, true],
]);

it('records the absence day and amount without double-deducting it', function () {
    $slip = timesheetPayrollRun(timesheetPayrollEmployee(
        [timesheetPayrollLine(Rules::DAY_ABSENT, hours: 0, absent: true)],
        Employee::PAYMENT_MONTHLY,
    ));

    expect($slip->absentDays)->toBe(1.0)
        ->and($slip->absentAmount)->toBe(120_000)
        ->and($slip->gross)->toBe(1_185_000);
});

it('pays nothing for a daily-paid absence or unpaid leave', function (string $dayType, bool $absent) {
    $slip = timesheetPayrollRun(timesheetPayrollEmployee([
        timesheetPayrollLine($dayType, hours: 0, absent: $absent),
    ]));

    expect($slip->gross)->toBe(0)
        ->and($slip->netPay)->toBe(0)
        ->and($slip->warnings)->toContain('no payable days in this period');
})->with([
    'unpaid leave' => [Rules::DAY_UNPAID_LEAVE, false],
    'absence' => [Rules::DAY_ABSENT, true],
]);

it('falls back to the ordinary rate when the employee is not entitled to the premium', function (
    string $dayType,
    BenefitFlags $benefits,
) {
    $slip = timesheetPayrollRun(timesheetPayrollEmployee(
        [timesheetPayrollLine($dayType)],
        benefits: $benefits,
    ));

    expect($slip->regularPay)->toBe(120_000)
        ->and($slip->holidayPay)->toBe(0)
        ->and($slip->restDayPay)->toBe(0)
        ->and($slip->warnings)->toContain("2026-11-02: premium for {$dayType} not applied (not entitled)");
})->with([
    'rest day' => ['rd', new BenefitFlags],
    'special day' => ['sh', new BenefitFlags],
    'regular holiday' => ['rh', new BenefitFlags],
    'regular holiday on rest day' => ['rd-rh', new BenefitFlags],
    'double regular holiday uses the premium flag' => ['2rh', new BenefitFlags(regularHolidayPay: true)],
    'regular + special holiday uses its own flag' => [
        'rh-sh',
        new BenefitFlags(regularHolidayPay: true, specialHolidayPay: true),
    ],
]);

it('applies the overtime premium only when the employee is entitled', function (bool $entitled, int $expectedOvertime) {
    $benefits = timesheetPayrollBenefits();
    $benefits->overtimePay = $entitled;

    $slip = timesheetPayrollRun(timesheetPayrollEmployee(
        [timesheetPayrollLine('regular', overtime: 2)],
        benefits: $benefits,
    ));

    expect($slip->overtimePay)->toBe($expectedOvertime)
        ->and($slip->gross)->toBe(120_000 + $expectedOvertime);

    if (! $entitled) {
        expect($slip->warnings)->toContain('2026-11-02: overtime premium not applied (not entitled); ordinary rate used');
    }
})->with([
    'entitled' => [true, 37_500],
    'not entitled' => [false, 30_000],
]);

it('applies the night differential only when the employee is entitled', function (bool $entitled, int $expectedNight) {
    $benefits = timesheetPayrollBenefits();
    $benefits->nightDifferential = $entitled;

    $slip = timesheetPayrollRun(timesheetPayrollEmployee(
        [timesheetPayrollLine('regular', night: 2)],
        benefits: $benefits,
    ));

    expect($slip->nightPay)->toBe($expectedNight)
        ->and($slip->gross)->toBe(120_000 + $expectedNight);

    if (! $entitled) {
        expect($slip->warnings)->toContain('2026-11-02: night differential not applied (not entitled)');
    }
})->with([
    'entitled' => [true, 3_000],
    'not entitled' => [false, 0],
]);

it('pays an unworked regular holiday when the employee worked the preceding day', function () {
    $slip = timesheetPayrollRun(timesheetPayrollEmployee([
        timesheetPayrollLine('regular', date: '2026-12-24'),
    ]), timesheetPayrollConfig([
        'holidays' => [['date' => '2026-12-25', 'code' => 'rh']],
        'pay_unworked_regular_holidays' => true,
    ]));

    expect($slip->regularPay)->toBe(240_000)
        ->and($slip->holidayPay)->toBe(120_000)
        ->and($slip->regularHours)->toBe(16.0)
        ->and($slip->gross)->toBe(240_000);
});

it('pays an unworked regular holiday when the preceding day has no record', function () {
    $slip = timesheetPayrollRun(timesheetPayrollEmployee([
        timesheetPayrollLine('regular', date: '2026-12-23'),
    ]), timesheetPayrollConfig([
        'holidays' => [['date' => '2026-12-25', 'code' => 'rh']],
        'pay_unworked_regular_holidays' => true,
    ]));

    expect($slip->regularPay)->toBe(240_000)
        ->and($slip->holidayPay)->toBe(120_000);
});

it('withholds an unworked regular holiday when the preceding day was absent', function () {
    $slip = timesheetPayrollRun(timesheetPayrollEmployee([
        timesheetPayrollLine(Rules::DAY_ABSENT, hours: 0, absent: true, date: '2026-12-24'),
    ]), timesheetPayrollConfig([
        'holidays' => [['date' => '2026-12-25', 'code' => 'rh']],
        'pay_unworked_regular_holidays' => true,
    ]));

    expect($slip->holidayPay)->toBe(0)
        ->and($slip->gross)->toBe(0)
        ->and($slip->warnings)->toContain('2026-12-25: unworked regular holiday unpaid (absent the preceding workday)');
});

it('does not pay an unworked special holiday or add a second day for monthly-paid staff', function (string $code, string $paymentType, int $expectedRegular) {
    $slip = timesheetPayrollRun(timesheetPayrollEmployee(
        [timesheetPayrollLine('regular', date: '2026-12-24')],
        $paymentType,
    ), timesheetPayrollConfig([
        'holidays' => [['date' => '2026-12-25', 'code' => $code]],
        'pay_unworked_regular_holidays' => true,
    ]));

    expect($slip->regularPay)->toBe($expectedRegular)
        ->and($slip->holidayPay)->toBe(0);
})->with([
    'special holiday is not an unworked paid day' => ['sh', Employee::PAYMENT_DAILY, 120_000],
    'monthly basic already covers the regular holiday' => ['rh', Employee::PAYMENT_MONTHLY, 1_305_000],
]);

it('aggregates a whole period of every day type without losing a premium', function () {
    $dayTypes = ['regular', 'rd', 'sh', 'rd-sh', 'rh', 'rd-rh', '2rh', 'rd-2rh', 'rh-sh', 'rd-rh-sh'];

    $lines = [];

    foreach ($dayTypes as $index => $dayType) {
        $lines[] = timesheetPayrollLine($dayType, date: sprintf('2026-11-%02d', $index + 1));
    }

    $slip = timesheetPayrollRun(timesheetPayrollEmployee($lines));

    // Σ multipliers = 22.20 × ₱1,200.00 = ₱26,640.00
    expect($slip->regularPay)->toBe(2_664_000)
        ->and($slip->restDayPay)->toBe(36_000)          // 0.30 × ₱1,200.00
        ->and($slip->holidayPay)->toBe(1_428_000)       // 11.90 × ₱1,200.00
        ->and($slip->regularHours)->toBe(80.0)
        ->and($slip->gross)->toBe(2_664_000);
});

it('does not pay a rest-day premium for an unworked rest day', function () {
    $slip = timesheetPayrollRun(timesheetPayrollEmployee([
        timesheetPayrollLine('rd', hours: 0),
    ]));

    expect($slip->regularPay)->toBe(0)
        ->and($slip->restDayPay)->toBe(0)
        ->and($slip->gross)->toBe(0);
});

it('prefers a recognised premium rate code over the stored day classification', function (
    string $dayType,
    ?string $rateCode,
    string $expected,
) {
    expect(Rules::effectiveDayType($dayType, $rateCode))->toBe($expected);
})->with([
    'worked regular holiday' => ['regular', 'rh', Rules::DAY_REGULAR_HOLIDAY],
    'worked special holiday' => ['regular', 'sh', Rules::DAY_SPECIAL_HOLIDAY],
    'worked holiday on a rest day' => ['rd', 'rd-rh', Rules::DAY_REST_REGULAR_HOLIDAY],
    'worked special holiday on a rest day' => ['rd', 'rd-sh', Rules::DAY_REST_SPECIAL_HOLIDAY],
    'worked double holiday on a rest day' => ['rd', 'rd-2rh', Rules::DAY_REST_DOUBLE_HOLIDAY],
    'paid leave' => ['regular', 'pl', Rules::DAY_PAID_LEAVE],
    'unpaid leave' => ['regular', 'upl', Rules::DAY_UNPAID_LEAVE],
    'rest day keeps its day classification' => ['rd', 'regular', 'rd'],
    'ordinary day keeps its day classification' => ['regular', 'regular', 'regular'],
    'non-premium rate code is ignored' => ['regular', 'no_log', 'regular'],
    'missing rate code falls back to day_type' => ['regular', null, 'regular'],
    'blank rate code falls back to day_type' => ['absent', '', 'absent'],
]);

it('pays a daily-paid employee from the contracted daily rate', function () {
    $employee = new Employee(
        id: 2,
        number: 'E-0002',
        name: 'DAILY EMPLOYEE',
        monthlyBasic: 700_000,   // the admin form stores the daily rate here
        dailyRate: 700_000,      // PHP 7,000.00 per day
        paymentType: Employee::PAYMENT_DAILY,
        benefits: timesheetPayrollBenefits(),
        timesheets: collect(range(1, 10))->map(fn (int $day) => timesheetPayrollLine(
            'regular',
            date: '2026-11-'.str_pad((string) $day, 2, '0', STR_PAD_LEFT),
        ))->all(),
    );

    $slip = timesheetPayrollRun($employee);

    expect($slip->regularPay)->toBe(7_000_000) // PHP 70,000.00 for ten days
        ->and($slip->dailyRate)->toBe(700_000);
});

it('pays half of the daily rate for a half-day paid leave', function () {
    $slip = timesheetPayrollRun(timesheetPayrollEmployee([
        timesheetPayrollLine('regular', paidLeave: true, leave: true, leaveFraction: 0.5),
    ]));

    expect($slip->regularPay)->toBe(60_000) // PHP 600.00 = half of PHP 1,200.00
        ->and($slip->regularHours)->toBe(4.0);
});

it('deducts half a day for unpaid half-day leave from a monthly-paid employee', function () {
    $slip = timesheetPayrollRun(timesheetPayrollEmployee(
        [timesheetPayrollLine('upl', hours: 0, leave: true, leaveFraction: 0.5)],
        Employee::PAYMENT_MONTHLY,
    ));

    expect($slip->regularPay)->toBe(1_245_000); // PHP 13,050.00 - PHP 600.00
});
