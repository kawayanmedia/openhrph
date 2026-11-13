<?php

namespace App\Services\Payroll\Computation;

use RuntimeException;

/**
 * Philippine statutory contributions and taxes.
 *
 * Every calculator is pure: rates and brackets are supplied by the caller, so
 * historical runs remain reproducible after a rate change. Mirrors
 * payroll-engine/internal/statutory.
 */
final class Statutory
{
    public const SCHEDULE_EVERY_PAY = 'every_pay';

    public const SCHEDULE_FIRST_PAY = 'first_pay';

    public const SCHEDULE_LAST_PAY = 'last_pay';

    /**
     * Map the values stored on payroll groups to a schedule. Legacy rows use
     * variants such as "everypay" or numeric cut-off ids.
     */
    public static function normalizeSchedule(?string $value): string
    {
        $normalized = strtolower(trim((string) $value));
        $normalized = str_replace(['-', ' '], '_', $normalized);

        return match ($normalized) {
            '', 'every_pay', 'everypay', '0' => self::SCHEDULE_EVERY_PAY,
            'first_pay', 'firstpay', '1' => self::SCHEDULE_FIRST_PAY,
            'last_pay', 'lastpay', '2' => self::SCHEDULE_LAST_PAY,
            default => $normalized,
        };
    }

    /**
     * How many parts of a monthly amount fall in this run.
     * A zero divisor means the deduction does not apply to this cut-off.
     */
    public static function divisor(?string $schedule, ?string $frequency, ?string $cutoff): int
    {
        $schedule = (string) $schedule;

        if ($schedule === '' || $schedule === self::SCHEDULE_EVERY_PAY) {
            return $frequency === Config::FREQUENCY_SEMI_MONTHLY ? 2 : 1;
        }

        return $schedule === (string) $cutoff ? 1 : 0;
    }

    /**
     * Select the monthly bracket containing the salary.
     *
     * @param  list<SssBracket>  $brackets
     */
    public static function computeSss(int $monthlyBasic, array $brackets): SssResult
    {
        if ($brackets === []) {
            throw new RuntimeException('sss: no statutory bracket matches the salary');
        }

        foreach ($brackets as $bracket) {
            if ($monthlyBasic >= $bracket->mscFrom && ($bracket->mscTo === 0 || $monthlyBasic <= $bracket->mscTo)) {
                return new SssResult(
                    msc: $bracket->msc,
                    ee: $bracket->ee,
                    er: $bracket->er,
                    ec: $bracket->ec,
                    wispEE: $bracket->wispEE,
                    wispER: $bracket->wispER,
                );
            }
        }

        throw new RuntimeException(sprintf(
            'sss: no statutory bracket matches the salary (basic %s)',
            Money::format($monthlyBasic),
        ));
    }

    /**
     * Build one row of the 2025 schedule from its salary credit. The 2025
     * schedule is 15% total: 5% employee and 10% employer, with the regular
     * MSC capped at ₱20,000 and the excess handled by WISP/MPF.
     */
    public static function sss2025Bracket(int $from, int $to, int $msc): SssBracket
    {
        $cap = Money::fromPesos(20000);
        $regular = Money::min($msc, $cap);
        $wisp = $msc > $cap ? $msc - $cap : 0;

        return new SssBracket(
            mscFrom: $from,
            mscTo: $to,
            msc: $msc,
            ee: Money::mul($regular, 0.05),
            er: Money::mul($regular, 0.10),
            ec: self::sssEmployeesCompensation($msc),
            wispEE: Money::mul($wisp, 0.05),
            wispER: Money::mul($wisp, 0.10),
        );
    }

    /**
     * The employer EC contribution for a salary credit: ₱10 up to ₱14,500 and
     * ₱30 from ₱15,000 (SSS Circular No. 2024-006).
     */
    public static function sssEmployeesCompensation(int $msc): int
    {
        return $msc >= Money::fromPesos(15000)
            ? Money::fromPesos(30)
            : Money::fromPesos(10);
    }

    /**
     * The 2025 SSS schedule: salary credit from ₱5,000 to ₱35,000 in ₱500
     * steps. Each band is billed from half a step below its credit.
     *
     * @return list<SssBracket>
     */
    public static function sss2025Schedule(): array
    {
        $step = Money::fromPesos(500);
        $halfStep = Money::fromPesos(250);
        $floor = Money::fromPesos(5000);
        $ceiling = Money::fromPesos(35000);

        $brackets = [
            self::sss2025Bracket(0, $floor + $halfStep - 1, $floor),
        ];

        for ($msc = $floor + $step; $msc < $ceiling; $msc += $step) {
            $from = $msc - $halfStep;
            $brackets[] = self::sss2025Bracket($from, $from + $step - 1, $msc);
        }

        $brackets[] = self::sss2025Bracket($ceiling - $halfStep, 0, $ceiling);

        return $brackets;
    }

    /**
     * The 5% schedule with a ₱10,000 floor and ₱100,000 ceiling.
     */
    public static function defaultPhilHealthConfig(): PhilHealthConfig
    {
        return new PhilHealthConfig(
            rate: 0.05,
            floor: Money::fromPesos(10000),
            ceiling: Money::fromPesos(100000),
        );
    }

