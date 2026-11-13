<?php

namespace App\Services\Payroll\Computation;

use DateTimeImmutable;
use RuntimeException;

/**
 * Computes a payroll run from attendance and statutory rules.
 *
 * Mirrors payroll-engine/internal/payroll (compute.go and special.go) so the
 * PHP driver and the Go binary produce the same payslips for the same input.
 */
final class Computer
{
    /**
     * Compute the payslip for one employee.
     */
    public static function compute(Config $cfg, Employee $employee): Payslip
    {
        return match (strtolower(trim($cfg->runType))) {
            Config::RUN_THIRTEENTH_MONTH => self::computeThirteenthMonth($cfg, $employee),
            Config::RUN_FINAL_PAY => self::computeFinalPay($cfg, $employee),
            Config::RUN_ANNUAL_ADJUSTMENT => self::computeAnnualAdjustment($cfg, $employee),
            default => self::computeRegular($cfg, $employee),
        };
    }

    /**
     * @param  list<Employee>  $employees
     * @return list<Payslip>
     */
    public static function computeAll(Config $cfg, array $employees): array
    {
        $slips = [];

        foreach ($employees as $employee) {
            $slips[] = self::compute($cfg, $employee);
        }

        return $slips;
    }

    public static function computeRegular(Config $cfg, Employee $employee): Payslip
    {
        $slip = new Payslip(
            employeeID: $employee->id,
            employeeNo: $employee->number,
            employeeName: $employee->name,
            basicMonthly: $employee->monthlyBasic,
        );

        $daysPerYear = $cfg->daysPerYear > 0 ? $cfg->daysPerYear : 261;
        $hoursPerDay = $cfg->hoursPerDay > 0 ? $cfg->hoursPerDay : 8;

        $daily = $employee->dailyRate;

        if ($daily === 0) {
            $daily = Rules::dailyRate($employee->monthlyBasic, $daysPerYear);
        }

        $hourly = Rules::hourlyRate($employee->monthlyBasic, $daysPerYear, $hoursPerDay);
        $slip->dailyRate = $daily;

        $monthlyPaid = strtolower(trim($employee->paymentType)) === Employee::PAYMENT_MONTHLY;
        $periods = $cfg->periodsPerMonth();

        if ($periods <= 0) {
            $periods = 1;
        }

        $periodBasic = self::split($employee->monthlyBasic, $periods);
        $slip->periodBasic = $periodBasic;

        // Fixed period basic for monthly-paid staff: the monthly rate divided
        // by the number of pays. Daily-paid staff earn only for attended days.
        if ($monthlyPaid) {
            $slip->regularPay = $periodBasic;
            $slip->basicEarned = $periodBasic;
        }

        if ($employee->timesheets === []) {
            if ($cfg->payWithoutTimesheet) {
                $slip->regularPay = $periodBasic;
                $slip->basicEarned = $slip->regularPay;
                $slip->regularHours = (float) ($hoursPerDay * self::periodDays($cfg, $daysPerYear));
                $slip->warnings[] = 'no attendance recorded; paid on basic salary';
            } else {
                $slip->regularPay = 0;
                $slip->warnings[] = 'no attendance recorded; nothing paid';
            }
        }

        foreach ($employee->timesheets as $line) {
            if ($line->legacyHours) {
                $slip->warnings[] = sprintf(
                    '%s: hours reconstructed from the legacy premium-equivalent columns',
                    $line->date,
                );
            }

            if ($line->absent) {
                $slip->absentDays += 1.0;

                if ($monthlyPaid) {
                    $slip->regularPay -= $daily;
                    $slip->basicEarned -= $daily;
                }

                continue;
            }

            if ($line->paidLeave || Rules::isPaidDay($line->dayType)) {
                if (! $monthlyPaid) {
                    $slip->regularPay += $daily;
                    $slip->basicEarned += $daily;
                    $slip->regularHours += (float) $hoursPerDay;
                }

                continue;
            }

            if (Rules::isUnpaidDay($line->dayType)) {
                if ($monthlyPaid) {
                    $slip->regularPay -= $daily;
                    $slip->basicEarned -= $daily;
                }

                continue;
            }

            if ($line->hours <= 0 && $line->overtime <= 0 && $line->night <= 0 && $line->nightOT <= 0) {
                continue;
            }

            [$dayRate, $premiumOnly, $note] = self::entitledDayRate($employee->benefits, $line->dayType, $line->date);

            if ($note !== null) {
                $slip->warnings[] = $note;
            }

            $regularPay = Money::mul($hourly, $line->hours * $dayRate);

            if ($monthlyPaid) {
                $regularPay = Money::mul($hourly, $line->hours * $premiumOnly);
            }

            $overtimeRate = $dayRate * Rules::OVERTIME_MULTIPLIER;

            if (! $employee->benefits->overtimePay && $line->overtime > 0) {
                $slip->warnings[] = sprintf(
                    '%s: overtime premium not applied (not entitled); ordinary rate used',
                    $line->date,
                );
                $overtimeRate = $dayRate;
            }

            $overtimePay = Money::mul($hourly, $line->overtime * $overtimeRate);

            $nightRate = 0.0;

            if ($employee->benefits->nightDifferential) {
                $nightRate = $dayRate * Rules::NIGHT_DIFFERENTIAL_RATE;
            } elseif ($line->night > 0) {
                $slip->warnings[] = sprintf('%s: night differential not applied (not entitled)', $line->date);
            }

            $nightPay = Money::mul($hourly, $line->night * $nightRate);

            $nightOTPay = Money::ZERO;

            if ($line->nightOT > 0) {
                $base = $dayRate * Rules::OVERTIME_MULTIPLIER;

                if (! $employee->benefits->overtimePay) {
                    $base = $dayRate;
                }

                $differential = $employee->benefits->nightDifferential ? Rules::NIGHT_DIFFERENTIAL_RATE : 0.0;

                $nightOTPay = Money::mul($hourly, $line->nightOT * $base * $differential);
            }

            if (! $monthlyPaid) {
                $slip->basicEarned += Money::mul($hourly, $line->hours);
            }

            $slip->regularPay += $regularPay;
            $slip->overtimePay += $overtimePay;
            $slip->nightPay += $nightPay;
            $slip->nightOTPay += $nightOTPay;

            $slip->regularHours += $line->hours;
            $slip->overtimeHours += $line->overtime;
            $slip->nightHours += $line->night + $line->nightOT;

            // Report only the premium component (already included in the
            // totals above) so the payslip does not show base pay twice.
            $premiumPay = $regularPay;

            if (! $monthlyPaid) {
                $premiumPay = $regularPay - Money::mul($hourly, $line->hours);
            }

            if (Rules::isHoliday($line->dayType)) {
                $slip->holidayPay += $premiumPay;
            } elseif (Rules::isRestDay($line->dayType)) {
                $slip->restDayPay += $premiumPay;
            }
        }

        if ($monthlyPaid && $slip->regularPay < 0) {
            $slip->regularPay = 0;
        }

        if ($slip->basicEarned < 0) {
            $slip->basicEarned = 0;
        }

        // Unworked regular holidays (Article 94). Monthly-paid staff already
        // have the day inside their fixed period basic; daily-paid staff are
        // paid 100% of the daily rate when they worked or were on paid leave
        // the day before.
        if (! $monthlyPaid && $cfg->payUnworkedRegularHolidays) {
            foreach ($cfg->holidays as $holiday) {
                if (! Rules::isRegularHoliday($holiday->code) || self::workedOn($employee->timesheets, $holiday->date)) {
                    continue;
                }

                if (! self::eligibleForUnworkedHoliday($employee->timesheets, $holiday->date, $cfg->payWithoutTimesheet)) {
                    $slip->warnings[] = sprintf(
                        '%s: unworked regular holiday unpaid (absent the preceding workday)',
                        $holiday->date,
                    );

                    continue;
                }

                if (! $employee->benefits->regularHolidayPay) {
                    $slip->warnings[] = sprintf(
                        '%s: unworked regular holiday not applied (not entitled)',
                        $holiday->date,
                    );

                    continue;
                }

                $slip->holidayPay += $daily;
                $slip->regularPay += $daily;
                $slip->regularHours += (float) $hoursPerDay;
            }
        }

        // Attendance lines are paid as they are read, so an absent day has
        // already earned nothing. AbsentAmount stays on the slip as
        // information for the payslip and register; it must not be subtracted
        // from the gross again.
        $slip->absentAmount = Rules::absenceDeduction($daily, $slip->absentDays);

        // Additional earnings: allowances, compensations, bonuses, commissions.
        foreach ($employee->allowances as $earning) {
            $slip->allowances += $earning->amount;
            $slip->nonTaxable += self::nonTaxablePart($cfg, $employee, $earning, $periods);
        }

        foreach ($employee->compensations as $earning) {
            $slip->compensations[] = $earning;
            $slip->incentives += $earning->amount;

            if (! $earning->taxable) {
                $slip->nonTaxable += $earning->amount;
            }
        }

        foreach ($employee->bonuses as $earning) {
            $slip->bonus += $earning->amount;

            if ($earning->nonTaxableCeiling > 0) {
                $exempt = Money::min($earning->amount, $earning->nonTaxableCeiling);
                $slip->nonTaxable += $exempt;
                $slip->benefitExempt += $exempt;
            } elseif (! $earning->taxable) {
                $slip->nonTaxable += $earning->amount;
                $slip->benefitExempt += $earning->amount;
            }
        }

        foreach ($employee->commissions as $earning) {
            $slip->commission += $earning->amount;

            if (! $earning->taxable) {
                $slip->nonTaxable += $earning->amount;
                $slip->benefitExempt += $earning->amount;
            }
        }

        $slip->gross = $slip->regularPay
            + $slip->overtimePay
            + $slip->nightPay
            + $slip->nightOTPay
            + $slip->allowances
            + $slip->incentives
            + $slip->bonus
            + $slip->commission;

        if ($slip->gross < 0) {
            $slip->gross = 0;
        }

        // No pay means no contributions; avoid producing a negative net pay.
        if ($slip->gross === 0) {
            $slip->warnings[] = 'no payable days in this period';
            $slip->netPay = 0;
            $slip->taxableIncome = 0;

            return $slip;
        }

        // Each agency has its own contribution base so a company can follow
        // the agency circular without code changes.
        $sssBase = self::contributionBase($cfg->sssBase, $employee, $slip, $periods);
        $philHealthBase = self::contributionBase($cfg->philHealthBase, $employee, $slip, $periods);
        $pagIbigBase = self::contributionBase($cfg->pagIbigBase, $employee, $slip, $periods);

        $divisor = Statutory::divisor($cfg->sssSchedule, $cfg->frequency, $cfg->cutoff);

        if ($divisor > 0) {
            try {
                $sss = Statutory::computeSss($sssBase, $cfg->sssBrackets);

                $slip->sssEmployee = self::firstPart($sss->ee, $divisor);
                $slip->sssProvident = self::firstPart($sss->wispEE, $divisor);
                $slip->sssEmployer = self::firstPart($sss->er, $divisor);
                $slip->sssProvidentER = self::firstPart($sss->wispER, $divisor);
                $slip->sssEC = self::firstPart($sss->ec, $divisor);
                $slip->sssTotal = $slip->sssEmployee
                    + $slip->sssProvident
                    + $slip->sssEmployer
                    + $slip->sssProvidentER
                    + $slip->sssEC;
            } catch (RuntimeException $exception) {
                $slip->warnings[] = $exception->getMessage();
            }
        }

        $divisor = Statutory::divisor($cfg->philHealthSchedule, $cfg->frequency, $cfg->cutoff);

        if ($divisor > 0) {
            $philHealth = Statutory::computePhilHealth($philHealthBase, $cfg->philHealth);
            $slip->philHealthEmployee = self::firstPart($philHealth->employee, $divisor);
            $slip->philHealthEmployer = self::firstPart($philHealth->employer, $divisor);
        }

        $divisor = Statutory::divisor($cfg->pagIbigSchedule, $cfg->frequency, $cfg->cutoff);

        if ($divisor > 0) {
            $pagIbig = Statutory::computePagIbig($pagIbigBase, $cfg->pagIbig);
            $slip->pagIbigEmployee = self::firstPart($pagIbig->employee, $divisor);
            $slip->pagIbigEmployer = self::firstPart($pagIbig->employer, $divisor);
        }

        foreach ($employee->deductions as $deduction) {
            $slip->otherDeductions += $deduction->amount;
        }

        foreach ($employee->loans as $loan) {
            $slip->loans += $loan->amount;
            $slip->otherDeductions += $loan->amount;
        }

        // Minimum wage earners are exempt on the statutory minimum wage and on
        // the holiday, overtime and night-shift pay attributable to it
        // (RA 9504 as amended by RA 10963).
        $minWageExemption = Money::ZERO;

        if ($employee->isMinimumWage && $employee->minimumWage > 0) {
            // A minimum-wage flag must be supported by the rate actually paid.
            // A stale or incorrect flag must not create a tax exemption.
            $rate = $employee->dailyRate;

            if ($rate === 0) {
                $rate = Rules::dailyRate($employee->monthlyBasic, $daysPerYear);
            }

            if ($rate > $employee->minimumWage) {
                $slip->warnings[] = sprintf(
                    'minimum wage flag ignored: daily rate %s exceeds the applicable minimum wage %s',
                    Money::format($rate),
                    Money::format($employee->minimumWage),
                );
            } else {
                $statutoryWage = self::minimumWagePay($cfg, $employee, $slip, $daysPerYear, $periods);
                $premiums = $slip->holidayPay
                    + $slip->restDayPay
                    + $slip->overtimePay
                    + $slip->nightPay
                    + $slip->nightOTPay;

                $minWageExemption = Money::min($slip->gross, $statutoryWage + $premiums);
                $slip->minimumWageExemption = $minWageExemption;
            }
        }

        // Mandatory employee contributions and non-taxable benefits are exempt.
        $slip->taxableIncome = $slip->gross
            - $slip->nonTaxable
            - $minWageExemption
            - $slip->sssEmployee
            - $slip->sssProvident
            - $slip->philHealthEmployee
            - $slip->pagIbigEmployee;

        if ($slip->taxableIncome < 0) {
            $slip->taxableIncome = 0;
        }

        if (Statutory::divisor($cfg->taxSchedule, $cfg->frequency, $cfg->cutoff) > 0) {
            try {
                $slip->withholdingTax = self::withholding($cfg, $slip->taxableIncome, $periods);
            } catch (RuntimeException $exception) {
                $slip->warnings[] = $exception->getMessage();
            }
        }

        $slip->totalDeductions = $slip->sssEmployee
            + $slip->sssProvident
            + $slip->philHealthEmployee
            + $slip->pagIbigEmployee
            + $slip->withholdingTax
            + $slip->otherDeductions;

        $slip->netPay = $slip->gross - $slip->totalDeductions;

        // Deductions can never take an employee below zero; flag the shortfall
        // so payroll can review it instead of silently producing a negative
        // payslip.
        if ($slip->netPay < 0) {
            $slip->warnings[] = sprintf(
                'deductions exceed earnings by %s',
                Money::format($slip->totalDeductions - $slip->gross),
            );
            $slip->netPay = 0;
        }

        $slip->totalEmployerCost = $slip->sssEmployer
            + $slip->sssProvidentER
            + $slip->sssEC
            + $slip->philHealthEmployer
            + $slip->pagIbigEmployer;

        if ($cfg->minimumWage > 0 && $daily > 0 && $daily < $cfg->minimumWage) {
            $slip->warnings[] = sprintf(
                'daily rate %s is below the minimum wage %s',
                Money::format($daily),
                Money::format($cfg->minimumWage),
            );
        }

        return $slip;
    }

