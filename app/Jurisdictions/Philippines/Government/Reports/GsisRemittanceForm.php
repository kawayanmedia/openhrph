<?php

namespace App\Jurisdictions\Philippines\Government\Reports;

use App\Models\PayrollDetail;
use App\Sectors\Contracts\RemittanceForm;
use Illuminate\Support\Collection;

/**
 * GSIS premium working paper. A government agency remits GSIS instead of SSS,
 * so the employee's GSIS BP number is the agency identifier here.
 */
final class GsisRemittanceForm implements RemittanceForm
{
    public function key(): string
    {
        return 'gsis';
    }

    public function label(): string
    {
        return 'GSIS premium';
    }

    /**
     * @param  Collection<int, PayrollDetail>  $details
     * @return array<int, array<int, string>>
     */
    public function rows(Collection $details): array
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
     * @return array<int, string>
     */
    public function totalColumns(): array
    {
        return [3, 4, 5];
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
