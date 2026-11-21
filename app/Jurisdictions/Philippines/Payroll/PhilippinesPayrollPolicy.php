<?php

namespace App\Jurisdictions\Philippines\Payroll;

use App\Jurisdictions\Support\PayrollPolicyData;

/**
 * Philippine non-statutory payroll rules.
 *
 * Values mirror the Labor Code and the existing engine defaults; keeping them
 * here is what allows another country to replace them.
 */
final class PhilippinesPayrollPolicy extends PayrollPolicyData
{
    public function __construct()
    {
        parent::__construct(
            overtimeMultiplier: 1.25,
            premiumDayOvertimeMultiplier: 1.30,
            nightDifferentialRate: 0.10,
            nightWindowStart: '22:00',
            nightWindowEnd: '06:00',
            thirteenthMonthExemptCentavos: 9_000_000,
            minimumWageTaxExempt: true,
            daysPerYearDefault: 261,
            hoursPerDayDefault: 8,
            leaveConversion: [
                'government' => 'monetizable',
                'private' => 'taxable_on_conversion',
            ],
            specialRuns: ['regular', 'thirteenth_month', 'final_pay', 'annual_adjustment'],
        );
    }
}
