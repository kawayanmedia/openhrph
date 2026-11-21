<?php

namespace App\Services\Payroll;

use App\Models\PayrollDetail;
use App\Services\Payroll\Computation\Computer;
use App\Services\Payroll\Computation\Config;
use App\Services\Payroll\Computation\Earning;
use App\Services\Payroll\Computation\Employee;
use App\Services\Payroll\Engine\Legacy\LegacyInput;
use App\Support\Installation\Installation;
use Throwable;

/**
 * Debug-only breakdown of how each earning on a payslip was taxed.
 *
 * Rows are built from the run inputs so the de minimis ceilings and
 * `compensations.maximum_non_tax` values the engine actually applied are the
 * ones shown. When the inputs cannot be resolved the report falls back to the
 * persisted detail, where only the itemised compensations carry a tax flag.
 */
final class EarningTaxReport
{
    /**
     * @return array{
     *     from_inputs: bool,
     *     rows: list<array{label: string, amount: float, taxable: ?bool, exempt: float, ceiling: ?float, basis: string, source: string, excess: float}>,
     *     summary: array{gross: float, non_taxable: float, benefit_exempt: float, minimum_wage_exemption: float, taxable: float},
     *     note: string
     * }
     */
    public static function forPayrollDetail(PayrollDetail $detail): array
    {
        try {
            $inputs = LegacyInput::load((int) $detail->payroll_id);

            foreach ($inputs->employees as $employee) {
                if ($employee->id !== (int) $detail->employee_id) {
                    continue;
                }

                return [
                    'from_inputs' => true,
                    'rows' => self::rows($inputs->config, $employee, $detail),
                    'summary' => self::summary($detail),
                    'note' => '',
                ];
            }
        } catch (Throwable) {
            // Fall through to the persisted view; the payslip itself is unaffected.
        }

        return [
            'from_inputs' => false,
            'rows' => self::persistedRows($detail),
            'summary' => self::summary($detail),
            'note' => 'Payroll inputs are not available for this run, so per-line ceilings cannot be resolved. Showing persisted lines only.',
        ];
    }

