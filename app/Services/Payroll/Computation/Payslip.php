<?php

namespace App\Services\Payroll\Computation;

/**
 * The computed result for one employee.
 *
 * Mirrors model.Payslip in payroll-engine/internal/model.
 */
final class Payslip
{
    /**
     * @param  list<Earning>  $compensations
     * @param  list<string>  $warnings
     */
    public function __construct(
        public int $employeeID = 0,
        public string $employeeNo = '',
        public string $employeeName = '',
        public int $basicMonthly = 0,
        public int $dailyRate = 0,
        public int $periodBasic = 0,
        public int $basicEarned = 0,
        public float $regularHours = 0.0,
        public int $regularPay = 0,
        public float $overtimeHours = 0.0,
        public int $overtimePay = 0,
        public float $nightHours = 0.0,
        public int $nightPay = 0,
        public int $nightOTPay = 0,
        public int $holidayPay = 0,
        public int $restDayPay = 0,
        public float $absentDays = 0.0,
        public int $absentAmount = 0,
        public int $allowances = 0,
        public int $incentives = 0,
        public array $compensations = [],
        public int $bonus = 0,
        public int $commission = 0,
        public int $thirteenthMonth = 0,
        public int $leaveConversion = 0,
        public int $separationPay = 0,
        public int $annualAdjustment = 0,
        public int $gross = 0,
        public int $sssEmployee = 0,
        public int $sssProvident = 0,
        public int $sssEmployer = 0,
        public int $sssProvidentER = 0,
        public int $sssEC = 0,
        public int $sssTotal = 0,
        public int $philHealthEmployee = 0,
        public int $philHealthEmployer = 0,
        public int $pagIbigEmployee = 0,
        public int $pagIbigEmployer = 0,
        public int $taxableIncome = 0,
        public int $nonTaxable = 0,
        public int $benefitExempt = 0,
        public int $minimumWageExemption = 0,
        public int $withholdingTax = 0,
        public int $otherDeductions = 0,
        public int $loans = 0,
        public int $totalDeductions = 0,
        public int $totalEmployerCost = 0,
        public int $netPay = 0,
        public array $warnings = [],
    ) {}

    public function sssEmployeeTotal(): int
    {
        return Money::add($this->sssEmployee, $this->sssProvident);
    }

