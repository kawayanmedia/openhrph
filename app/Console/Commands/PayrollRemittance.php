<?php

namespace App\Console\Commands;

use App\Models\Payroll;
use App\Models\PayrollDetail;
use App\Support\Tenancy\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * Reconciles a payroll to the statutory remittances.
 *
 * The summary format keeps the original four-row reconciliation. The agency
 * formats emit employee-level working papers with a control total.
 */
class PayrollRemittance extends Command
{
    protected $signature = 'payroll:remittance
        {payroll : Payroll id}
        {--format=summary : summary|sss|philhealth|pagibig|bir|all}
        {--path= : Write the CSV to this path, or a directory when --format=all}
        {--no-control-totals : Omit the TOTAL row}';

    protected $description = 'Reconcile a payroll to the statutory remittances';

    public function handle(): int
    {
        $payroll = Payroll::withoutTenant()->find($this->argument('payroll'));

        if (! $payroll) {
            $this->error('Payroll not found.');

            return self::FAILURE;
        }

        app(TenantContext::class)->set((int) $payroll->company_id);

        $details = $payroll->payrollDetails()->with('employee')->get();

        if ($details->isEmpty()) {
            $this->error('This payroll has no details to reconcile.');

            return self::FAILURE;
        }

        $format = strtolower((string) $this->option('format'));

        if ($format === 'all') {
            return $this->writeAll($details);
        }

        if (! in_array($format, ['summary', 'sss', 'philhealth', 'pagibig', 'bir'], true)) {
            $this->error('Unknown format. Use summary, sss, philhealth, pagibig, bir or all.');

            return self::FAILURE;
        }

        $csv = $this->csv($format, $details);

        return $this->write($csv, $this->option('path'), 'remittance-'.$format.'.csv');
    }

    /**
     * @param  Collection<int, PayrollDetail>  $details
     */
    protected function writeAll(Collection $details): int
    {
        $path = $this->option('path');

        if ($path && ! is_dir($path)) {
            mkdir($path, 0775, true);
        }

        foreach (['summary', 'sss', 'philhealth', 'pagibig', 'bir'] as $format) {
            $csv = $this->csv($format, $details);

            if ($path) {
                file_put_contents(rtrim($path, '/').'/remittance-'.$format.'.csv', $csv);
                $this->line('Wrote remittance-'.$format.'.csv');
            } else {
                $this->line('--- '.strtoupper($format).' ---');
                $this->line($csv);
            }
        }

        return self::SUCCESS;
    }

    /**
     * @param  Collection<int, PayrollDetail>  $details
     */
    protected function csv(string $format, Collection $details): string
    {
        $rows = match ($format) {
            'sss' => $this->sssRows($details),
            'philhealth' => $this->philhealthRows($details),
            'pagibig' => $this->pagibigRows($details),
            'bir' => $this->birRows($details),
            default => $this->summaryRows($details),
        };

        if (! $this->option('no-control-totals') && $format !== 'summary') {
            $rows[] = $this->totalRow($rows);
        }

        $stream = fopen('php://temp', 'r+');
        foreach ($rows as $row) {
            fputcsv($stream, $row);
        }
        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        return $csv;
    }

    /**
     * @param  Collection<int, PayrollDetail>  $details
     * @return array<int, array<int, string>>
     */
    protected function summaryRows(Collection $details): array
    {
        $rows = [['Agency', 'Employee share', 'Employer share', 'Total']];

        foreach ([
            'SSS' => ['sss_employee_total', 'sss_employer_total'],
            'PhilHealth' => ['philhealth_employee', 'philhealth_employer'],
            'Pag-IBIG' => ['hdmf_employee', 'hdmf_employer'],
        ] as $agency => [$employeeColumn, $employerColumn]) {
            $employee = $this->sum($details, $employeeColumn);
            $employer = $this->sum($details, $employerColumn);
            $rows[] = [$agency, $this->peso($employee), $this->peso($employer), $this->peso($employee + $employer)];
        }

        $tax = $this->sum($details, 'withholding_tax');
        $rows[] = ['BIR (1601-C)', $this->peso($tax), '0.00', $this->peso($tax)];

        return $rows;
    }

