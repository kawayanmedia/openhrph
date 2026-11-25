<?php

namespace App\Services\Payroll\Computation;

use App\Jurisdictions\Contracts\PayrollPolicy;
use App\Jurisdictions\Philippines\Statutory\Bir\WithholdingTaxService;
use App\Jurisdictions\Philippines\Statutory\Bir\WithholdingTaxTable;
use App\Jurisdictions\Philippines\Statutory\ContributionSchedule;
use App\Jurisdictions\Philippines\Statutory\Generic\GenericContributionService;
use App\Jurisdictions\Philippines\Statutory\PagIbig\PagIbigContributionService;
use App\Jurisdictions\Philippines\Statutory\PayFrequency;
use App\Jurisdictions\Philippines\Statutory\PhilHealth\PhilHealthContributionService;
use App\Jurisdictions\Support\PayrollPolicyData;
use App\Support\Money;
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
        return match ($cfg->runType |> trim(...) |> strtolower(...)) {
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

        $monthlyPaid = ($employee->paymentType |> trim(...) |> strtolower(...)) === Employee::PAYMENT_MONTHLY;

        $daily = $employee->dailyRate;

        if ($daily === 0) {
            $daily = Rules::dailyRate($employee->monthlyBasic, $daysPerYear);
        }

        // Daily-paid employees are contracted at a daily rate. Deriving their
        // hourly rate from the monthly field would treat the daily rate as a
        // month's pay and understate the wage by the days-per-year divisor.
        $hourly = ! $monthlyPaid && $employee->dailyRate > 0
            ? Rules::hourlyRateFromDaily($employee->dailyRate, $hoursPerDay)
            : Rules::hourlyRate($employee->monthlyBasic, $daysPerYear, $hoursPerDay);

        $slip->dailyRate = $daily;
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
                $slip->basicEarned = 0;
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

            $leaveFraction = $line->leaveFraction > 0 && $line->leaveFraction <= 1
                ? $line->leaveFraction
                : 1.0;

            if ($line->paidLeave || Rules::isPaidDay($line->dayType)) {
                if (! $monthlyPaid) {
                    $paidLeave = Money::mul($daily, $leaveFraction);
                    $slip->regularPay += $paidLeave;
                    $slip->basicEarned += $paidLeave;
                    $slip->regularHours += (float) $hoursPerDay * $leaveFraction;
                }

                continue;
            }

            if (Rules::isUnpaidDay($line->dayType)) {
                if ($monthlyPaid) {
                    $unpaid = Money::mul($daily, $leaveFraction);
                    $slip->regularPay -= $unpaid;
                    $slip->basicEarned -= $unpaid;
                }

                continue;
            }

            if ($line->hours <= 0 && $line->overtime <= 0 && $line->night <= 0 && $line->nightOT <= 0) {
                continue;
            }

            [$dayRate, $premiumOnly, $note] = self::entitledDayRate($employee->benefits, $line->dayType, $line->date, $cfg->policy);

            if ($note !== null) {
                $slip->warnings[] = $note;
            }

            $hours = $line->hours;

            if ($cfg->computeUndertime && $line->undertime > 0) {
                $paidHours = max(0.0, $hours - $line->undertime);
                $undertimePay = Money::mul($hourly, $line->undertime * $dayRate);

                if ($monthlyPaid) {
                    // The fixed period basic still pays the day, so remove the
                    // undertime hours from it.
                    $slip->regularPay -= $undertimePay;
                    $slip->basicEarned -= $undertimePay;
                }

                $slip->undertimeHours += $line->undertime;
                $slip->undertimePay += $undertimePay;
                $hours = $paidHours;
            }

            $regularPay = Money::mul($hourly, $hours * $dayRate);

            if ($monthlyPaid) {
                $regularPay = Money::mul($hourly, $hours * $premiumOnly);
            }

            // Article 87 requires the premium-day rate plus at least 30% for
            // work beyond eight hours on a rest day or holiday.
            $overtimeRate = $dayRate * Rules::overtimeMultiplierFor($dayRate, $cfg->policy);

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
                $nightRate = $dayRate * $cfg->policy->nightDifferentialRate();
            } elseif ($line->night > 0) {
                $slip->warnings[] = sprintf('%s: night differential not applied (not entitled)', $line->date);
            }

            $nightPay = Money::mul($hourly, $line->night * $nightRate);

            $nightOTPay = Money::ZERO;

            if ($line->nightOT > 0) {
                $base = $dayRate * Rules::overtimeMultiplierFor($dayRate, $cfg->policy);

                if (! $employee->benefits->overtimePay) {
                    $base = $dayRate;
                }

                $differential = $employee->benefits->nightDifferential ? $cfg->policy->nightDifferentialRate() : 0.0;

                $nightOTPay = Money::mul($hourly, $line->nightOT * $base * $differential);
            }

            if (! $monthlyPaid) {
                $slip->basicEarned += Money::mul($hourly, $hours);
            }

            $slip->regularPay += $regularPay;
            $slip->overtimePay += $overtimePay;
            $slip->nightPay += $nightPay;
            $slip->nightOTPay += $nightOTPay;

            $slip->regularHours += $hours;
            $slip->overtimeHours += $line->overtime;
            $slip->nightHours += $line->night + $line->nightOT;

            // Report only the premium component (already included in the
            // totals above) so the payslip does not show base pay twice.
            $premiumPay = $regularPay;

            if (! $monthlyPaid) {
                $premiumPay = $regularPay - Money::mul($hourly, $hours);
            }

            if (Rules::isHoliday($line->dayType)) {
                $slip->holidayPay += $premiumPay;
            } elseif (Rules::isRestDay($line->dayType)) {
                $slip->restDayPay += $premiumPay;
            }
        }

        $hasPaidLeave = false;
        foreach ($employee->timesheets as $line) {
            if ($line->paidLeave || Rules::isPaidDay($line->dayType)) {
                $hasPaidLeave = true;
                break;
            }
        }

        if ($employee->timesheets !== []
            && $slip->regularHours === 0.0
            && $slip->overtimeHours === 0.0
            && $slip->nightHours === 0.0
            && $slip->absentDays === 0.0
            && $slip->undertimeHours === 0.0
            && ! $hasPaidLeave) {
            $slip->warnings[] = 'timesheets have no recorded hours; verify attendance before relying on this payroll';
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
            $slip->nonTaxable += self::nonTaxablePart($cfg, $employee, $earning, $slip);
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

        $generic = ((string) $cfg->statutoryDriver |> trim(...) |> strtolower(...)) !== 'ph';
        $genericEmployeeTotal = 0;
        $genericEmployerTotal = 0;
        $genericTaxableReduction = 0;

        if (! $generic) {
            // Each agency has its own contribution base so a company can follow
            // the agency circular without code changes.
            $philHealthBase = self::contributionBase($cfg->philHealthBase, $employee, $slip, $periods);
            $pagIbigBase = self::contributionBase($cfg->pagIbigBase, $employee, $slip, $periods);

            // Government agencies remit GSIS and private establishments remit
            // SSS; the scheme is mutually exclusive and owns its own schedule.
            $scheme = $cfg->socialInsurance();
            $socialBase = self::contributionBase(
                $scheme->code() === 'gsis' ? $cfg->gsisBase : $cfg->sssBase,
                $employee,
                $slip,
                $periods,
            );
            $divisor = self::divisor($cfg, $cfg->sssSchedule);

            if ($divisor > 0) {
                try {
                    $split = $scheme->compute($socialBase);

                    if ($scheme->code() === 'gsis') {
                        $slip->gsisEmployee = self::firstPart($split->employee, $divisor);
                        $slip->gsisEmployer = self::firstPart($split->employer, $divisor);
                        // The total is the sum of the parts so the payroll
                        // columns always reconcile to the remittance amount.
                        $slip->gsisTotal = $slip->gsisEmployee + $slip->gsisEmployer;
                    } else {
                        $slip->sssEmployee = self::firstPart($split->employee, $divisor);
                        $slip->sssProvident = self::firstPart($split->providentEmployee, $divisor);
                        $slip->sssEmployer = self::firstPart($split->employer, $divisor);
                        $slip->sssProvidentER = self::firstPart($split->providentEmployer, $divisor);
                        $slip->sssEC = self::firstPart($split->employerEc, $divisor);
                        $slip->sssTotal = $slip->sssEmployee
                            + $slip->sssProvident
                            + $slip->sssEmployer
                            + $slip->sssProvidentER
                            + $slip->sssEC;
                    }
                } catch (RuntimeException $exception) {
                    $slip->warnings[] = $exception->getMessage();
                }
            }

            $divisor = self::divisor($cfg, $cfg->philHealthSchedule);

            if ($divisor > 0) {
                $philHealth = (new PhilHealthContributionService($cfg->philHealth))->compute($philHealthBase);
                $slip->philHealthEmployee = self::firstPart($philHealth->employee, $divisor);
                $slip->philHealthEmployer = self::firstPart($philHealth->employer, $divisor);
            }

            $divisor = self::divisor($cfg, $cfg->pagIbigSchedule);

            if ($divisor > 0) {
                $pagIbig = (new PagIbigContributionService($cfg->pagIbig))->compute($pagIbigBase);
                $slip->pagIbigEmployee = self::firstPart($pagIbig->employee, $divisor);
                $slip->pagIbigEmployer = self::firstPart($pagIbig->employer, $divisor);
            }
        }

        // Minimum wage earners are exempt on the statutory minimum wage and on
        // the holiday, overtime and night-shift pay attributable to it
        // (RA 9504 as amended by RA 10963).
        $minWageExemption = Money::ZERO;

        if ($cfg->policy->minimumWageTaxExempt() && $employee->isMinimumWage && $employee->minimumWage > 0) {
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

        // A generic country computes every non-tax component before taxable
        // income is finalised, so a component flagged as tax-deductible reduces
        // the base of the tax component that follows it.
        if ($generic) {
            self::computeGenericComponents(
                $cfg,
                $employee,
                $slip,
                $periods,
                $genericEmployeeTotal,
                $genericEmployerTotal,
                $genericTaxableReduction,
                false,
                $minWageExemption,
            );
        }

        $voluntaryTaxReduction = 0;

        foreach ($employee->deductions as $deduction) {
            $amount = self::deductionAmount($employee, $slip, $periods, $deduction, $minWageExemption);
            $slip->otherDeductions += $amount;

            if ($deduction->reducesTaxableIncome) {
                $voluntaryTaxReduction += $amount;
            }

            $slip->deductionItems[] = new DeductionLine(
                code: $deduction->code !== '' ? $deduction->code : 'deduction_'.count($slip->deductionItems),
                name: $deduction->name,
                category: 'deduction',
                base: 0,
                employee: $amount,
                employer: 0,
                reducesTaxableIncome: $deduction->reducesTaxableIncome,
                source: DeductionLine::SOURCE_DEDUCTION,
                sortOrder: 500 + count($slip->deductionItems),
            );
        }

        foreach ($employee->loans as $loan) {
            $slip->loans += $loan->amount;
            $slip->otherDeductions += $loan->amount;

            $slip->deductionItems[] = new DeductionLine(
                code: $loan->code !== '' ? $loan->code : 'loan_'.count($slip->deductionItems),
                name: $loan->name,
                category: 'loan',
                employee: $loan->amount,
                source: DeductionLine::SOURCE_LOAN,
                sortOrder: 800 + count($slip->deductionItems),
            );
        }

        // Mandatory employee contributions and non-taxable benefits are exempt.
        $slip->taxableIncome = $slip->gross
            - $slip->nonTaxable
            - $minWageExemption
            - $slip->sssEmployee
            - $slip->sssProvident
            - $slip->philHealthEmployee
            - $slip->pagIbigEmployee
            - $slip->gsisEmployee
            - $genericTaxableReduction
            - $voluntaryTaxReduction;

        if ($slip->taxableIncome < 0) {
            $slip->taxableIncome = 0;
        }

        if ($generic) {
            self::computeGenericComponents(
                $cfg,
                $employee,
                $slip,
                $periods,
                $genericEmployeeTotal,
                $genericEmployerTotal,
                $genericTaxableReduction,
                true,
                $minWageExemption,
            );
        } elseif (self::divisor($cfg, $cfg->taxSchedule) > 0) {
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
            + $slip->gsisEmployee
            + $genericEmployeeTotal
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
            + $slip->pagIbigEmployer
            + $slip->gsisEmployer
            + $genericEmployerTotal;

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

        $generic = self::isGeneric($cfg);

        // The 90,000 exclusion is a Philippine rule; a generic country taxes
        // the benefit according to its own configured tax component.
        $taxable = $generic ? $amount : self::incrementalBenefitExcess($amount, $employee->ytd->otherBenefits, $cfg->policy);

        $slip->thirteenthMonth = $amount;
        $slip->periodBasic = $amount;
        $slip->gross = $amount;
        $slip->nonTaxable = $amount - $taxable;
        $slip->benefitExempt = $slip->nonTaxable;
        $slip->taxableIncome = $taxable;

        if ($generic) {
            $slip->withholdingTax = self::genericTax($cfg, $taxable, $slip);
        } else {
            $brackets = self::annualBrackets($cfg);

            if ($brackets !== [] && $taxable > 0) {
                try {
                    $slip->withholdingTax = (new WithholdingTaxService($brackets))->compute($taxable);
                } catch (RuntimeException $exception) {
                    $slip->warnings[] = $exception->getMessage();
                }
            }
        }

        $slip->totalDeductions = $slip->withholdingTax;
        $slip->netPay = $slip->gross - $slip->totalDeductions;
        self::appendGenericTaxLine($cfg, $slip);

        return $slip;
    }

    /**
     * A separation settlement: the final period's salary and statutory
     * contributions, pro-rated 13th month, leave conversion and separation pay
     * by cause.
     */
    public static function computeFinalPay(Config $cfg, Employee $employee): Payslip
    {
        $regularCfg = clone ($cfg, ['runType' => Config::RUN_REGULAR]);

        $slip = self::computeRegular($regularCfg, $employee);

        $daily = $slip->dailyRate;

        // Pro-rated 13th month: 1/12 of the year's basic salary, less any
        // 13th-month pay already released.
        $basicEarned = $employee->ytd->basicEarned + $slip->basicEarned;
        $thirteenth = Rules::thirteenthMonth($basicEarned) - $employee->ytd->thirteenthMonthPaid;

        if ($thirteenth < 0) {
            $thirteenth = 0;
        }

        $generic = self::isGeneric($cfg);
        $thirteenthTaxable = $generic ? $thirteenth : self::incrementalBenefitExcess($thirteenth, $employee->ytd->otherBenefits, $cfg->policy);

        // Leave conversion: unused leave days at the employee's daily rate.
        $leaveDays = $employee->leaveBalanceDays;

        if (isset($cfg->meta['leave_days'])) {
            $parsed = filter_var(trim($cfg->meta['leave_days']), FILTER_VALIDATE_FLOAT);

            if ($parsed !== false) {
                $leaveDays = (float) $parsed;
            }
        }

        if ((($cfg->meta['leave_conversion'] ?? '') |> trim(...) |> strtolower(...)) === 'false') {
            $leaveDays = 0;
        }

        $leave = Money::ZERO;
        $leaveExempt = 0;

        if ($leaveDays > 0) {
            $leave = Money::mul($daily, $leaveDays);

            // RR 29-2025: monetized unused VL of private employees is
            // non-taxable up to 12 days; government VL/SL is non-taxable.
            $exemptDays = $cfg->leaveConversionTaxableDays === null
                ? $leaveDays
                : min($leaveDays, $cfg->leaveConversionTaxableDays);
            $leaveExempt = Money::mul($daily, $exemptDays);
            $leaveTaxable = $leave - $leaveExempt;
        } else {
            $leaveTaxable = 0;
        }

        [$separation, $separationTaxable] = self::separationPay($cfg, $employee, $slip);

        $gross = $slip->gross + $thirteenth + $leave + $separation;
        $taxable = $slip->taxableIncome + $thirteenthTaxable + $leaveTaxable;
        $slip->nonTaxable += $leaveExempt;

        if ($separationTaxable) {
            $taxable += $separation;
        }

        $tax = Money::ZERO;

        if ($taxable > 0) {
            try {
                $tax = $generic
                    ? self::genericTax($cfg, $taxable, $slip)
                    : self::withholding($cfg, $taxable, $cfg->periodsPerMonth());
            } catch (RuntimeException $exception) {
                $slip->warnings[] = $exception->getMessage();
            }
        }

        $slip->thirteenthMonth = $thirteenth;
        $slip->leaveConversion = $leave;
        $slip->separationPay = $separation;
        $slip->gross = $gross;
        $slip->taxableIncome = $taxable;

        $benefitExempt = $generic ? 0 : $thirteenth - $thirteenthTaxable;
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

        self::appendGenericTaxLine($cfg, $slip);

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

        $generic = self::isGeneric($cfg);
        $brackets = self::annualBrackets($cfg);

        if ((! $generic && $brackets === []) || $employee->ytd->taxableIncome <= 0) {
            $slip->warnings[] = 'no taxable income recorded for the year';

            return $slip;
        }

        $monthlyEquivalent = Money::mul($employee->ytd->taxableIncome, 1.0 / 12.0);
        $monthlyTax = $generic
            ? self::genericTax($cfg, $monthlyEquivalent, $slip)
            : (new WithholdingTaxService($brackets))->compute($monthlyEquivalent);

        $annualTax = Money::mul($monthlyTax, 12);
        $adjustment = $annualTax - $employee->ytd->taxWithheld;

        $slip->annualAdjustment = $adjustment;
        $slip->withholdingTax = $adjustment;
        $slip->totalDeductions = $adjustment;
        $slip->netPay = Money::mul($adjustment, -1);
        $slip->nonTaxable = 0;
        self::appendGenericTaxLine($cfg, $slip);

        return $slip;
    }

    /**
     * The portion of a benefit that exceeds the ₱90,000 annual exclusion after
     * other benefits already received this year.
     */
    public static function incrementalBenefitExcess(int $amount, int $otherBenefits, ?PayrollPolicy $policy = null): int
    {
        if ($amount <= 0) {
            return 0;
        }

        $ceiling = ($policy ?? new PayrollPolicyData)->thirteenthMonthExemptCentavos();
        $priorExcess = Money::max(0, $otherBenefits - $ceiling);
        $totalExcess = Money::max(0, $amount + $otherBenefits - $ceiling);

        return Money::max(0, $totalExcess - $priorExcess);
    }

    private static function appendGenericTaxLine(Config $cfg, Payslip $slip): void
    {
        if (! self::isGeneric($cfg) || $slip->withholdingTax === 0) {
            return;
        }

        foreach ($cfg->components as $component) {
            if (strtolower((string) ($component['category'] ?? '')) !== 'tax') {
                continue;
            }

            $slip->deductionLines[] = new DeductionLine(
                code: (string) ($component['code'] ?? 'withholding_tax'),
                name: (string) ($component['name'] ?? $component['code'] ?? 'Tax'),
                category: 'tax',
                employee: $slip->withholdingTax,
                source: DeductionLine::SOURCE_STATUTORY,
                sortOrder: (int) ($component['sort_order'] ?? 900),
            );

            return;
        }
    }

    private static function isGeneric(Config $cfg): bool
    {
        $driver = $cfg->statutoryDriver |> trim(...) |> strtolower(...);

        return $driver !== '' && $driver !== 'ph';
    }

    /**
     * Apply the generic country's tax component to a one-off taxable amount.
     */
    private static function genericTax(Config $cfg, int $amount, Payslip $slip): int
    {
        foreach ($cfg->components as $component) {
            if (strtolower((string) ($component['category'] ?? '')) !== 'tax') {
                continue;
            }

            $result = GenericContributionService::compute($component, max(0, $amount));

            return $result['employee'];
        }

        $slip->warnings[] = 'no tax component is configured for this country';

        return 0;
    }

    /**
     * @return list<TaxBracket>
     */
    private static function annualBrackets(Config $cfg): array
    {
        if ($cfg->annualTaxBrackets !== []) {
            return $cfg->annualTaxBrackets;
        }

        if (($cfg->statutoryDriver |> trim(...) |> strtolower(...)) !== 'ph') {
            return [];
        }

        return WithholdingTaxTable::train(PayFrequency::MONTHLY);
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

        $reason = ($cfg->meta['separation_reason'] ?? '') |> trim(...) |> strtolower(...);

        if (in_array($reason, ['retirement', 'ra_7641'], true)) {
            return self::retirementPay($cfg, $employee, $slip);
        }

        $oneMonthMinimum = false;

        $monthsPerYear = match ($reason) {
            // Labor Code Art. 298: one month pay or one month per year of
            // service, whichever is higher.
            'retrenchment', 'redundancy', 'closure', 'business_closure',
            'authorised_cause', 'authorized_cause' => 1.0,
            // Labor Code Art. 299: one-half month pay per year of service.
            'illness', 'disease', 'sick' => 0.5,
            default => 0.0,
        };

        if ($monthsPerYear === 0.0) {
            return [0, false];
        }

        $oneMonthMinimum = in_array($reason, [
            'retrenchment', 'redundancy', 'closure', 'business_closure',
            'authorised_cause', 'authorized_cause',
        ], true);

        $years = self::yearsOfService($employee);

        if (isset($cfg->meta['years_of_service'])) {
            $parsed = filter_var(trim($cfg->meta['years_of_service']), FILTER_VALIDATE_FLOAT);

            if ($parsed !== false) {
                $years = (float) $parsed;
            }
        }

        if ($years < 0) {
            return [0, false];
        }

        $monthly = self::separationMonthlyPay($cfg, $employee, $slip);
        $amount = Money::mul($monthly, $years * $monthsPerYear);

        if ($oneMonthMinimum && $amount < $monthly) {
            $amount = $monthly;
        }

        return [$amount, self::separationIsTaxable($reason)];
    }

    /**
     * RA 7641 retirement pay: 22.5 days for every year of service, after at
     * least five years.
     *
     * @return array{0: int, 1: bool}
     */
    private static function retirementPay(Config $cfg, Employee $employee, Payslip $slip): array
    {
        $years = self::yearsOfService($employee);

        if (isset($cfg->meta['years_of_service'])) {
            $parsed = filter_var(trim($cfg->meta['years_of_service']), FILTER_VALIDATE_FLOAT);

            if ($parsed !== false) {
                $years = (float) $parsed;
            }
        }

        if ($years < 5) {
            $slip->warnings[] = 'retirement pay requires at least five years of service';

            return [0, false];
        }

        $daily = $slip->dailyRate > 0
            ? $slip->dailyRate
            : (int) round(self::separationMonthlyPay($cfg, $employee, $slip) / 22);

        return [Money::mul($daily, 22.5 * $years), false];
    }

    /**
     * The monthly rate used for separation pay. A daily-paid employee stores a
     * daily rate in the monthly field, so annualise it first.
     */
    private static function separationMonthlyPay(Config $cfg, Employee $employee, Payslip $slip): int
    {
        if (($employee->paymentType |> trim(...) |> strtolower(...)) === Employee::PAYMENT_DAILY && $employee->dailyRate > 0) {
            $days = $cfg->daysPerYear > 0 ? $cfg->daysPerYear : 261;

            return Money::mul($employee->dailyRate, $days / 12);
        }

        return $slip->basicMonthly;
    }

    public static function separationIsTaxable(string $reason): bool
    {
        return ! in_array($reason |> trim(...) |> strtolower(...), [
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

    /**
     * Whole years of service; a fraction of at least six months counts as one
     * whole year (Labor Code Arts. 298-299).
     */
    private static function yearsOfService(Employee $employee): float
    {
        if ($employee->hiredOn === '') {
            return 0;
        }

        try {
            $start = new DateTimeImmutable($employee->hiredOn);
        } catch (\Exception) {
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

        if ($end < $start) {
            return 0;
        }

        $years = (int) $end->format('Y') - (int) $start->format('Y');
        $anniversary = $start->modify('+'.$years.' years');

        if ($anniversary > $end) {
            $years--;
            $anniversary = $start->modify('+'.$years.' years');
        }

        if ($end >= $anniversary->modify('+6 months')) {
            $years++;
        }

        return max(0, (float) $years);
    }

    /**
     * The multiplier for the day, honouring the employee's benefit switches.
     *
     * @return array{0: float, 1: float, 2: string|null} day rate, premium only, warning
     */
    private static function entitledDayRate(BenefitFlags $flags, string $dayType, string $date, ?PayrollPolicy $policy = null): array
    {
        $multiplier = Rules::dayMultiplier($dayType, $policy);
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
     * capped at their statutory ceiling for the period; anything above the
     * remaining ceiling is taxable.
     */
    private static function nonTaxablePart(Config $cfg, Employee $employee, Earning $earning, Payslip $slip): int
    {
        if ($earning->taxable) {
            return 0;
        }

        if ($earning->kind === Earning::KIND_ALLOWANCE && $employee->benefits->deMinimis) {
            $benefit = self::deMinimisBenefit($cfg, $earning);

            if ($benefit !== null) {
                return self::deMinimisExemption($cfg, $employee, $earning, $benefit, $slip);
            }
        }

        return $earning->amount;
    }

    /**
     * How the engine taxes one earning line: the exempt portion, the maximum
     * applied to it, and the part above that maximum.
     *
     * Debug and audit use only. The serialised payslip shape and the Go parity
     * contract do not include this.
     *
     * @param  array<string, int>  $periodUsage  exempt centavos already used in each benefit period
     * @return array{taxable: bool, exempt: int, ceiling: ?int, basis: string, source: string, excess: int, key: ?string}
     */
    public static function earningTaxTreatment(Config $cfg, Employee $employee, Earning $earning, array $periodUsage = []): array
    {
        $amount = $earning->amount;

        if ($earning->kind === Earning::KIND_ALLOWANCE) {
            if ($earning->taxable) {
                return self::taxTreatment(taxable: true, amount: $amount, exempt: 0);
            }

            if ($employee->benefits->deMinimis) {
                $benefit = self::deMinimisBenefit($cfg, $earning);

                if ($benefit !== null) {
                    if ($benefit->basis === DeMinimisBenefit::BASIS_HOURLY_OT) {
                        $ceiling = self::deMinimisHourlyOtCeiling($cfg, $employee, $benefit);

                        return self::taxTreatment(
                            taxable: true,
                            amount: $amount,
                            exempt: Money::min($amount, $ceiling),
                            ceiling: $ceiling,
                            basis: $benefit->basis,
                            source: 'statutory de minimis',
                        );
                    }

                    $key = self::deMinimisUsageKey($cfg, $employee, $benefit);
                    $ceiling = Money::max(0, $benefit->ceiling - (int) ($periodUsage[$key] ?? 0));

                    return self::taxTreatment(
                        taxable: true,
                        amount: $amount,
                        exempt: Money::min($amount, $ceiling),
                        ceiling: $ceiling,
                        basis: $benefit->basis,
                        source: 'statutory de minimis · year-to-date',
                        key: $key,
                    );
                }
            }

            return self::taxTreatment(
                taxable: false,
                amount: $amount,
                exempt: $amount,
                source: $employee->benefits->deMinimis ? '' : 'de minimis not applied (employee flag off)',
            );
        }

        if ($earning->kind === Earning::KIND_BONUS && $earning->nonTaxableCeiling > 0) {
            return self::taxTreatment(
                taxable: true,
                amount: $amount,
                exempt: Money::min($amount, $earning->nonTaxableCeiling),
                ceiling: $earning->nonTaxableCeiling,
                basis: 'catalogue',
                source: 'compensations.maximum_non_tax',
            );
        }

        return self::taxTreatment(
            taxable: $earning->taxable,
            amount: $amount,
            exempt: $earning->taxable ? 0 : $amount,
        );
    }

    /**
     * @return array{taxable: bool, exempt: int, ceiling: ?int, basis: string, source: string, excess: int, key: ?string}
     */
    private static function taxTreatment(
        bool $taxable,
        int $amount,
        int $exempt,
        ?int $ceiling = null,
        string $basis = '',
        string $source = '',
        ?string $key = null,
    ): array {
        return [
            'taxable' => $taxable,
            'exempt' => $exempt,
            'ceiling' => $ceiling,
            'basis' => $basis,
            'source' => $source,
            'excess' => $ceiling === null ? 0 : Money::max(0, $amount - $exempt),
            'key' => $key,
        ];
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

    /**
     * The exempt portion of one de minimis allowance. The ceiling applies to
     * the cumulative amount for its own period (month, semester or year), so
     * the exemption left is the ceiling less what prior payrolls in that
     * period already exempted.
     */
    private static function deMinimisExemption(
        Config $cfg,
        Employee $employee,
        Earning $earning,
        DeMinimisBenefit $benefit,
        Payslip $slip,
    ): int {
        if ($benefit->basis === DeMinimisBenefit::BASIS_HOURLY_OT) {
            return Money::min($earning->amount, self::deMinimisHourlyOtCeiling($cfg, $employee, $benefit));
        }

        $key = self::deMinimisUsageKey($cfg, $employee, $benefit);
        $remaining = self::deMinimisRemaining($employee, $slip, $benefit, $key);
        $exempt = Money::min($earning->amount, $remaining);

        if ($exempt > 0) {
            $slip->deMinimisExempt[$key] = (int) ($slip->deMinimisExempt[$key] ?? 0) + $exempt;
        }

        return $exempt;
    }

    /**
     * The exemption still available for a benefit in this payroll's period.
     */
    private static function deMinimisRemaining(
        Employee $employee,
        Payslip $slip,
        DeMinimisBenefit $benefit,
        string $key,
    ): int {
        $used = (int) ($employee->ytd->deMinimis[$key] ?? 0)
            + (int) ($slip->deMinimisExempt[$key] ?? 0);

        return Money::max(0, $benefit->ceiling - $used);
    }

    /**
     * The ledger key for a benefit: its statutory code plus the calendar
     * month, semester or year the ceiling applies to.
     */
    private static function deMinimisUsageKey(Config $cfg, Employee $employee, DeMinimisBenefit $benefit): string
    {
        $code = ($benefit->code |> trim(...) |> strtolower(...)) ?: 'de_minimis';
        $date = $cfg->payrollDate !== ''
            ? DateTimeImmutable::createFromFormat('!Y-m-d', $cfg->payrollDate)
            : false;

        if ($date === false) {
            $period = $employee->ytd->year > 0 ? (string) $employee->ytd->year : '0';

            return $code.'|'.$period;
        }

        $period = match ($benefit->basis) {
            DeMinimisBenefit::BASIS_ANNUAL => $date->format('Y'),
            DeMinimisBenefit::BASIS_SEMESTER => $date->format('Y').'-S'.((int) $date->format('n') <= 6 ? '1' : '2'),
            default => $date->format('Y-m'),
        };

        return $code.'|'.$period;
    }

    /**
     * The meal-allowance ceiling for overtime and night-shift work: a percentage
     * of the hourly minimum wage for each overtime hour in the period
     * (RR 29-2025 sets 30%).
     */
    private static function deMinimisHourlyOtCeiling(Config $cfg, Employee $employee, DeMinimisBenefit $benefit): int
    {
        $daysPerYear = $cfg->daysPerYear > 0 ? $cfg->daysPerYear : 261;
        $hoursPerDay = $cfg->hoursPerDay > 0 ? $cfg->hoursPerDay : 8;

        $hourly = $employee->minimumWage > 0
            ? Money::mul($employee->minimumWage, 1 / $hoursPerDay)
            : Rules::hourlyRate($employee->monthlyBasic, $daysPerYear, $hoursPerDay);

        $rate = $benefit->percentage / 100;

        return Money::mul($hourly, self::overtimeHours($employee->timesheets) * $rate);
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
        return str_replace(['-', ' '], '_', $value |> trim(...) |> strtolower(...));
    }

    /**
     * Select what a statutory contribution is assessed on.
     */
    private static function contributionBase(string $base, Employee $employee, Payslip $slip, int $periods): int
    {
        return match ($base |> trim(...) |> strtolower(...)) {
            'gross' => Money::mul($slip->gross, (float) $periods),
            'period_gross' => $slip->gross,
            default => $employee->monthlyBasic,
        };
    }

    /**
     * Compute the generic country's statutory components.
     *
     * `$taxPass` false computes contributions and levies; true computes the tax
     * component against the finalised taxable income. Components are evaluated
     * in `sort_order`, mirroring the Go engine.
     *
     * @param  array<int, array<string, mixed>>  $components
     */
    private static function computeGenericComponents(
        Config $cfg,
        Employee $employee,
        Payslip $slip,
        int $periods,
        int &$employeeTotal,
        int &$employerTotal,
        int &$taxableReduction,
        bool $taxPass,
        int $minWageExemption,
    ): void {
        $components = $cfg->components;

        usort($components, static fn (array $a, array $b): int => ((int) ($a['sort_order'] ?? 100)) <=> ((int) ($b['sort_order'] ?? 100)));

        foreach ($components as $component) {
            $category = strtolower((string) ($component['category'] ?? 'contribution'));

            if ($taxPass !== ($category === 'tax')) {
                continue;
            }

            $divisor = GenericContributionService::divisor(
                (string) ($component['schedule'] ?? 'every_pay'),
                $cfg->frequency,
                $cfg->cutoff,
            );

            if ($divisor <= 0) {
                continue;
            }

            $baseName = strtolower((string) ($component['base'] ?? 'monthly_basic'));
            $base = self::genericBase($baseName, $employee, $slip, $periods, $minWageExemption, $taxPass);
            $result = GenericContributionService::compute($component, $base);

            // Monthly bases are billed in parts across the cut-offs; a period
            // base is already the amount due on this pay date.
            if (str_starts_with($baseName, 'monthly_')) {
                $result = GenericContributionService::perPeriod($result, $divisor);
            }

            $employeeAmount = $result['employee'];
            $employerAmount = $result['employer'];

            if ($category === 'tax') {
                $slip->withholdingTax += $employeeAmount;
            } else {
                $employeeTotal += $employeeAmount;
                $employerTotal += $employerAmount;

                if ($result['taxable']) {
                    $taxableReduction += $employeeAmount;
                }
            }

            $slip->deductionLines[] = new DeductionLine(
                code: (string) ($component['code'] ?? 'component'),
                name: (string) ($component['name'] ?? $component['code'] ?? 'Component'),
                category: $category,
                base: $result['base'],
                employee: $employeeAmount,
                employer: $employerAmount,
                reducesTaxableIncome: $result['taxable'],
                source: DeductionLine::SOURCE_STATUTORY,
                sortOrder: (int) ($component['sort_order'] ?? 100),
            );
        }
    }

    /**
     * Resolve a fixed or percentage-based employee deduction.
     */
    private static function deductionAmount(
        Employee $employee,
        Payslip $slip,
        int $periods,
        Earning $deduction,
        int $minWageExemption,
    ): int {
        if (($deduction->calculation |> trim(...) |> strtolower(...)) !== 'percentage' || $deduction->rate <= 0) {
            return max(0, $deduction->amount);
        }

        $basis = $deduction->basis !== '' ? $deduction->basis : 'period_basic';
        $base = self::genericBase($basis, $employee, $slip, $periods, $minWageExemption);

        if (str_starts_with(strtolower($basis), 'monthly_')) {
            $base = intdiv($base, max(1, $periods));
        }

        return max(0, (int) round($base * $deduction->rate, 0, PHP_ROUND_HALF_UP));
    }

    /**
     * The base a generic component or percentage deduction is assessed on.
     */
    private static function genericBase(
        string $base,
        Employee $employee,
        Payslip $slip,
        int $periods,
        int $minWageExemption = 0,
        bool $taxableResolved = false,
    ): int {
        return (int) match ($base |> trim(...) |> strtolower(...)) {
            'monthly_gross' => Money::mul($slip->gross, (float) max(1, $periods)),
            'period_gross', 'gross' => $slip->gross,
            'period_basic' => $slip->periodBasic,
            'taxable' => $taxableResolved
                ? $slip->taxableIncome
                : max(0, $slip->gross - $slip->nonTaxable - $minWageExemption),
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

        if (($employee->paymentType |> trim(...) |> strtolower(...)) === Employee::PAYMENT_MONTHLY) {
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
            return (new WithholdingTaxService($cfg->taxBrackets))->compute($taxable);
        }

        $brackets = $cfg->annualTaxBrackets !== []
            ? $cfg->annualTaxBrackets
            : WithholdingTaxTable::train(PayFrequency::MONTHLY);

        $monthlyTax = (new WithholdingTaxService($brackets))->compute(Money::mul($taxable, (float) $periods));

        return self::firstPart($monthlyTax, $periods);
    }

    private static function uniformSchedules(Config $cfg): bool
    {
        return $cfg->sssSchedule === ContributionSchedule::EVERY_PAY->value
            && $cfg->philHealthSchedule === ContributionSchedule::EVERY_PAY->value
            && $cfg->pagIbigSchedule === ContributionSchedule::EVERY_PAY->value
            && $cfg->taxSchedule === ContributionSchedule::EVERY_PAY->value;
    }

    /**
     * How many parts of a monthly statutory amount fall in this run.
     */
    private static function divisor(Config $cfg, string $schedule): int
    {
        return ContributionSchedule::fromString($schedule)
            ->divisor(PayFrequency::fromString($cfg->frequency), $cfg->cutoff);
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