    public function sssEmployerTotal(): int
    {
        return Money::add(Money::add($this->sssEmployer, $this->sssProvidentER), $this->sssEC);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $slip = new self;
        $slip->fill($data);

        return $slip;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function fill(array $data): void
    {
        $this->employeeID = (int) ($data['employee_id'] ?? 0);
        $this->employeeNo = (string) ($data['employee_no'] ?? '');
        $this->employeeName = (string) ($data['employee_name'] ?? '');
        $this->basicMonthly = (int) ($data['basic_monthly'] ?? 0);
        $this->dailyRate = (int) ($data['daily_rate'] ?? 0);
        $this->periodBasic = (int) ($data['period_basic'] ?? 0);
        $this->basicEarned = (int) ($data['basic_earned'] ?? 0);
        $this->regularHours = (float) ($data['regular_hours'] ?? 0);
        $this->regularPay = (int) ($data['regular_pay'] ?? 0);
        $this->overtimeHours = (float) ($data['overtime_hours'] ?? 0);
        $this->overtimePay = (int) ($data['overtime_pay'] ?? 0);
        $this->nightHours = (float) ($data['night_hours'] ?? 0);
        $this->nightPay = (int) ($data['night_pay'] ?? 0);
        $this->nightOTPay = (int) ($data['night_ot_pay'] ?? 0);
        $this->holidayPay = (int) ($data['holiday_pay'] ?? 0);
        $this->restDayPay = (int) ($data['rest_day_pay'] ?? 0);
        $this->absentDays = (float) ($data['absent_days'] ?? 0);
        $this->absentAmount = (int) ($data['absent_amount'] ?? 0);
        $this->allowances = (int) ($data['allowances'] ?? 0);
        $this->incentives = (int) ($data['incentives'] ?? 0);
        $this->compensations = array_map(
            static fn (array $row): Earning => Earning::fromArray($row),
            array_values((array) ($data['compensations'] ?? [])),
        );
        $this->bonus = (int) ($data['bonus'] ?? 0);
        $this->commission = (int) ($data['commission'] ?? 0);
        $this->thirteenthMonth = (int) ($data['thirteenth_month'] ?? 0);
        $this->leaveConversion = (int) ($data['leave_conversion'] ?? 0);
        $this->separationPay = (int) ($data['separation_pay'] ?? 0);
        $this->annualAdjustment = (int) ($data['annual_adjustment'] ?? 0);
        $this->gross = (int) ($data['gross'] ?? 0);
        $this->sssEmployee = (int) ($data['sss_employee'] ?? 0);
        $this->sssProvident = (int) ($data['sss_provident'] ?? 0);
        $this->sssEmployer = (int) ($data['sss_employer'] ?? 0);
        $this->sssProvidentER = (int) ($data['sss_provident_er'] ?? 0);
        $this->sssEC = (int) ($data['sss_ec'] ?? 0);
        $this->sssTotal = (int) ($data['sss_total'] ?? 0);
        $this->philHealthEmployee = (int) ($data['philhealth_employee'] ?? 0);
        $this->philHealthEmployer = (int) ($data['philhealth_employer'] ?? 0);
        $this->pagIbigEmployee = (int) ($data['pagibig_employee'] ?? 0);
        $this->pagIbigEmployer = (int) ($data['pagibig_employer'] ?? 0);
        $this->taxableIncome = (int) ($data['taxable_income'] ?? 0);
        $this->nonTaxable = (int) ($data['non_taxable'] ?? 0);
        $this->benefitExempt = (int) ($data['benefit_exempt'] ?? 0);
        $this->minimumWageExemption = (int) ($data['minimum_wage_exemption'] ?? 0);
        $this->withholdingTax = (int) ($data['withholding_tax'] ?? 0);
        $this->otherDeductions = (int) ($data['other_deductions'] ?? 0);
        $this->loans = (int) ($data['loans'] ?? 0);
        $this->totalDeductions = (int) ($data['total_deductions'] ?? 0);
        $this->totalEmployerCost = (int) ($data['total_employer_cost'] ?? 0);
        $this->netPay = (int) ($data['net_pay'] ?? 0);
        $this->warnings = array_values(array_map('strval', (array) ($data['warnings'] ?? [])));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'employee_id' => $this->employeeID,
            'employee_no' => $this->employeeNo,
            'employee_name' => $this->employeeName,
            'basic_monthly' => $this->basicMonthly,
            'daily_rate' => $this->dailyRate,
            'period_basic' => $this->periodBasic,
            'basic_earned' => $this->basicEarned,
            'regular_hours' => $this->regularHours,
            'regular_pay' => $this->regularPay,
            'overtime_hours' => $this->overtimeHours,
            'overtime_pay' => $this->overtimePay,
            'night_hours' => $this->nightHours,
            'night_pay' => $this->nightPay,
            'night_ot_pay' => $this->nightOTPay,
            'holiday_pay' => $this->holidayPay,
            'rest_day_pay' => $this->restDayPay,
            'absent_days' => $this->absentDays,
            'absent_amount' => $this->absentAmount,
            'allowances' => $this->allowances,
            'incentives' => $this->incentives,
            'compensations' => array_map(static fn (Earning $earning): array => $earning->toArray(), $this->compensations),
            'bonus' => $this->bonus,
            'commission' => $this->commission,
            'thirteenth_month' => $this->thirteenthMonth,
            'leave_conversion' => $this->leaveConversion,
            'separation_pay' => $this->separationPay,
            'annual_adjustment' => $this->annualAdjustment,
            'gross' => $this->gross,
            'sss_employee' => $this->sssEmployee,
            'sss_provident' => $this->sssProvident,
            'sss_employer' => $this->sssEmployer,
            'sss_provident_er' => $this->sssProvidentER,
            'sss_ec' => $this->sssEC,
            'sss_total' => $this->sssTotal,
            'philhealth_employee' => $this->philHealthEmployee,
            'philhealth_employer' => $this->philHealthEmployer,
            'pagibig_employee' => $this->pagIbigEmployee,
            'pagibig_employer' => $this->pagIbigEmployer,
            'taxable_income' => $this->taxableIncome,
            'non_taxable' => $this->nonTaxable,
            'benefit_exempt' => $this->benefitExempt,
            'minimum_wage_exemption' => $this->minimumWageExemption,
            'withholding_tax' => $this->withholdingTax,
            'other_deductions' => $this->otherDeductions,
            'loans' => $this->loans,
            'total_deductions' => $this->totalDeductions,
            'total_employer_cost' => $this->totalEmployerCost,
            'net_pay' => $this->netPay,
            'warnings' => $this->warnings,
        ];
    }
}
