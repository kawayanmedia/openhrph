<?php

namespace App\Services\Payroll\Engine\Legacy;

use App\Services\Payroll\Computation\Config;
use App\Services\Payroll\Computation\Earning;
use App\Services\Payroll\Computation\Money;
use App\Services\Payroll\Computation\Payslip;
use App\Services\Payroll\Engine\PayrollEngineException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Persists a computed payroll run.
 *
 * Mirrors internal/db/write.go: the current details are snapshotted into
 * payroll_detail_revisions, replaced, the run totals and audit event are
 * recorded and the year-to-date ledger is updated, all in one transaction.
 */
final class LegacyWriter
{
    /**
     * @param  list<Payslip>  $slips
     */
    public static function write(Run $run, array $slips, WriteOptions $options): Summary
    {
        return DB::transaction(function () use ($run, $slips, $options): Summary {
            $summary = new Summary(payrollID: $run->payrollID, employeeCount: count($slips));

            $status = (string) (DB::table('payrolls')
                ->where('id', $run->payrollID)
                ->where('company_id', $run->companyID)
                ->value('status') ?? 'open');

            if (in_array($status, ['approved', 'closed'], true) && ! $options->allowReopen) {
                throw new PayrollEngineException(
                    "payroll {$run->payrollID} is {$status}; reopen it with a reason before re-processing",
                );
            }

            // Re-processing must not double count the year-to-date ledger, so
            // first remove what this payroll previously contributed.
            self::reverseYtd($run);

            $revision = Schema::hasTable('payroll_detail_revisions')
                ? self::snapshotRevision($run, $options)
                : 0;

            DB::table('payroll_details')
                ->where('payroll_id', $run->payrollID)
                ->where('company_id', $run->companyID)
                ->delete();

            $details = DB::table('payroll_details');
            $detailColumns = self::existingColumns('payroll_details', self::detailColumns());
            $now = now();

            foreach ($slips as $slip) {
                $row = self::detailRow($run, $slip, $now);

                $insert = [];

                foreach ($detailColumns as $column) {
                    $insert[$column] = $row[$column];
                }

                $details->insert($insert);

                $summary->gross = Money::add($summary->gross, $slip->gross);
                $summary->deductions = Money::add($summary->deductions, $slip->totalDeductions);
                $summary->net = Money::add($summary->net, $slip->netPay);
                $summary->warnings = array_merge($summary->warnings, $slip->warnings);
            }

            $summary->revision = $revision;

            self::updatePayroll($run, $summary, $options, $now);
            self::updateYtd($run, $slips, $now);
            self::recordAudit($run, $summary, $options);

            return $summary;
        });
    }