    /**
     * 13th-month pay: 1/12 of the basic salary actually earned in the
     * calendar year (PD 851 as amended by MO 28). The ₱90,000 exclusion is
     * aggregated with the employee's other benefits for the year and only the
     * excess is taxable.
     */
    public static function computeThirteenthMonth(Config $cfg, Employee $employee): Payslip
    {
        $slip = new Payslip(
            employeeID: $employee->id,
            employeeNo: $employee->number,
            employeeName: $employee->name,
            basicMonthly: $employee->monthlyBasic,
        );

        $daysPerYear = $cfg->daysPerYear > 0 ? $cfg->daysPerYear : 261;

        $slip->dailyRate = $employee->dailyRate;

        if ($slip->dailyRate === 0) {
            $slip->dailyRate = Rules::dailyRate($employee->monthlyBasic, $daysPerYear);
        }

        $amount = Rules::thirteenthMonth($employee->ytd->basicEarned);

        if ($amount <= 0) {
            $slip->warnings[] = 'no basic salary earned in the year; 13th month is zero';

            return $slip;
        }

        $taxable = self::incrementalBenefitExcess($amount, $employee->ytd->otherBenefits);

        $slip->thirteenthMonth = $amount;
        $slip->periodBasic = $amount;
        $slip->gross = $amount;
        $slip->nonTaxable = $amount - $taxable;
        $slip->benefitExempt = $slip->nonTaxable;
        $slip->taxableIncome = $taxable;

        $brackets = self::annualBrackets($cfg);

        if ($brackets !== [] && $taxable > 0) {
            try {
                $slip->withholdingTax = Statutory::computeTax($taxable, $brackets);
            } catch (RuntimeException $exception) {
                $slip->warnings[] = $exception->getMessage();
            }
        }

        $slip->totalDeductions = $slip->withholdingTax;
        $slip->netPay = $slip->gross - $slip->totalDeductions;

        return $slip;
    }

