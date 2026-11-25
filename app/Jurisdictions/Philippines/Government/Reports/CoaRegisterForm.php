<?php

namespace App\Jurisdictions\Philippines\Government\Reports;

use App\Models\PayrollDetail;
use App\Sectors\Contracts\RemittanceForm;
use Illuminate\Support\Collection;

/**
 * Government payroll register for COA-style review. The layout is a
 * reconciliation working paper, not the agency-prescribed form.
 */
final class CoaRegisterForm implements RemittanceForm
{
    public function key(): string
    {
        return 'coa';
    }

    public function label(): string
    {
        return 'COA payroll register';
    }

    /**
     * @param  Collection<int, PayrollDetail>  $details
     * @return array<int, array<int, string>>
     */
    public function rows(Collection $details): array
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

    /**
     * @return array<int, string>
     */
    public function totalColumns(): array
    {
        return [];
    }

    private function name(PayrollDetail $detail): string
    {
        return trim(($detail->employee?->first_name ?? '').' '.($detail->employee?->last_name ?? ''));
    }

    private function peso(int $centavos): string
    {
        return number_format($centavos / 100, 2, '.', '');
    }
}
