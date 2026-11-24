<?php

namespace App\Services\Payroll;

use App\Models\Payroll;
use App\Models\PayrollDetail;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Support\Collection;

/**
 * Builds the payroll register (gross, deductions, employer cost, net pay).
 */
final class PayrollRegisterReport
{
    /**
     * @return array<int, array<int, string>>
     */
    public function rows(Payroll $payroll): array
    {
        return $this->withinPayroll($payroll, function (Collection $details): array {
            $rows = [[
                'Employee no', 'Name', 'Period basic', 'Regular pay', 'Overtime', 'Night', 'Holiday',
                'Rest day', 'Allowances', 'Compensations', 'Bonus', 'Commission', '13th month',
                'Leave conversion', 'Separation pay', 'Gross', 'Taxable', 'Non-taxable', 'Tax',
                'SSS employee', 'SSS employer', 'PhilHealth employee', 'PhilHealth employer',
                'Pag-IBIG employee', 'Pag-IBIG employer', 'Loans', 'Other deductions',
                'Total deductions', 'Employer cost', 'Net pay',
            ]];

            foreach ($details as $detail) {
                $rows[] = [
                    $detail->employee?->employee_number,
                    trim(($detail->employee?->first_name ?? '').' '.($detail->employee?->last_name ?? '')),
                    $this->peso($detail->getRawOriginal('basic_salary')),
                    $this->peso($detail->getRawOriginal('regular_pay')),
                    $this->peso($detail->getRawOriginal('overtime_pay')),
                    $this->peso($detail->getRawOriginal('night_pay')),
                    $this->peso($detail->getRawOriginal('holiday_pay')),
                    $this->peso($detail->getRawOriginal('rest_day_pay')),
                    $this->peso($detail->getRawOriginal('allowance')),
                    $this->peso($detail->getRawOriginal('incentive')),
                    $this->peso($detail->getRawOriginal('bonus')),
                    $this->peso($detail->getRawOriginal('commission')),
                    $this->peso($detail->getRawOriginal('thirteenth_month')),
                    $this->peso($detail->getRawOriginal('leave_conversion')),
                    $this->peso($detail->getRawOriginal('separation_pay')),
                    $this->peso($detail->getRawOriginal('gross_income')),
                    $this->peso($detail->getRawOriginal('taxable_income')),
                    $this->peso($detail->getRawOriginal('non_taxable_income')),
                    $this->peso($detail->getRawOriginal('withholding_tax')),
                    $this->peso($detail->getRawOriginal('sss_employee_total')),
                    $this->peso($detail->getRawOriginal('sss_employer_total')),
                    $this->peso($detail->getRawOriginal('philhealth_employee')),
                    $this->peso($detail->getRawOriginal('philhealth_employer')),
                    $this->peso($detail->getRawOriginal('hdmf_employee')),
                    $this->peso($detail->getRawOriginal('hdmf_employer')),
                    $this->peso($detail->getRawOriginal('loans')),
                    $this->peso(max(0, (int) $detail->getRawOriginal('total_deductions') - (int) $detail->getRawOriginal('total_employee') - (int) $detail->getRawOriginal('withholding_tax'))),
                    $this->peso($detail->getRawOriginal('total_deductions')),
                    $this->peso($detail->getRawOriginal('total_employer')),
                    $this->peso($detail->getRawOriginal('net_pay')),
                ];
            }

            return $rows;
        });
    }

    public function csv(Payroll $payroll): string
    {
        return $this->toCsv($this->rows($payroll));
    }

    /**
     * @template TReturn
     *
     * @param  Closure(Collection<int, PayrollDetail>): TReturn  $callback
     * @return TReturn
     */
    private function withinPayroll(Payroll $payroll, Closure $callback): mixed
    {
        return app(TenantContext::class)->run(
            (int) $payroll->company_id,
            fn (): mixed => $callback(
                $payroll->payrollDetails()->with('employee')->orderBy('id')->get()
            ),
        );
    }

    private function peso(mixed $centavos): string
    {
        return number_format(((int) $centavos) / 100, 2, '.', '');
    }

    /**
     * @param  array<int, array<int, mixed>>  $rows
     */
    private function toCsv(array $rows): string
    {
        $stream = fopen('php://temp', 'r+');

        foreach ($rows as $row) {
            fputcsv($stream, $row);
        }

        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        return (string) $csv;
    }
}
