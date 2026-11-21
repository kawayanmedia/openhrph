<?php

namespace App\Services\Payroll\Engine\Legacy;

/**
 * Reads the hours worked on a timesheet row that only has punches.
 *
 * The attendance pipeline records in/out pairs (and mirrors them to the
 * am/pm columns) before payroll computes the actual_* buckets. When a run
 * happens before that computation, falling back to the punches stops the day
 * from being paid as zero.
 */
final class WorkedHours
{
    /**
     * Hours from an in/out punch list. Overnight pairs (out earlier than in)
     * are treated as crossing midnight.
     *
     * @param  array<int, mixed>|null  $logs
     */
    public static function fromLogs(?array $logs): float
    {
        if ($logs === null || $logs === []) {
            return 0.0;
        }

        $minutes = 0.0;
        $startedAt = null;

        foreach ($logs as $log) {
            if (! is_array($log)) {
                continue;
            }

            $time = self::clock($log['time'] ?? null);

            if ($time === null) {
                continue;
            }

            if ((int) ($log['log_type'] ?? 0) === 0) {
                $startedAt = $time;

                continue;
            }

            if ($startedAt !== null) {
                $minutes += self::minutesBetween($startedAt, $time);
                $startedAt = null;
            }
        }

        return round($minutes / 60, 2);
    }

    /**
     * Hours from the mirrored am/pm columns, matching the legacy timesheet
     * calculation for rows recorded before the punch list existed.
     */
    public static function fromColumns(
        ?string $amLogin,
        ?string $amLogout,
        ?string $pmLogin,
        ?string $pmLogout,
        ?string $amOutMorning,
    ): float {
        $minutes = 0.0;

        foreach ([[$amLogin, $amLogout], [$pmLogin, $pmLogout]] as [$in, $out]) {
            $inTime = self::clock($in);
            $outTime = self::clock($out);

            if ($inTime !== null && $outTime !== null) {
                $minutes += self::minutesBetween($inTime, $outTime);
            }
        }

        $pm = self::clock($pmLogin);
        $morning = self::clock($amOutMorning);

        if ($pm !== null && $morning !== null) {
            $minutes += self::minutesBetween($pm, $morning);
        }

        return round($minutes / 60, 2);
    }

    private static function clock(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        if (preg_match('/^(\d{1,2}):(\d{2})/', $value, $matches) !== 1) {
            return null;
        }

        $hour = (int) $matches[1];
        $minute = (int) $matches[2];

        if ($hour > 23 || $minute > 59) {
            return null;
        }

        return sprintf('%02d:%02d', $hour, $minute);
    }

    private static function minutesBetween(string $from, string $to): float
    {
        $fromMinutes = self::toMinutes($from);
        $toMinutes = self::toMinutes($to);

        if ($toMinutes < $fromMinutes) {
            $toMinutes += 24 * 60;
        }

        return max(0, $toMinutes - $fromMinutes);
    }

    private static function toMinutes(string $clock): int
    {
        [$hour, $minute] = array_map('intval', explode(':', $clock));

        return ($hour * 60) + $minute;
    }
}
