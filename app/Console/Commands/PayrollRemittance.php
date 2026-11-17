<?php

namespace App\Console\Commands;

use App\Models\Payroll;
use App\Models\PayrollDetail;
use App\Sectors\SectorManager;
use App\Services\Payroll\Computation\DeductionLine;
use App\Support\Tenancy\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reconciles a payroll to the statutory remittances.
 *
 * The summary format keeps the original four-row reconciliation. The agency
 * formats emit employee-level working papers with a control total. GSIS loans
 * are not statutory contributions, so they are reported by the loans working
 * paper instead of the GSIS premium paper.
 */
class PayrollRemittance extends Command
{
    protected $signature = 'payroll:remittance
        {payroll : Payroll id}
        {--format=summary : summary|components|sss|philhealth|pagibig|gsis|bir|coa|loans|sector|all}
        {--path= : Write the CSV to this path, or a directory when --format=all}
        {--agency= : Loan agency filter for --format=loans, for example GSIS}
        {--no-control-totals : Omit the TOTAL row}';

    /** @var array<string, string> loan code to agency */
    protected array $loanAgencies = [];

    protected $description = 'Reconcile a payroll to the statutory remittances';

    public function handle(): int
    {
        $payroll = Payroll::withoutTenant()->find($this->argument('payroll'));

        if (! $payroll) {
            $this->error('Payroll not found.');

            return self::FAILURE;
        }

        app(TenantContext::class)->set((int) $payroll->company_id);

        $details = $payroll->payrollDetails()->with(['employee', 'deductionLines'])->get();

        if ($details->isEmpty()) {
            $this->error('This payroll has no details to reconcile.');

            return self::FAILURE;
        }

        $this->loanAgencies = Schema::hasTable('loans')
            ? DB::table('loans')
                ->where('company_id', $payroll->company_id)
                ->pluck('loan_agency', 'code')
                ->map(fn ($agency): string => (string) $agency)
                ->all()
            : [];

        $format = strtolower((string) $this->option('format'));

        if ($format === 'all') {
            return $this->writeFormats(['summary', 'components', 'sss', 'philhealth', 'pagibig', 'gsis', 'bir', 'coa', 'loans'], $details);
        }

        if ($format === 'sector') {
            return $this->writeSector($payroll, $details);
        }

        if (! in_array($format, ['summary', 'components', 'sss', 'philhealth', 'pagibig', 'gsis', 'bir', 'coa', 'loans'], true)) {
            $this->error('Unknown format. Use summary, components, sss, philhealth, pagibig, gsis, bir, coa, loans, sector or all.');

            return self::FAILURE;
        }

        $csv = $this->csv($format, $details);

        return $this->write($csv, $this->option('path'), 'remittance-'.$format.'.csv');
    }

    /**
     * Write the remittance set the installation sector actually remits.
     *
     * @param  Collection<int, PayrollDetail>  $details
     */
    protected function writeSector(Payroll $payroll, Collection $details): int
    {
        $policy = app(SectorManager::class)->forCompany((int) $payroll->company_id);
        $configured = array_map('strtolower', (array) ($policy->remittances()['remittances'] ?? []));
        $formats = array_values(array_intersect(
            ['sss', 'philhealth', 'pagibig', 'gsis', 'bir'],
            $configured,
        ));

        if (in_array('coa_payroll_register', (array) ($policy->remittances()['registers'] ?? []), true)) {
            $formats[] = 'coa';
        }

        // Loans are a working paper, not a statutory premium.
        $formats[] = 'loans';

        return $this->writeFormats(array_values(array_unique(array_merge(['summary'], $formats))), $details);
    }

