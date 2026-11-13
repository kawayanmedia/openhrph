<?php

namespace App\Services\Payroll\Computation;

/**
 * Exact centavo arithmetic mirroring payroll-engine/internal/money.
 *
 * Amounts are integer centavos. Rounding is half away from zero on the raw
 * float product, which is what Go's math.Round does. PHP's round() applies a
 * fuzz correction and must not be used here or the two engines drift.
 */
final class Money
{
    public const ZERO = 0;

    /**
     * Round half away from zero, matching Go's math.Round.
     */
    public static function round(float $value): int
    {
        return $value < 0
            ? -(int) floor(-$value + 0.5)
            : (int) floor($value + 0.5);
    }

    public static function fromPesos(float $pesos): int
    {
        return self::round($pesos * 100);
    }

    /**
     * Parse a decimal string such as "1234.56" into centavos.
     * Empty or invalid input yields zero.
     */
    public static function fromPesosString(?string $value): int
    {
        $value = trim((string) $value);

        if ($value === '') {
            return self::ZERO;
        }

        $value = str_replace(',', '', $value);

        if (! is_numeric($value)) {
            return self::ZERO;
        }

        return self::fromPesos((float) $value);
    }

    public static function pesos(int $centavos): float
    {
        return $centavos / 100;
    }

    /**
     * Render with two decimals, e.g. "1234.56".
     */
    public static function format(int $centavos): string
    {
        $sign = $centavos < 0 ? '-' : '';
        $value = abs($centavos);

        return sprintf('%s%d.%02d', $sign, intdiv($value, 100), $value % 100);
    }

    public static function add(int $centavos, int $other): int
    {
        return $centavos + $other;
    }

    public static function sub(int $centavos, int $other): int
    {
        return $centavos - $other;
    }

    /**
     * Scale by a factor using half-away-from-zero rounding.
     */
    public static function mul(int $centavos, float $factor): int
    {
        return self::round($centavos * $factor);
    }

    /**
     * Divide into parts, truncating toward zero and pushing the remainder
     * into the first part so the parts add back to the original amount.
     * Mirrors money.Centavos.Split.
     *
     * @return list<int>
     */
    public static function splitTruncating(int $centavos, int $parts): array
    {
        if ($parts <= 0) {
            return [];
        }

        if ($parts === 1) {
            return [$centavos];
        }

        $base = intdiv($centavos, $parts);
        $remainder = $centavos - ($base * $parts);

        $out = array_fill(0, $parts, $base);

        if ($remainder !== 0) {
            $out[0] += $remainder;
        }

        return $out;
    }

    public static function half(int $centavos): int
    {
        return self::round($centavos / 2);
    }

    /**
     * Limit the amount to the inclusive range [low, high]. A non-positive
     * high means unbounded above.
     */
    public static function clampTo(int $centavos, int $low, int $high): int
    {
        if ($high > 0 && $centavos > $high) {
            $centavos = $high;
        }

        if ($centavos < $low) {
            $centavos = $low;
        }

        return $centavos;
    }

    public static function min(int $a, int $b): int
    {
        return $a < $b ? $a : $b;
    }

    public static function max(int $a, int $b): int
    {
        return $a > $b ? $a : $b;
    }
}