    /**
     * A separation settlement: the final period's salary and statutory
     * contributions, pro-rated 13th month, leave conversion and separation pay
     * by cause.
     */
    public static function computeFinalPay(Config $cfg, Employee $employee): Payslip
    {
        $regularCfg = clone $cfg;
        $regularCfg->runType = Config::RUN_REGULAR;

        $slip = self::computeRegular($regularCfg, $employee);

        $daily = $slip->dailyRate;

        // Pro-rated 13th month: 1/12 of the year's basic salary, less any
        // 13th-month pay already released.
        $basicEarned = $employee->ytd->basicEarned + $slip->basicEarned;
        $thirteenth = Rules::thirteenthMonth($basicEarned) - $employee->ytd->thirteenthMonthPaid;

        if ($thirteenth < 0) {
            $thirteenth = 0;
        }

        $thirteenthTaxable = self::incrementalBenefitExcess($thirteenth, $employee->ytd->otherBenefits);

        // Leave conversion: unused leave days at the employee's daily rate.
        $leaveDays = $employee->leaveBalanceDays;

        if (isset($cfg->meta['leave_days'])) {
            $parsed = filter_var(trim($cfg->meta['leave_days']), FILTER_VALIDATE_FLOAT);

            if ($parsed !== false) {
                $leaveDays = (float) $parsed;
            }
        }

        if (strtolower(trim($cfg->meta['leave_conversion'] ?? '')) === 'false') {
            $leaveDays = 0;
        }

        $leave = Money::ZERO;

        if ($leaveDays > 0) {
            $leave = Money::mul($daily, $leaveDays);
        }

        [$separation, $separationTaxable] = self::separationPay($cfg, $employee, $slip);

        $gross = $slip->gross + $thirteenth + $leave + $separation;
        $taxable = $slip->taxableIncome + $thirteenthTaxable + $leave;

        if ($separationTaxable) {
            $taxable += $separation;
        }

        $tax = Money::ZERO;

        if ($taxable > 0) {
            try {
                $tax = self::withholding($cfg, $taxable, $cfg->periodsPerMonth());
            } catch (RuntimeException $exception) {
                $slip->warnings[] = $exception->getMessage();
            }
        }

        $slip->thirteenthMonth = $thirteenth;
        $slip->leaveConversion = $leave;
        $slip->separationPay = $separation;
        $slip->gross = $gross;
        $slip->taxableIncome = $taxable;

        $benefitExempt = $thirteenth - $thirteenthTaxable;
        $slip->nonTaxable += $benefitExempt;
        $slip->benefitExempt += $benefitExempt;

        if (! $separationTaxable) {
            $slip->nonTaxable += $separation;
        }

        $slip->totalDeductions = $slip->totalDeductions - $slip->withholdingTax + $tax;
        $slip->withholdingTax = $tax;
        $slip->netPay = $slip->gross - $slip->totalDeductions;

        if ($slip->netPay < 0) {
            $slip->warnings[] = sprintf(
                'deductions exceed earnings by %s',
                Money::format($slip->totalDeductions - $slip->gross),
            );
            $slip->netPay = 0;
        }

        return $slip;
    }