    /**
     * Split the premium 50/50 between employee and employer.
     */
    public static function computePhilHealth(int $monthlyBasic, PhilHealthConfig $config): PhilHealthResult
    {
        $base = Money::clampTo($monthlyBasic, $config->floor, $config->ceiling);
        $premium = Money::mul($base, $config->rate);
        $employee = Money::half($premium);

        return new PhilHealthResult(
            base: $base,
            premium: $premium,
            employee: $employee,
            employer: $premium - $employee,
        );
    }

    /**
     * The 2% schedule capped at a ₱10,000 fund salary (₱200 maximum a side).
     */
    public static function defaultPagIbigConfig(): PagIbigConfig
    {
        return new PagIbigConfig(
            rate: 0.02,
            lowRate: 0.01,
            lowThreshold: Money::fromPesos(1500),
            maxFundSalary: Money::fromPesos(10000),
        );
    }

    /**
     * Apply the fund-salary cap before computing the shares.
     */
    public static function computePagIbig(int $monthlyBasic, PagIbigConfig $config): PagIbigResult
    {
        $base = Money::min($monthlyBasic, $config->maxFundSalary);

        $rate = $config->rate;

        if ($monthlyBasic <= $config->lowThreshold && $config->lowRate > 0) {
            $rate = $config->lowRate;
        }

        return new PagIbigResult(
            base: $base,
            employee: Money::mul($base, $rate),
            employer: Money::mul($base, $config->rate),
        );
    }

    /**
     * The withholding tax for a taxable amount.
     *
     * @param  list<TaxBracket>  $brackets
     */
    public static function computeTax(int $taxable, array $brackets): int
    {
        if ($brackets === []) {
            throw new RuntimeException('tax: no statutory bracket matches the salary');
        }

        foreach ($brackets as $bracket) {
            if ($taxable >= $bracket->from && ($bracket->to === 0 || $taxable <= $bracket->to)) {
                if ($taxable <= $bracket->from) {
                    return $bracket->baseTax;
                }

                return Money::add($bracket->baseTax, Money::mul($taxable - $bracket->from, $bracket->rate));
            }
        }

        throw new RuntimeException(sprintf(
            'tax: no statutory bracket matches the salary (taxable %s)',
            Money::format($taxable),
        ));
    }

    /**
     * The BIR withholding table effective 2023 onwards (TRAIN law).
     *
     * @return list<TaxBracket>
     */
    public static function trainTaxBrackets(string $frequency): array
    {
        if ($frequency === Config::FREQUENCY_SEMI_MONTHLY) {
            return [
                new TaxBracket(from: 0, to: Money::fromPesos(10416.99), baseTax: 0, rate: 0),
                new TaxBracket(from: Money::fromPesos(10417), to: Money::fromPesos(16666.99), baseTax: 0, rate: 0.15),
                new TaxBracket(from: Money::fromPesos(16667), to: Money::fromPesos(33332.99), baseTax: Money::fromPesos(937.50), rate: 0.20),
                new TaxBracket(from: Money::fromPesos(33333), to: Money::fromPesos(83332.99), baseTax: Money::fromPesos(4270.70), rate: 0.25),
                new TaxBracket(from: Money::fromPesos(83333), to: Money::fromPesos(333332.99), baseTax: Money::fromPesos(16770.70), rate: 0.30),
                new TaxBracket(from: Money::fromPesos(333333), to: 0, baseTax: Money::fromPesos(91770.70), rate: 0.35),
            ];
        }

        return [
            new TaxBracket(from: 0, to: Money::fromPesos(20832.99), baseTax: 0, rate: 0),
            new TaxBracket(from: Money::fromPesos(20833), to: Money::fromPesos(33332.99), baseTax: 0, rate: 0.15),
            new TaxBracket(from: Money::fromPesos(33333), to: Money::fromPesos(66666.99), baseTax: Money::fromPesos(1875.00), rate: 0.20),
            new TaxBracket(from: Money::fromPesos(66667), to: Money::fromPesos(166666.99), baseTax: Money::fromPesos(8541.80), rate: 0.25),
            new TaxBracket(from: Money::fromPesos(166667), to: Money::fromPesos(666666.99), baseTax: Money::fromPesos(33541.80), rate: 0.30),
            new TaxBracket(from: Money::fromPesos(666667), to: 0, baseTax: Money::fromPesos(183541.80), rate: 0.35),
        ];
    }

    public static function belowMinimum(int $dailyRate, MinimumWage $wage): bool
    {
        return $wage->amount > 0 && $dailyRate < $wage->amount;
    }

    /**
     * Map payroll_groups.frequency to a frequency key.
     */
    public static function normalizeFrequency(?string $value): string
    {
        return in_array(strtolower(trim((string) $value)), ['monthly', 'month'], true)
            ? Config::FREQUENCY_MONTHLY
            : Config::FREQUENCY_SEMI_MONTHLY;
    }
}
