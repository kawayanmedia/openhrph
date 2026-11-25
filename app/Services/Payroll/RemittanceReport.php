<?php

namespace App\Services\Payroll;

use App\Models\Payroll;
use App\Models\PayrollDetail;
use App\Sectors\Contracts\RemittanceForm;
use App\Sectors\SectorManager;
use App\Services\Payroll\Computation\DeductionLine;
use App\Support\Installation\Installation;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Builds the statutory remittance working papers for a payroll.
 *
 * The Artisan command and the admin report centre both use this service so a
 * downloaded file always matches the command-line reconciliation.
 */
final class RemittanceReport
{
    /** @var array<string, string> loan code to agency */
    private array $loanAgencies = [];

    /** @var array<int, array<string, RemittanceForm>> */
    private array $sectorForms = [];

    /**
     * Remittance formats supported by the active country.
     *
     * @return array<string, string>
     */
    public function formats(): array
    {
        return Installation::jurisdiction()->reports()->remittanceFormats();
    }

    /**
     * The remittance set the company's sector actually files.
     *
     * @return array<int, string>
     */
    public function sectorFormats(Payroll $payroll): array
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

        return array_values(array_unique(array_merge(['summary'], $formats)));
    }

    /**
     * The payroll detail rows, loaded with the relationships the reports need.
     *
     * @return Collection<int, PayrollDetail>
     */
    public function details(Payroll $payroll): Collection
    {
        return $this->withinPayroll(
            $payroll,
            fn (Collection $details): Collection => $details,
        );
    }

    /**
     * Employee-level working-paper rows for a format (without a TOTAL row).
     *
     * @param  array{agency?: string|null, control_totals?: bool}  $options
     * @return array<int, array<int, string>>
     */
    public function rows(Payroll $payroll, string $format, array $options = []): array
    {
        return $this->withinPayroll($payroll, function (Collection $details) use ($payroll, $format, $options): array {
            return $this->rowsFor($format, $details, $options, (int) $payroll->company_id);
        });
    }

    /**
     * The CSV body for a format, including the control-total row by default.
     *
     * @param  array{agency?: string|null, control_totals?: bool}  $options
     */
    public function csv(Payroll $payroll, string $format, array $options = []): string
    {
        return $this->withinPayroll($payroll, function (Collection $details) use ($payroll, $format, $options): string {
            $rows = $this->rowsFor($format, $details, $options, (int) $payroll->company_id);
            $controlTotals = $options['control_totals'] ?? true;

            if ($controlTotals && $format !== 'summary') {
                $rows[] = $this->totalRow($rows, $this->totalColumns($format, (int) $payroll->company_id));
            }

            return $this->toCsv($rows);
        });
    }

    /**
     * Agency totals for the remittance register.
     *
     * @return array<int, array{code: string, agency: string, employee: int, employer: int, total: int}>
     */
    public function agencySummary(Payroll $payroll): array
    {
        return $this->withinPayroll($payroll, function (Collection $details): array {
            $rows = [];

            $agencies = [
                'sss' => ['label' => 'SSS', 'employee' => 'sss_employee_total', 'employer' => 'sss_employer_total'],
                'gsis' => ['label' => 'GSIS', 'employee' => 'gsis_employee', 'employer' => 'gsis_employer'],
                'philhealth' => ['label' => 'PhilHealth', 'employee' => 'philhealth_employee', 'employer' => 'philhealth_employer'],
                'pagibig' => ['label' => 'Pag-IBIG', 'employee' => 'hdmf_employee', 'employer' => 'hdmf_employer'],
                'bir' => ['label' => 'BIR', 'employee' => 'withholding_tax', 'employer' => null],
            ];

            foreach ($agencies as $code => $definition) {
                $employee = $this->sum($details, $definition['employee']);
                $employer = $definition['employer'] === null ? 0 : $this->sum($details, $definition['employer']);

                if ($employee === 0 && $employer === 0) {
                    continue;
                }

                $rows[] = [
                    'code' => $code,
                    'agency' => $definition['label'],
                    'employee' => $employee,
                    'employer' => $employer,
                    'total' => $employee + $employer,
                ];
            }

            return $rows;
        });
    }

    /**
     * Run a callback with the payroll tenant active and the loan lookup loaded.
     *
     * @template TReturn
     *
     * @param  Closure(Collection<int, PayrollDetail>): TReturn  $callback
     * @return TReturn
     */
    private function withinPayroll(Payroll $payroll, Closure $callback): mixed
    {
        return app(TenantContext::class)->run((int) $payroll->company_id, function () use ($payroll, $callback): mixed {
            $this->loanAgencies = $this->loanAgenciesFor((int) $payroll->company_id);

            $details = $payroll->payrollDetails()
                ->with(['employee', 'deductionLines'])
                ->get();

            return $callback($details);
        });
    }

    /**
     * @return array<string, string>
     */
    private function loanAgenciesFor(int $companyId): array
    {
        if (! Schema::hasTable('loans')) {
            return [];
        }

        return DB::table('loans')
            ->where('company_id', $companyId)
            ->pluck('loan_agency', 'code')
            ->map(fn ($agency): string => (string) $agency)
            ->all();
    }

    /**
     * @param  Collection<int, PayrollDetail>  $details
     * @param  array{agency?: string|null, control_totals?: bool}  $options
     * @return array<int, array<int, string>>
     */
    private function rowsFor(string $format, Collection $details, array $options, int $companyId): array
    {
        if ($form = $this->sectorForm($format, $companyId)) {
            return $form->rows($details);
        }

        return match ($format) {
            'sss' => $this->sssRows($details),
            'philhealth' => $this->philhealthRows($details),
            'pagibig' => $this->pagibigRows($details),
            'bir' => $this->birRows($details),
            'loans' => $this->loanRows($details, $options['agency'] ?? null),
            'components' => $this->componentRows($details),
            default => $this->summaryRows($details),
        };
    }

    /**
     * @param  Collection<int, PayrollDetail>  $details
     * @return array<int, array<int, string>>
     */
    private function summaryRows(Collection $details): array
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
    private function componentRows(Collection $details): array
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
    private function sssRows(Collection $details): array
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
    private function philhealthRows(Collection $details): array
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
    private function pagibigRows(Collection $details): array
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
     * Loan amortizations from the deduction ledger, optionally narrowed to one
     * agency (for example `--agency=GSIS`). Loans are never statutory rows.
     *
     * @param  Collection<int, PayrollDetail>  $details
     * @return array<int, array<int, string>>
     */
    private function loanRows(Collection $details, ?string $agency = null): array
    {
        $rows = [['Employee no', 'Name', 'Loan agency', 'Loan code', 'Loan name', 'Deducted this period']];
        $agency = (string) $agency |> trim(...) |> strtoupper(...);

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

    private function loanAgency(string $code): string
    {
        if (isset($this->loanAgencies[$code])) {
            return $this->loanAgencies[$code];
        }

        $base = preg_replace('/_\d+$/', '', $code) ?? $code;

        return $this->loanAgencies[$base] ?? 'OTHER';
    }

    /**
     * The remittance form the active sector contributes for this format.
     */
    private function sectorForm(string $format, int $companyId): ?RemittanceForm
    {
        if (! isset($this->sectorForms[$companyId])) {
            $forms = [];

            foreach (app(SectorManager::class)->forCompany($companyId)->featurePack()->remittanceForms() as $key => $class) {
                if (is_string($class) && class_exists($class)) {
                    $forms[$key] = new $class;
                }
            }

            $this->sectorForms[$companyId] = $forms;
        }

        return $this->sectorForms[$companyId][$format] ?? null;
    }

    /**
     * The money columns the TOTAL row sums for a format. Base amounts such as
     * the monthly salary credit are descriptive, not additive.
     *
     * @return list<int>
     */
    private function totalColumns(string $format, int $companyId): array
    {
        if ($form = $this->sectorForm($format, $companyId)) {
            return $form->totalColumns();
        }

        return match ($format) {
            'sss', 'philhealth', 'pagibig' => [4, 5, 6],
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
    private function birRows(Collection $details): array
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
    private function totalRow(array $rows, array $columns): array
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
    private function sum(Collection $details, string $column): int
    {
        return (int) $details->sum(fn ($detail) => (int) $detail->getRawOriginal($column));
    }

    private function name(PayrollDetail $detail): string
    {
        return trim(($detail->employee?->first_name ?? '').' '.($detail->employee?->last_name ?? ''));
    }

    /**
     * @param  array<int, array<int, string>>  $rows
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

    private function peso(int $centavos): string
    {
        return number_format($centavos / 100, 2, '.', '');
    }
}