    /**
     * Compare the tax withheld during the year with the tax on the annualised
     * taxable income and return the difference as a positive (additional
     * withholding) or negative (refund) adjustment.
     */
    public static function computeAnnualAdjustment(Config $cfg, Employee $employee): Payslip
    {
        $slip = new Payslip(
            employeeID: $employee->id,
            employeeNo: $employee->number,
            employeeName: $employee->name,
            basicMonthly: $employee->monthlyBasic,
        );

        $brackets = self::annualBrackets($cfg);

        if ($brackets === [] || $employee->ytd->taxableIncome <= 0) {
            $slip->warnings[] = 'no taxable income recorded for the year';

            return $slip;
        }

        $monthlyEquivalent = Money::mul($employee->ytd->taxableIncome, 1.0 / 12.0);
        $monthlyTax = Statutory::computeTax($monthlyEquivalent, $brackets);

        $annualTax = Money::mul($monthlyTax, 12);
        $adjustment = $annualTax - $employee->ytd->taxWithheld;

        $slip->annualAdjustment = $adjustment;
        $slip->withholdingTax = $adjustment;
        $slip->totalDeductions = $adjustment;
        $slip->netPay = Money::mul($adjustment, -1);
        $slip->nonTaxable = 0;

        return $slip;
    }

    /**
     * The portion of a benefit that exceeds the ₱90,000 annual exclusion after
     * other benefits already received this year.
     */
    public static function incrementalBenefitExcess(int $amount, int $otherBenefits): int
    {
        if ($amount <= 0) {
            return 0;
        }

        $priorExcess = Money::max(0, $otherBenefits - Rules::THIRTEENTH_MONTH_EXEMPTION);
        $totalExcess = Money::max(0, $amount + $otherBenefits - Rules::THIRTEENTH_MONTH_EXEMPTION);

        return Money::max(0, $totalExcess - $priorExcess);
    }

