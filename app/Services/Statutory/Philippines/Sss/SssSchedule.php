<?php

namespace App\Services\Statutory\Philippines\Sss;

use App\Support\Money;

/**
 * Builders for the SSS contribution schedule.
 */
final class SssSchedule
{
    /**
     * The 2025 SSS schedule: salary credit from ₱5,000 to ₱35,000 in ₱500
     * steps. Each band is billed from half a step below its credit.
     *
     * @return list<SssBracket>
     */
    public static function preset2025(): array
    {
        $step = Money::fromPesos(500);
        $halfStep = Money::fromPesos(250);
        $floor = Money::fromPesos(5000);
        $ceiling = Money::fromPesos(35000);

        $brackets = [
            self::bracket(0, $floor + $halfStep - 1, $floor),
        ];

        for ($msc = $floor + $step; $msc < $ceiling; $msc += $step) {
            $from = $msc - $halfStep;
            $brackets[] = self::bracket($from, $from + $step - 1, $msc);
        }

        $brackets[] = self::bracket($ceiling - $halfStep, 0, $ceiling);

        return $brackets;
    }

    /**
     * Build one row of the 2025 schedule from its salary credit. The 2025
     * schedule is 15% total: 5% employee and 10% employer, with the regular
     * MSC capped at ₱20,000 and the excess handled by WISP/MPF.
     */
    public static function bracket(int $from, int $to, int $msc): SssBracket
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
            ec: self::employeesCompensation($msc),
            wispEE: Money::mul($wisp, 0.05),
            wispER: Money::mul($wisp, 0.10),
        );
    }

    /**
     * The employer EC contribution for a salary credit: ₱10 up to ₱14,500 and
     * ₱30 from ₱15,000 (SSS Circular No. 2024-006).
     */
    public static function employeesCompensation(int $msc): int
    {
        return $msc >= Money::fromPesos(15000)
            ? Money::fromPesos(30)
            : Money::fromPesos(10);
    }
}
