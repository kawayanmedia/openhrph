<?php

namespace App\Console\Commands;

use App\Models\Payroll;
use App\Support\Tenancy\TenantContext;
use Illuminate\Console\Command;

/**
 * Exports the payroll register: every employee's gross, each deduction, the
 * employer share and net pay, suitable for reconciliation to the journal.
 */
class PayrollRegister extends Command
{
    protected $signature = 'payroll:register
        {payroll : Payroll id}
        {--path= : Write the CSV to this path instead of stdout}';

    protected $description = 'Export the payroll register as CSV';

    public function handle(): int
    {
        $payroll = Payroll::withoutTenant()->find($this->argument('payroll'));

        if (! $payroll) {
            $this->error('Payroll not found.');

            return self::FAILURE;
        }

        app(TenantContext::class)->set((int) $payroll->company_id);

        $details = $payroll->payrollDetails()->with('employee')->orderBy('id')->get();

        if ($details->isEmpty()) {
            $this->error('This payroll has no details to export.');

            return self::FAILURE;
        }

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

        $csv = $this->toCsv($rows);
        $path = $this->option('path');

        if ($path) {
            file_put_contents($path, $csv);
            $this->info('Payroll register written to '.$path);

            return self::SUCCESS;
        }

        $this->line($csv);

        return self::SUCCESS;
    }

    private function peso($centavos): string
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

        return $csv;
    }
}