    /**
     * @return list<TaxBracket>
     */
    private static function annualBrackets(Config $cfg): array
    {
        if ($cfg->annualTaxBrackets !== []) {
            return $cfg->annualTaxBrackets;
        }

        return Statutory::trainTaxBrackets(Config::FREQUENCY_MONTHLY);
    }

    /**
     * @return array{0: int, 1: bool} the amount and whether it is taxable
     */
    private static function separationPay(Config $cfg, Employee $employee, Payslip $slip): array
    {
        if (isset($cfg->meta['separation_pay']) && trim($cfg->meta['separation_pay']) !== '') {
            $amount = Money::fromPesosString($cfg->meta['separation_pay']);

            return [$amount, self::separationIsTaxable($cfg->meta['separation_reason'] ?? '')];
        }

        $reason = strtolower(trim($cfg->meta['separation_reason'] ?? ''));

        $monthsPerYear = match ($reason) {
            'retrenchment', 'redundancy', 'closure', 'business_closure' => 1.0,
            'authorised_cause', 'authorized_cause', 'redundancy_half' => 0.5,
            default => 0.0,
        };

        if ($monthsPerYear === 0.0) {
            return [0, false];
        }

        $years = self::yearsOfService($employee);

        if (isset($cfg->meta['years_of_service'])) {
            $parsed = filter_var(trim($cfg->meta['years_of_service']), FILTER_VALIDATE_FLOAT);

            if ($parsed !== false) {
                $years = (float) $parsed;
            }
        }

        if ($years <= 0) {
            return [0, false];
        }

        $amount = Money::mul($slip->basicMonthly, $years * $monthsPerYear);

        return [$amount, self::separationIsTaxable($reason)];
    }