    /**
     * @return list<array{label: string, amount: float, taxable: ?bool, exempt: float, ceiling: ?float, basis: string, source: string, excess: float}>
     */
    public static function rows(Config $cfg, Employee $employee, PayrollDetail $detail): array
    {
        $periodUsage = self::periodUsage($employee);
        $rows = [];

        foreach (self::fixedRows($detail) as $label => $amount) {
            $rows[] = self::row($label, $amount, taxable: true);
        }

        foreach ([$employee->allowances, $employee->compensations, $employee->bonuses, $employee->commissions] as $earnings) {
            foreach ($earnings as $earning) {
                $rows[] = self::earningRow($cfg, $employee, $earning, $periodUsage);
            }
        }

        foreach (self::benefitRows($cfg, $employee, $detail) as $row) {
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * @return list<array{label: string, amount: float, taxable: ?bool, exempt: float, ceiling: ?float, basis: string, source: string, excess: float}>
     */
    public static function persistedRows(PayrollDetail $detail): array
    {
        $rows = [];

        foreach (self::fixedRows($detail) as $label => $amount) {
            $rows[] = self::row($label, $amount, taxable: true);
        }

        foreach ($detail->compensations ?? [] as $line) {
            $amount = ((int) ($line['amount'] ?? 0)) / 100;
            $taxable = (bool) ($line['taxable'] ?? true);
            $ceiling = (int) ($line['non_taxable_ceiling'] ?? 0);

            $rows[] = $ceiling > 0
                ? self::row(
                    (string) ($line['name'] ?? ''),
                    $amount,
                    taxable: true,
                    exempt: min($amount, $ceiling / 100),
                    ceiling: $ceiling / 100,
                    basis: 'catalogue',
                    source: 'compensations.maximum_non_tax',
                )
                : self::row((string) ($line['name'] ?? ''), $amount, $taxable);
        }

        foreach ([
            'Allowances (total)' => (float) $detail->allowance,
            'Bonus (total)' => (float) $detail->bonus,
            'Commission (total)' => (float) $detail->commission,
        ] as $label => $amount) {
            if (round($amount, 2) !== 0.0) {
                $rows[] = self::row($label, $amount, taxable: null, source: 'inputs unavailable');
            }
        }

        return array_values(array_merge($rows, self::benefitRows(null, null, $detail)));
    }

    /**
     * The exemption already consumed in each benefit period before this
     * payroll. The loader takes this payroll's own consumption back out of the
     * ledger, so the loaded totals are already the starting point.
     *
     * @return array<string, int>
     */
    private static function periodUsage(Employee $employee): array
    {
        return array_map(static fn ($used): int => (int) $used, $employee->ytd->deMinimis);
    }

    /**
     * @param  array<string, int>  $periodUsage
     * @return array{label: string, amount: float, taxable: ?bool, exempt: float, ceiling: ?float, basis: string, source: string, excess: float}
     */
    private static function earningRow(Config $cfg, Employee $employee, Earning $earning, array &$periodUsage): array
    {
        $treatment = Computer::earningTaxTreatment($cfg, $employee, $earning, $periodUsage);
        $label = trim($earning->name) !== '' ? $earning->name : $earning->code;

        if ($treatment['key'] !== null) {
            $periodUsage[$treatment['key']] = (int) ($periodUsage[$treatment['key']] ?? 0) + $treatment['exempt'];
        }

        return [
            'label' => $label,
            'amount' => self::pesos($earning->amount),
            'taxable' => $treatment['taxable'],
            'exempt' => self::pesos($treatment['exempt']),
            'ceiling' => $treatment['ceiling'] === null ? null : self::pesos($treatment['ceiling']),
            'basis' => $treatment['basis'],
            'source' => $treatment['source'],
            'excess' => self::pesos($treatment['excess']),
        ];
    }

    /**
     * Earnings the engine settles from the period's basic pay rather than from
     * a compensation row: 13th month, leave conversion and separation pay.
     *
     * @return list<array{label: string, amount: float, taxable: ?bool, exempt: float, ceiling: ?float, basis: string, source: string, excess: float}>
     */
    private static function benefitRows(?Config $cfg, ?Employee $employee, PayrollDetail $detail): array
    {
        $rows = [];

        $thirteenth = (float) $detail->thirteenth_month;

        if (round($thirteenth, 2) !== 0.0) {
            $amount = (int) round($thirteenth * 100);
            $otherBenefits = $employee?->ytd->otherBenefits ?? 0;
            $policy = $cfg?->policy ?? Installation::jurisdiction()->payrollPolicy();
            $ceiling = $policy->thirteenthMonthExemptCentavos();
            $taxablePart = Computer::incrementalBenefitExcess($amount, $otherBenefits, $policy);
            $remaining = max(0, $ceiling - $otherBenefits);

            $rows[] = self::row(
                '13th month',
                $thirteenth,
                taxable: true,
                exempt: self::pesos($amount - $taxablePart),
                ceiling: self::pesos($remaining),
                basis: 'annual',
                source: '₱90,000 13th month and other benefits exclusion',
            );
        }

        $leave = (float) $detail->leave_conversion;

        if (round($leave, 2) !== 0.0) {
            $rows[] = self::row('Leave conversion', $leave, taxable: true);
        }

        $separation = (float) $detail->separation_pay;

        if (round($separation, 2) !== 0.0) {
            $taxable = $cfg === null
                ? null
                : Computer::separationIsTaxable((string) ($cfg->meta['separation_reason'] ?? ''));

            $rows[] = self::row('Separation pay', $separation, taxable: $taxable);
        }

        return $rows;
    }

    /**
     * @return array<string, float>
     */
    private static function fixedRows(PayrollDetail $detail): array
    {
        $basic = (float) $detail->basic_salary;

        return array_filter([
            'Basic pay (period)' => $basic,
            'Attendance and premiums' => (float) $detail->regular_pay - $basic,
            'Overtime' => (float) $detail->overtime_pay,
            'Night differential' => (float) $detail->night_pay,
            'Rest day' => (float) $detail->rest_day_pay,
            'Holiday' => (float) $detail->holiday_pay,
        ], static fn (float $amount): bool => round($amount, 2) !== 0.0);
    }

    /**
     * @return array{label: string, amount: float, taxable: ?bool, exempt: float, ceiling: ?float, basis: string, source: string, excess: float}
     */
    private static function row(
        string $label,
        float $amount,
        ?bool $taxable,
        float $exempt = 0.0,
        ?float $ceiling = null,
        string $basis = '',
        string $source = '',
    ): array {
        return [
            'label' => $label,
            'amount' => round($amount, 2),
            'taxable' => $taxable,
            'exempt' => round($exempt, 2),
            'ceiling' => $ceiling === null ? null : round($ceiling, 2),
            'basis' => $basis,
            'source' => $source,
            'excess' => $ceiling === null ? 0.0 : round(max(0.0, $amount - $exempt), 2),
        ];
    }

    /**
     * @return array{gross: float, non_taxable: float, benefit_exempt: float, minimum_wage_exemption: float, taxable: float}
     */
    private static function summary(PayrollDetail $detail): array
    {
        return [
            'gross' => (float) $detail->gross_income,
            'non_taxable' => (float) $detail->non_taxable_income,
            'benefit_exempt' => ((int) $detail->getRawOriginal('benefit_exempt')) / 100,
            'minimum_wage_exemption' => (float) $detail->minimum_wage_exemption,
            'taxable' => (float) $detail->taxable_income,
        ];
    }

    private static function pesos(int $centavos): float
    {
        return $centavos / 100;
    }
}
