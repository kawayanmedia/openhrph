<?php

namespace App\Services\Attendance;

/**
 * Minute-based interval helpers used by the attendance engine.
 *
 * Intervals are `[start, end]` pairs in minutes. They may exceed 1440 when a
 * shift or punch crosses midnight, which keeps overnight work in one
 * coordinate space instead of special-casing the next calendar day.
 */
final class TimeInterval
{
    /**
     * @param  list<array{0: int, 1: int}>  $intervals
     */
    public static function sum(array $intervals): int
    {
        $total = 0;

        foreach ($intervals as [$start, $end]) {
            if ($end > $start) {
                $total += $end - $start;
            }
        }

        return $total;
    }

    /**
     * Remove unpaid breaks from a set of working intervals.
     *
     * @param  list<array{0: int, 1: int}>  $intervals
     * @param  list<array{0: int, 1: int}>  $breaks
     * @return list<array{0: int, 1: int}>
     */
    public static function subtract(array $intervals, array $breaks): array
    {
        $result = $intervals;

        foreach ($breaks as [$breakStart, $breakEnd]) {
            if ($breakEnd <= $breakStart) {
                continue;
            }

            $next = [];

            foreach ($result as [$start, $end]) {
                if ($breakEnd <= $start || $breakStart >= $end) {
                    $next[] = [$start, $end];

                    continue;
                }

                if ($breakStart > $start) {
                    $next[] = [$start, $breakStart];
                }

                if ($breakEnd < $end) {
                    $next[] = [$breakEnd, $end];
                }
            }

            $result = self::normalize($next);
        }

        return $result;
    }

    /**
     * Total overlap, in minutes, between two interval sets.
     *
     * @param  list<array{0: int, 1: int}>  $left
     * @param  list<array{0: int, 1: int}>  $right
     */
    public static function overlap(array $left, array $right): int
    {
        $minutes = 0;

        foreach ($left as [$leftStart, $leftEnd]) {
            foreach ($right as [$rightStart, $rightEnd]) {
                $minutes += max(0, min($leftEnd, $rightEnd) - max($leftStart, $rightStart));
            }
        }

        return $minutes;
    }

    /**
     * The last N minutes of a set of intervals, returned as intervals.
     *
     * @param  list<array{0: int, 1: int}>  $intervals
     * @return list<array{0: int, 1: int}>
     */
    public static function tail(array $intervals, int $minutes): array
    {
        if ($minutes <= 0) {
            return [];
        }

        $intervals = self::normalize($intervals);
        $remaining = $minutes;
        $tail = [];

        for ($index = count($intervals) - 1; $index >= 0 && $remaining > 0; $index--) {
            [$start, $end] = $intervals[$index];
            $length = $end - $start;

            if ($length <= 0) {
                continue;
            }

            $take = min($length, $remaining);
            $tail[] = [$end - $take, $end];
            $remaining -= $take;
        }

        return array_reverse(array_map(
            fn (array $interval): array => [$interval[0], $interval[1]],
            $tail,
        ));
    }

    /**
     * @param  list<array{0: int, 1: int}>  $intervals
     * @return list<array{0: int, 1: int}>
     */
    public static function normalize(array $intervals): array
    {
        $intervals = array_values(array_filter(
            $intervals,
            fn (array $interval): bool => $interval[1] > $interval[0],
        ));

        usort($intervals, fn (array $a, array $b): int => $a[0] <=> $b[0]);

        $merged = [];

        foreach ($intervals as [$start, $end]) {
            $last = count($merged) - 1;

            if ($last >= 0 && $start <= $merged[$last][1]) {
                $merged[$last][1] = max($merged[$last][1], $end);

                continue;
            }

            $merged[] = [$start, $end];
        }

        return $merged;
    }
}