    private static function separationIsTaxable(string $reason): bool
    {
        return ! in_array(strtolower(trim($reason)), [
            'retrenchment',
            'redundancy',
            'closure',
            'business_closure',
            'illness',
            'death',
            'disability',
            'authorised_cause',
            'authorized_cause',
        ], true);
    }

    private static function yearsOfService(Employee $employee): float
    {
        try {
            $start = new DateTimeImmutable($employee->hiredOn);
        } catch (\Exception) {
            return 0;
        }

        if ($employee->hiredOn === '') {
            return 0;
        }

        $end = new DateTimeImmutable;

        if ($employee->separatedOn !== '') {
            try {
                $end = new DateTimeImmutable($employee->separatedOn);
            } catch (\Exception) {
                // Keep today as the end date.
            }
        }

        $days = ($end->getTimestamp() - $start->getTimestamp()) / 86400;
        $years = $days / 365.25;

        return $years < 0 ? 0 : $years;
    }

    /**
     * The multiplier for the day, honouring the employee's benefit switches.
     *
     * @return array{0: float, 1: float, 2: string|null} day rate, premium only, warning
     */
    private static function entitledDayRate(BenefitFlags $flags, string $dayType, string $date): array
    {
        $multiplier = Rules::dayMultiplier($dayType);
        $entitled = true;

        if (Rules::isRestDay($dayType)) {
            $entitled = $flags->restDayPay;
        } elseif (Rules::isRegularHoliday($dayType) && Rules::isSpecialHoliday($dayType)) {
            $entitled = $flags->regularHolidaySpecialHolidayPay;
        } elseif (Rules::isRegularHoliday($dayType)) {
            $entitled = $multiplier >= 3.0 ? $flags->premiumHolidayPay : $flags->regularHolidayPay;
        } elseif (Rules::isSpecialHoliday($dayType)) {
            $entitled = $flags->specialHolidayPay;
        }

        if (! $entitled) {
            return [1.0, 0.0, sprintf('%s: premium for %s not applied (not entitled)', $date, $dayType)];
        }

        return [$multiplier, $multiplier - 1, null];
    }