    /**
     * @param  array<int, string>  $formats
     * @param  Collection<int, PayrollDetail>  $details
     */
    protected function writeFormats(array $formats, Collection $details): int
    {
        $path = $this->option('path');

        if ($path && ! is_dir($path)) {
            mkdir($path, 0775, true);
        }

        foreach ($formats as $format) {
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
            'gsis' => $this->gsisRows($details),
            'bir' => $this->birRows($details),
            'coa' => $this->coaRows($details),
            'loans' => $this->loanRows($details),
            'components' => $this->componentRows($details),
            default => $this->summaryRows($details),
        };

        if (! $this->option('no-control-totals') && $format !== 'summary') {
            $rows[] = $this->totalRow($rows, $this->totalColumns($format));
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
        if ($details->contains(fn (PayrollDetail $detail): bool => $detail->deductionLines->isNotEmpty())) {
            return $this->componentRows($details);
        }

        $rows = [['Agency', 'Employee share', 'Employer share', 'Total']];

        foreach ([
            'SSS' => ['sss_employee_total', 'sss_employer_total'],
            'GSIS' => ['gsis_employee', 'gsis_employer'],
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
     * Country-neutral remittance by statutory component.
     *
     * @param  Collection<int, PayrollDetail>  $details
     * @return array<int, array<int, string>>
     */
    protected function componentRows(Collection $details): array
    {
        $rows = [['Component', 'Code', 'Employee share', 'Employer share', 'Total']];
        $groups = [];

        foreach ($details as $detail) {
            foreach ($detail->deductionLines as $line) {
                if ($line->source !== 'statutory') {
                    continue;
                }

                $key = strtolower((string) $line->code);
                $groups[$key] ??= [
                    'name' => (string) ($line->name ?: $line->code),
                    'code' => (string) $line->code,
                    'employee' => 0,
                    'employer' => 0,
                ];

                $groups[$key]['employee'] += (int) $line->employee_centavos;
                $groups[$key]['employer'] += (int) $line->employer_centavos;
            }
        }

        ksort($groups);

        foreach ($groups as $group) {
            $rows[] = [
                $group['name'],
                $group['code'],
                $this->peso($group['employee']),
                $this->peso($group['employer']),
                $this->peso($group['employee'] + $group['employer']),
            ];
        }

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
     * GSIS premium working paper. A government agency remits GSIS instead of
     * SSS, so the employee's GSIS BP number is the agency identifier here.
     *
     * @param  Collection<int, PayrollDetail>  $details
     * @return array<int, array<int, string>>
     */
    /**
     * Government payroll register for COA-style review. The layout is a
     * reconciliation working paper, not the agency-prescribed form.
     *
     * @param  Collection<int, PayrollDetail>  $details
     * @return array<int, array<int, string>>
     */
    protected function coaRows(Collection $details): array
    {
        $rows = [[
            'Employee no', 'Name', 'Basic', 'Gross',
            'GSIS employee', 'GSIS employer',
            'SSS employee', 'SSS employer',
            'PhilHealth employee', 'PhilHealth employer',
            'Pag-IBIG employee', 'Pag-IBIG employer',
            'Withholding tax', 'Loans', 'Other deductions', 'Net pay',
        ]];

        foreach ($details as $detail) {
            $gsisEmployee = (int) $detail->getRawOriginal('gsis_employee');
            $sssEmployee = (int) $detail->getRawOriginal('sss_employee_total');
            $philhealthEmployee = (int) $detail->getRawOriginal('philhealth_employee');
            $hdmfEmployee = (int) $detail->getRawOriginal('hdmf_employee');
            $tax = (int) $detail->getRawOriginal('withholding_tax');
            $loans = (int) $detail->getRawOriginal('loans');
            $totalDeductions = (int) $detail->getRawOriginal('total_deductions');
            $other = max(0, $totalDeductions - $gsisEmployee - $sssEmployee - $philhealthEmployee - $hdmfEmployee - $tax - $loans);

            $rows[] = [
                (string) $detail->employee?->employee_number,
                $this->name($detail),
                $this->peso((int) $detail->getRawOriginal('basic_salary')),
                $this->peso((int) $detail->getRawOriginal('gross_income')),
                $this->peso($gsisEmployee),
                $this->peso((int) $detail->getRawOriginal('gsis_employer')),
                $this->peso($sssEmployee),
                $this->peso((int) $detail->getRawOriginal('sss_employer_total')),
                $this->peso($philhealthEmployee),
                $this->peso((int) $detail->getRawOriginal('philhealth_employer')),
                $this->peso($hdmfEmployee),
                $this->peso((int) $detail->getRawOriginal('hdmf_employer')),
                $this->peso($tax),
                $this->peso($loans),
                $this->peso($other),
                $this->peso((int) $detail->getRawOriginal('net_pay')),
            ];
        }

        return $rows;
    }

    protected function gsisRows(Collection $details): array
    {
        $rows = [['Employee no', 'Name', 'GSIS BP number', 'Employee share', 'Employer share', 'Total', 'Monthly compensation']];

        foreach ($details as $detail) {
            $employee = (int) $detail->getRawOriginal('gsis_employee');
            $employer = (int) $detail->getRawOriginal('gsis_employer');

            $rows[] = [
                (string) $detail->employee?->employee_number,
                $this->name($detail),
                (string) $detail->employee?->gsis_number,
                $this->peso($employee),
                $this->peso($employer),
                $this->peso($employee + $employer),
                $this->peso((int) $detail->getRawOriginal('basic_monthly_salary')),
            ];
        }

        return $rows;
    }

    /**
     * Loan amortizations from the deduction ledger, optionally narrowed to one
     * agency (for example `--agency=GSIS`). Loans are never statutory rows.
     *
     * @param  Collection<int, PayrollDetail>  $details
     * @return array<int, array<int, string>>
     */
    protected function loanRows(Collection $details): array
    {
        $rows = [['Employee no', 'Name', 'Loan agency', 'Loan code', 'Loan name', 'Deducted this period']];
        $agency = strtoupper(trim((string) $this->option('agency')));

        foreach ($details as $detail) {
            $employeeNo = (string) $detail->employee?->employee_number;
            $lines = $detail->deductionLines->where('source', DeductionLine::SOURCE_LOAN);

            if ($lines->isEmpty()) {
                $amount = (int) $detail->getRawOriginal('loans');

                if ($amount <= 0 || ($agency !== '' && $agency !== 'OTHER')) {
                    continue;
                }

                $rows[] = [$employeeNo, $this->name($detail), 'OTHER', 'LOAN', 'Loan', $this->peso($amount)];

                continue;
            }

            foreach ($lines as $line) {
                $code = (string) $line->code;
                $loanAgency = $this->loanAgency($code);

                if ($agency !== '' && strtoupper($loanAgency) !== $agency) {
                    continue;
                }

                $rows[] = [
                    $employeeNo,
                    $this->name($detail),
                    $loanAgency,
                    $code,
                    (string) $line->name,
                    $this->peso((int) $line->employee_centavos),
                ];
            }
        }

        return $rows;
    }

    protected function loanAgency(string $code): string
    {
        if (isset($this->loanAgencies[$code])) {
            return $this->loanAgencies[$code];
        }

        $base = preg_replace('/_\d+$/', '', $code) ?? $code;

        return $this->loanAgencies[$base] ?? 'OTHER';
    }

    /**
     * The money columns the TOTAL row sums for a format. Base amounts such as
     * the monthly salary credit are descriptive, not additive.
     *
     * @return list<int>
     */
    protected function totalColumns(string $format): array
    {
        return match ($format) {
            'sss', 'philhealth', 'pagibig' => [4, 5, 6],
            'gsis' => [3, 4, 5],
            'bir' => [4],
            'loans' => [5],
            'components' => [2, 3, 4],
            default => [],
        };
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
     * @param  list<int>  $columns
     * @return array<int, string>
     */
    protected function totalRow(array $rows, array $columns): array
    {
        $header = array_shift($rows);
        $total = array_fill(0, count($header), '');
        $total[0] = 'TOTAL';

        foreach ($rows as $row) {
            foreach ($columns as $index) {
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