    /**
     * @param  Collection<int, PayrollDetail>  $details
     * @return array<int, array<int, string>>
     */
    protected function sssRows(Collection $details): array
    {
        $rows = [['Employee no', 'Name', 'SSS number', 'Monthly salary credit', 'Employee share', 'Employer share', 'Total']];

        foreach ($details as $detail) {
            $rows[] = [
                (string) $detail->employee?->employee_number,
                $this->name($detail),
                (string) $detail->employee?->sss_number,
                $this->peso((int) $detail->getRawOriginal('basic_monthly_salary')),
                $this->peso((int) $detail->getRawOriginal('sss_employee_total')),
                $this->peso((int) $detail->getRawOriginal('sss_employer_total')),
                $this->peso((int) $detail->getRawOriginal('sss_employee_total') + (int) $detail->getRawOriginal('sss_employer_total')),
            ];
        }

        return $rows;
    }

    /**
     * @param  Collection<int, PayrollDetail>  $details
     * @return array<int, array<int, string>>
     */
    protected function philhealthRows(Collection $details): array
    {
        $rows = [['Employee no', 'Name', 'PhilHealth number', 'Monthly basic', 'Employee share', 'Employer share', 'Total']];

        foreach ($details as $detail) {
            $rows[] = [
                (string) $detail->employee?->employee_number,
                $this->name($detail),
                (string) $detail->employee?->philhealth_number,
                $this->peso((int) $detail->getRawOriginal('basic_monthly_salary')),
                $this->peso((int) $detail->getRawOriginal('philhealth_employee')),
                $this->peso((int) $detail->getRawOriginal('philhealth_employer')),
                $this->peso((int) $detail->getRawOriginal('philhealth_employee') + (int) $detail->getRawOriginal('philhealth_employer')),
            ];
        }

        return $rows;
    }

    /**
     * @param  Collection<int, PayrollDetail>  $details
     * @return array<int, array<int, string>>
     */
    protected function pagibigRows(Collection $details): array
    {
        $rows = [['Employee no', 'Name', 'Pag-IBIG number', 'Fund salary', 'Employee share', 'Employer share', 'Total']];

        foreach ($details as $detail) {
            $fundSalary = min((int) $detail->getRawOriginal('basic_monthly_salary'), 1000000);
            $rows[] = [
                (string) $detail->employee?->employee_number,
                $this->name($detail),
                (string) $detail->employee?->hdmf_number,
                $this->peso($fundSalary),
                $this->peso((int) $detail->getRawOriginal('hdmf_employee')),
                $this->peso((int) $detail->getRawOriginal('hdmf_employer')),
                $this->peso((int) $detail->getRawOriginal('hdmf_employee') + (int) $detail->getRawOriginal('hdmf_employer')),
            ];
        }

        return $rows;
    }

    /**
     * @param  Collection<int, PayrollDetail>  $details
     * @return array<int, array<int, string>>
     */
    protected function birRows(Collection $details): array
    {
        $rows = [['Employee no', 'Name', 'TIN', 'Taxable income', 'Tax withheld']];

        foreach ($details as $detail) {
            $rows[] = [
                (string) $detail->employee?->employee_number,
                $this->name($detail),
                (string) $detail->employee?->tin_number,
                $this->peso((int) $detail->getRawOriginal('taxable_income')),
                $this->peso((int) $detail->getRawOriginal('withholding_tax')),
            ];
        }

        return $rows;
    }

    /**
     * @param  array<int, array<int, string>>  $rows
     * @return array<int, string>
     */
    protected function totalRow(array $rows): array
    {
        $header = array_shift($rows);
        $total = array_fill(0, count($header), '');
        $total[0] = 'TOTAL';

        foreach ($rows as $row) {
            foreach (array_slice(array_keys($header), 3) as $index) {
                if (isset($row[$index]) && is_numeric($row[$index])) {
                    $total[$index] = number_format(((float) ($total[$index] ?: 0)) + (float) $row[$index], 2, '.', '');
                }
            }
        }

        return $total;
    }

    /**
     * @param  Collection<int, PayrollDetail>  $details
     */
    protected function sum(Collection $details, string $column): int
    {
        return (int) $details->sum(fn ($detail) => (int) $detail->getRawOriginal($column));
    }

    protected function name($detail): string
    {
        return trim(($detail->employee?->first_name ?? '').' '.($detail->employee?->last_name ?? ''));
    }

    protected function write(string $csv, ?string $path, string $defaultName): int
    {
        if ($path) {
            if (is_dir($path)) {
                $path = rtrim($path, '/').'/'.$defaultName;
            }

            file_put_contents($path, $csv);
            $this->info('Remittance reconciliation written to '.$path);

            return self::SUCCESS;
        }

        $this->line($csv);

        return self::SUCCESS;
    }

    private function peso(int $centavos): string
    {
        return number_format($centavos / 100, 2, '.', '');
    }
}