    /**
     * @param  list<TimesheetLine>  $lines
     */
    private static function workedOn(array $lines, string $date): bool
    {
        foreach ($lines as $line) {
            if ($line->date !== $date || $line->absent || Rules::isUnpaidDay($line->dayType)) {
                continue;
            }

            if ($line->hours > 0 || $line->overtime > 0 || $line->paidLeave || Rules::isPaidDay($line->dayType)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The Article 94 condition: present or on paid leave on the work day
     * immediately preceding the holiday.
     *
     * @param  list<TimesheetLine>  $lines
     */
    private static function eligibleForUnworkedHoliday(array $lines, string $date, bool $payWithoutTimesheet): bool
    {
        if ($lines === []) {
            return $payWithoutTimesheet;
        }

        $preceding = self::previousDate($date);

        if ($preceding === '') {
            return true;
        }

        $found = false;

        foreach ($lines as $line) {
            if ($line->date !== $preceding) {
                continue;
            }

            $found = true;

            if ($line->absent) {
                return false;
            }

            if ($line->paidLeave || Rules::isPaidDay($line->dayType) || $line->hours > 0 || $line->overtime > 0) {
                return true;
            }

            if (Rules::isUnpaidDay($line->dayType)) {
                return false;
            }
        }

        // No record for the preceding day: assume present when attendance is
        // recorded for the period.
        return ! $found;
    }

    private static function previousDate(string $date): string
    {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);

        if ($parsed === false) {
            return '';
        }

        return $parsed->modify('-1 day')->format('Y-m-d');
    }

    /**
     * The portion of an allowance that is not taxable. De minimis benefits are
     * capped at their statutory ceiling and basis; anything above is taxable.
     */
    private static function nonTaxablePart(Config $cfg, Employee $employee, Earning $earning, int $periods): int
    {
        if ($earning->taxable) {
            return 0;
        }

        if ($earning->kind === Earning::KIND_ALLOWANCE && $employee->benefits->deMinimis) {
            $benefit = self::deMinimisBenefit($cfg, $earning);

            if ($benefit !== null) {
                return self::deMinimisExemption($cfg, $employee, $earning, $benefit, $periods);
            }
        }

        return $earning->amount;
    }

    private static function deMinimisBenefit(Config $cfg, Earning $earning): ?DeMinimisBenefit
    {
        foreach ($cfg->deMinimis as $benefit) {
            if (strcasecmp($benefit->code, $earning->code) === 0
                || strcasecmp($benefit->code, $earning->name) === 0
                || strcasecmp($benefit->code, self::slug($earning->name)) === 0) {
                return $benefit;
            }
        }

        return null;
    }

    private static function deMinimisExemption(
        Config $cfg,
        Employee $employee,
        Earning $earning,
        DeMinimisBenefit $benefit,
        int $periods,
    ): int {
        $periodsPerMonth = $periods > 0 ? $periods : 1;

        return match ($benefit->basis) {
            DeMinimisBenefit::BASIS_ANNUAL => Money::min($earning->amount, self::split($benefit->ceiling, $periodsPerMonth * 12)),
            DeMinimisBenefit::BASIS_SEMESTER => Money::min(
                $earning->amount,
                self::firstPart(self::split($benefit->ceiling, 6), $periodsPerMonth),
            ),
            DeMinimisBenefit::BASIS_HOURLY_OT => self::deMinimisHourlyOtExemption($cfg, $employee, $earning),
            default => Money::min($earning->amount, self::firstPart($benefit->ceiling, $periodsPerMonth)),
        };
    }

    /**
     * The meal-allowance ceiling for overtime work: 25% of the hourly rate for
     * each overtime hour in the period.
     */
    private static function deMinimisHourlyOtExemption(Config $cfg, Employee $employee, Earning $earning): int
    {
        $daysPerYear = $cfg->daysPerYear > 0 ? $cfg->daysPerYear : 261;
        $hoursPerDay = $cfg->hoursPerDay > 0 ? $cfg->hoursPerDay : 8;
        $hourly = Rules::hourlyRate($employee->monthlyBasic, $daysPerYear, $hoursPerDay);
        $ceiling = Money::mul($hourly, self::overtimeHours($employee->timesheets) * 0.25);

        return Money::min($earning->amount, $ceiling);
    }

    /**
     * @param  list<TimesheetLine>  $lines
     */
    private static function overtimeHours(array $lines): float
    {
        $total = 0.0;

        foreach ($lines as $line) {
            $total += $line->overtime;
        }

        return $total;
    }

    private static function slug(string $value): string
    {
        return str_replace(['-', ' '], '_', strtolower(trim($value)));
    }

    /**
     * Select what a statutory contribution is assessed on.
     */
    private static function contributionBase(string $base, Employee $employee, Payslip $slip, int $periods): int
    {
        return match (strtolower(trim($base))) {
            'gross' => Money::mul($slip->gross, (float) $periods),
            'period_gross' => $slip->gross,
            default => $employee->monthlyBasic,
        };
    }

    private static function minimumWagePay(Config $cfg, Employee $employee, Payslip $slip, int $daysPerYear, int $periods): int
    {
        $daily = $employee->minimumWage;

        if ($daily === 0) {
            $daily = $cfg->minimumWage;
        }

        if ($daily === 0) {
            return 0;
        }

        if (strtolower(trim($employee->paymentType)) === Employee::PAYMENT_MONTHLY) {
            $monthlyMinimum = Money::mul($daily, $daysPerYear / 12);

            return self::split($monthlyMinimum, $periods);
        }

        // Daily-paid staff earn the minimum wage for each paid day in the
        // period. Cap the exemption at the regular pay actually earned (which
        // includes paid unworked holidays) so an overpayment above the
        // statutory wage is not exempt.
        $statutory = Money::mul($daily, self::paidDays($cfg, $employee));

        if ($slip->regularPay < $statutory) {
            return $slip->regularPay;
        }

        return $statutory;
    }

    /**
     * Count the days that earn the daily minimum wage: attended days, paid
     * leave, and paid unworked regular holidays.
     */
    private static function paidDays(Config $cfg, Employee $employee): float
    {
        $days = 0.0;

        foreach ($employee->timesheets as $line) {
            if ($line->absent) {
                continue;
            }

            if ($line->paidLeave || Rules::isPaidDay($line->dayType)) {
                $days++;

                continue;
            }

            if (Rules::isUnpaidDay($line->dayType)) {
                continue;
            }

            if ($line->hours > 0) {
                $days++;
            }
        }

        if ($cfg->payUnworkedRegularHolidays) {
            foreach ($cfg->holidays as $holiday) {
                if (! Rules::isRegularHoliday($holiday->code) || self::workedOn($employee->timesheets, $holiday->date)) {
                    continue;
                }

                if (! self::eligibleForUnworkedHoliday($employee->timesheets, $holiday->date, $cfg->payWithoutTimesheet)) {
                    continue;
                }

                if (! $employee->benefits->regularHolidayPay) {
                    continue;
                }

                $days++;
            }
        }

        return $days;
    }

    /**
     * The tax for the period. When every contribution is taken every pay, the
     * published tables for the frequency are the operative reference. With a
     * non-uniform schedule (first/last pay only) the tax is computed on the
     * monthly equivalent and allocated, so the two cut-offs reconcile to the
     * monthly table.
     */
    private static function withholding(Config $cfg, int $taxable, int $periods): int
    {
        if (self::uniformSchedules($cfg)) {
            return Statutory::computeTax($taxable, $cfg->taxBrackets);
        }

        $brackets = $cfg->annualTaxBrackets !== []
            ? $cfg->annualTaxBrackets
            : Statutory::trainTaxBrackets(Config::FREQUENCY_MONTHLY);

        $monthlyTax = Statutory::computeTax(Money::mul($taxable, (float) $periods), $brackets);

        return self::firstPart($monthlyTax, $periods);
    }

    private static function uniformSchedules(Config $cfg): bool
    {
        return $cfg->sssSchedule === Statutory::SCHEDULE_EVERY_PAY
            && $cfg->philHealthSchedule === Statutory::SCHEDULE_EVERY_PAY
            && $cfg->pagIbigSchedule === Statutory::SCHEDULE_EVERY_PAY
            && $cfg->taxSchedule === Statutory::SCHEDULE_EVERY_PAY;
    }

    private static function split(int $amount, int $divisor): int
    {
        if ($divisor <= 1) {
            return $amount;
        }

        return Rules::roundHalfUp($amount / $divisor);
    }

    private static function firstPart(int $amount, int $parts): int
    {
        if ($parts <= 1) {
            return $amount;
        }

        return Money::splitTruncating($amount, $parts)[0];
    }

    private static function periodDays(Config $cfg, int $daysPerYear): int
    {
        $days = intdiv($daysPerYear, 12);

        if ($cfg->frequency === Config::FREQUENCY_SEMI_MONTHLY) {
            return intdiv($days, 2);
        }

        return $days;
    }
}