    /**
     * @return list<string>
     */
    public static function detailColumns(): array
    {
        return [
            'company_id', 'payroll_id', 'employee_id', 'year',
            'basic_monthly_salary', 'basic_salary', 'daily_rate',
            'gross_income', 'taxable_income', 'non_taxable_income', 'withholding_tax',
            'sss_employee', 'sss_provident_employee', 'sss_employee_total',
            'sss_employer', 'sss_provident_employer', 'sss_employer_ec_er', 'sss_employer_total', 'sss_total',
            'hdmf_employee', 'hdmf_employer', 'hdmf_total',
            'philhealth_employee', 'philhealth_employer', 'total_philhealth',
            'regular_hours', 'regular_pay', 'overtime_hours', 'overtime_pay',
            'night_hours', 'night_pay', 'rest_day_pay', 'holiday_pay',
            'absent_days', 'absent_days_amount', 'allowance', 'incentive',
            'compensations', 'bonus', 'commission', 'thirteenth_month', 'leave_conversion', 'separation_pay',
            'loans', 'basic_earned', 'benefit_exempt', 'minimum_wage_exemption', 'warnings',
            'total_employee', 'total_employer', 'total_deductions', 'net_pay',
            'created_at', 'updated_at',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function detailRow(Run $run, Payslip $slip, mixed $now): array
    {
        $employeeContribution = $slip->sssEmployee + $slip->sssProvident + $slip->philHealthEmployee + $slip->pagIbigEmployee;

        return [
            'company_id' => $run->companyID,
            'payroll_id' => $run->payrollID,
            'employee_id' => $slip->employeeID,
            'year' => $run->year(),
            'basic_monthly_salary' => $slip->basicMonthly,
            'basic_salary' => $slip->periodBasic,
            'daily_rate' => $slip->dailyRate,
            'gross_income' => $slip->gross,
            'taxable_income' => $slip->taxableIncome,
            'non_taxable_income' => $slip->nonTaxable,
            'withholding_tax' => $slip->withholdingTax,
            'sss_employee' => $slip->sssEmployee,
            'sss_provident_employee' => $slip->sssProvident,
            'sss_employee_total' => $slip->sssEmployee + $slip->sssProvident,
            'sss_employer' => $slip->sssEmployer,
            'sss_provident_employer' => $slip->sssProvidentER,
            'sss_employer_ec_er' => $slip->sssEC,
            'sss_employer_total' => $slip->sssEmployer + $slip->sssProvidentER + $slip->sssEC,
            'sss_total' => $slip->sssTotal,
            'hdmf_employee' => $slip->pagIbigEmployee,
            'hdmf_employer' => $slip->pagIbigEmployer,
            'hdmf_total' => $slip->pagIbigEmployee + $slip->pagIbigEmployer,
            'philhealth_employee' => $slip->philHealthEmployee,
            'philhealth_employer' => $slip->philHealthEmployer,
            'total_philhealth' => $slip->philHealthEmployee + $slip->philHealthEmployer,
            'regular_hours' => $slip->regularHours,
            'regular_pay' => $slip->regularPay,
            'overtime_hours' => $slip->overtimeHours,
            'overtime_pay' => $slip->overtimePay,
            'night_hours' => $slip->nightHours,
            'night_pay' => $slip->nightPay,
            'rest_day_pay' => $slip->restDayPay,
            'holiday_pay' => $slip->holidayPay,
            'absent_days' => $slip->absentDays,
            'absent_days_amount' => $slip->absentAmount,
            'allowance' => $slip->allowances,
            'incentive' => $slip->incentives,
            'compensations' => json_encode(array_map(
                static fn (Earning $earning): array => $earning->toArray(),
                $slip->compensations,
            )),
            'bonus' => $slip->bonus,
            'commission' => $slip->commission,
            'thirteenth_month' => $slip->thirteenthMonth,
            'leave_conversion' => $slip->leaveConversion,
            'separation_pay' => $slip->separationPay,
            'loans' => $slip->loans,
            'basic_earned' => $slip->basicEarned,
            'benefit_exempt' => $slip->benefitExempt,
            'minimum_wage_exemption' => $slip->minimumWageExemption,
            'warnings' => json_encode(array_values($slip->warnings)),
            'total_employee' => $employeeContribution,
            'total_employer' => $slip->totalEmployerCost,
            'total_deductions' => $slip->totalDeductions,
            'net_pay' => $slip->netPay,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    /**
     * @return list<string>
     */
    private static function existingColumns(string $table, array $columns): array
    {
        if (! Schema::hasTable($table)) {
            throw new RuntimeException("table {$table} does not exist");
        }

        return array_values(array_filter(
            $columns,
            static fn (string $column): bool => Schema::hasColumn($table, $column),
        ));
    }

    private static function snapshotRevision(Run $run, WriteOptions $options): int
    {
        $count = DB::table('payroll_details')
            ->where('payroll_id', $run->payrollID)
            ->where('company_id', $run->companyID)
            ->whereNull('deleted_at')
            ->count();

        if ($count === 0) {
            return 0;
        }

        $revision = (int) DB::table('payroll_detail_revisions')
            ->where('payroll_id', $run->payrollID)
            ->where('company_id', $run->companyID)
            ->max('revision') + 1;

        DB::statement(
            'insert into payroll_detail_revisions (
                company_id, payroll_id, revision, payroll_status, engine_version, employee_id,
                basic_monthly_salary, basic_salary, daily_rate, gross_income, taxable_income, non_taxable_income,
                withholding_tax, sss_employee, sss_provident_employee, sss_employer, sss_provident_employer,
                sss_employer_ec_er, hdmf_employee, hdmf_employer, philhealth_employee, philhealth_employer,
                regular_pay, overtime_pay, night_pay, rest_day_pay, holiday_pay, allowance, incentive,
                total_employee, total_employer, total_deductions, net_pay, warnings, created_at
            )
            select company_id, payroll_id, ?, ?, ?, employee_id,
                   basic_monthly_salary, basic_salary, daily_rate, gross_income, taxable_income, non_taxable_income,
                   withholding_tax, sss_employee, sss_provident_employee, sss_employer, sss_provident_employer,
                   sss_employer_ec_er, hdmf_employee, hdmf_employer, philhealth_employee, philhealth_employer,
                   regular_pay, overtime_pay, night_pay, rest_day_pay, holiday_pay, allowance, incentive,
                   total_employee, total_employer, total_deductions, net_pay, warnings, ?
            from payroll_details
            where payroll_id = ? and company_id = ? and deleted_at is null',
            [
                $revision,
                $run->status,
                $options->engineVersion === '' ? null : $options->engineVersion,
                now(),
                $run->payrollID,
                $run->companyID,
            ],
        );

        return $revision;
    }

    /**
     * Remove the contribution of the payroll's current details from the
     * year-to-date ledger so a re-processing replaces rather than adds.
     */
    private static function reverseYtd(Run $run): void
    {
        if ($run->type === Config::RUN_ANNUAL_ADJUSTMENT) {
            return;
        }

        if (! Schema::hasTable('employee_payroll_ytd')) {
            return;
        }

        if (! Schema::hasColumn('payroll_details', 'basic_earned') || ! Schema::hasColumn('payroll_details', 'benefit_exempt')) {
            return;
        }

        $rows = DB::table('payroll_details')
            ->where('payroll_id', $run->payrollID)
            ->where('company_id', $run->companyID)
            ->whereNull('deleted_at')
            ->groupBy('employee_id')
            ->get([
                'employee_id',
                DB::raw('coalesce(sum(basic_earned), 0) as basic_earned'),
                DB::raw('coalesce(sum(gross_income), 0) as gross_income'),
                DB::raw('coalesce(sum(taxable_income), 0) as taxable_income'),
                DB::raw('coalesce(sum(withholding_tax), 0) as withholding_tax'),
                DB::raw('coalesce(sum(sss_employee_total + hdmf_employee + philhealth_employee), 0) as contributions'),
                DB::raw('coalesce(sum(thirteenth_month), 0) as thirteenth_month'),
                DB::raw('coalesce(sum(benefit_exempt), 0) as benefit_exempt'),
            ]);

        foreach ($rows as $row) {
            $query = DB::table('employee_payroll_ytd')
                ->where('company_id', $run->companyID)
                ->where('employee_id', (int) $row->employee_id)
                ->where('year', $run->year());

            $current = $query->first(['basic_earned', 'gross_earned', 'taxable_income', 'tax_withheld', 'contributions', 'thirteenth_month_paid', 'other_benefits']);

            if ($current === null) {
                continue;
            }

            DB::table('employee_payroll_ytd')
                ->where('company_id', $run->companyID)
                ->where('employee_id', (int) $row->employee_id)
                ->where('year', $run->year())
                ->update([
                    'basic_earned' => max(0, (int) $current->basic_earned - (int) $row->basic_earned),
                    'gross_earned' => max(0, (int) $current->gross_earned - (int) $row->gross_income),
                    'taxable_income' => max(0, (int) $current->taxable_income - (int) $row->taxable_income),
                    'tax_withheld' => max(0, (int) $current->tax_withheld - (int) $row->withholding_tax),
                    'contributions' => max(0, (int) $current->contributions - (int) $row->contributions),
                    'thirteenth_month_paid' => max(0, (int) $current->thirteenth_month_paid - (int) $row->thirteenth_month),
                    'other_benefits' => max(0, (int) $current->other_benefits - (int) $row->benefit_exempt),
                ]);
        }
    }

    private static function updatePayroll(Run $run, Summary $summary, WriteOptions $options, mixed $now): void
    {
        $update = [
            'gross_amount' => $summary->gross,
            'net_amount' => $summary->net,
            'status' => 'processed',
            'engine_version' => $options->engineVersion === '' ? null : $options->engineVersion,
            'processed_at' => $now,
            'processed_employees' => $summary->employeeCount,
            'warnings' => json_encode(array_values($summary->warnings)),
            'input_hash' => $options->inputHash === '' ? null : $options->inputHash,
        ];

        $update = array_filter(
            $update,
            static fn (string $column): bool => Schema::hasColumn('payrolls', $column),
            ARRAY_FILTER_USE_KEY,
        );

        DB::table('payrolls')
            ->where('id', $run->payrollID)
            ->where('company_id', $run->companyID)
            ->update($update);
    }

    /**
     * @param  list<Payslip>  $slips
     */
    private static function updateYtd(Run $run, array $slips, mixed $now): void
    {
        if (! Schema::hasTable('employee_payroll_ytd')) {
            return;
        }

        foreach ($slips as $slip) {
            if ($slip->gross === 0) {
                continue;
            }

            $basicEarned = $slip->basicEarned;
            $otherBenefits = $slip->benefitExempt;
            $thirteenth = 0;

            switch ($run->type) {
                case Config::RUN_THIRTEENTH_MONTH:
                    $basicEarned = 0;
                    $otherBenefits = 0;
                    $thirteenth = $slip->thirteenthMonth;

                    break;
                case Config::RUN_FINAL_PAY:
                    $thirteenth = $slip->thirteenthMonth;
                    $otherBenefits = 0;

                    break;
            }

            $contributions = $slip->sssEmployee + $slip->sssProvident + $slip->philHealthEmployee + $slip->pagIbigEmployee;

            $query = DB::table('employee_payroll_ytd')
                ->where('company_id', $run->companyID)
                ->where('employee_id', $slip->employeeID)
                ->where('year', $run->year());

            $current = $query->first(['basic_earned', 'gross_earned', 'taxable_income', 'tax_withheld', 'contributions', 'thirteenth_month_paid', 'other_benefits']);

            if ($current === null) {
                DB::table('employee_payroll_ytd')->insert([
                    'company_id' => $run->companyID,
                    'employee_id' => $slip->employeeID,
                    'year' => $run->year(),
                    'basic_earned' => $basicEarned,
                    'gross_earned' => $slip->gross,
                    'taxable_income' => $slip->taxableIncome,
                    'tax_withheld' => $slip->withholdingTax,
                    'contributions' => $contributions,
                    'thirteenth_month_paid' => $thirteenth,
                    'other_benefits' => $otherBenefits,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                continue;
            }

            DB::table('employee_payroll_ytd')
                ->where('company_id', $run->companyID)
                ->where('employee_id', $slip->employeeID)
                ->where('year', $run->year())
                ->update([
                    'basic_earned' => (int) $current->basic_earned + $basicEarned,
                    'gross_earned' => (int) $current->gross_earned + $slip->gross,
                    'taxable_income' => (int) $current->taxable_income + $slip->taxableIncome,
                    'tax_withheld' => (int) $current->tax_withheld + $slip->withholdingTax,
                    'contributions' => (int) $current->contributions + $contributions,
                    'thirteenth_month_paid' => (int) $current->thirteenth_month_paid + $thirteenth,
                    'other_benefits' => (int) $current->other_benefits + $otherBenefits,
                    'updated_at' => $now,
                ]);
        }
    }

    private static function recordAudit(Run $run, Summary $summary, WriteOptions $options): void
    {
        if (! Schema::hasTable('payroll_audit_events')) {
            return;
        }

        $event = ($summary->revision > 0 || in_array($run->status, ['approved', 'closed'], true))
            ? 'reprocessed'
            : 'processed';

        DB::table('payroll_audit_events')->insert([
            'company_id' => $run->companyID,
            'payroll_id' => $run->payrollID,
            'event' => $event,
            'actor_id' => $options->actorID === 0 ? null : $options->actorID,
            'reason' => $options->allowReopen && $options->reopenReason !== '' ? $options->reopenReason : null,
            'engine_version' => $options->engineVersion === '' ? null : $options->engineVersion,
            'gross_amount' => $summary->gross,
            'net_amount' => $summary->net,
            'payload' => json_encode([
                'employees' => $summary->employeeCount,
                'gross' => $summary->grossPesos(),
                'net' => $summary->netPesos(),
                'revision' => $summary->revision,
                'warnings' => count($summary->warnings),
            ]),
            'created_at' => now(),
        ]);
    }
}
