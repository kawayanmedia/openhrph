<?php

namespace App\Services\Attendance;

use App\Models\Rate;
use App\Models\Timesheet;
use Carbon\CarbonImmutable;

/**
 * Derives the payable hour buckets from raw punches.
 *
 * The engine is the single source of truth for attendance hours. It works in
 * minutes, deducts an unpaid break exactly once, and keeps the buckets
 * disjoint in the way payroll expects:
 *
 *  - actual_regular: ordinary paid hours, capped at the scheduled paid minutes
 *  - actual_ot:      overtime hours, including night overtime
 *  - actual_nt:      all night hours (ordinary + overtime)
 *  - actual_nt_ot:   the night subset of overtime
 *
 * `rule_version = 2` tells the payroll mapper that overtime already includes
 * the night portion, so it must not add `actual_nt_ot` a second time. Legacy
 * rows keep their old meaning.
 */
final class TimesheetCalculator
{
    public const RULE_VERSION = 2;

    public function __construct(private readonly WorkScheduleResolver $resolver) {}

    public function compute(Timesheet $timesheet): Timesheet
    {
        if ((bool) $timesheet->is_locked) {
            return $timesheet;
        }

        $workday = $this->resolver->resolve($timesheet->employee, $timesheet->log_date, $timesheet);
        $pairs = $this->pairs($timesheet, $workday);

        $paid = [];

        foreach ($pairs as [$start, $end]) {
            $paid = array_merge($paid, TimeInterval::subtract([[$start, $end]], $workday->breaks));
        }

        $paid = TimeInterval::normalize($paid);
        $paidMinutes = TimeInterval::sum($paid);
        $scheduledMinutes = $workday->scheduledMinutes();

        $regularMinutes = min($paidMinutes, $scheduledMinutes);
        $overtimeMinutes = max(0, $paidMinutes - $regularMinutes);

        // Approved overtime may exceed what the punches show (work performed
        // without a punch, or an approval covering the whole request). Pay the
        // larger of the two so a recompute cannot erase an approval and an
        // approval cannot be added twice.
        $approvedOtMinutes = max(0, (int) ($timesheet->getAttribute('approved_ot_minutes') ?? 0));
        $payableOvertimeMinutes = max($overtimeMinutes, $approvedOtMinutes);

        $nightMinutes = TimeInterval::overlap($paid, $workday->nightIntervals());
        $nightOvertimeMinutes = TimeInterval::overlap(
            TimeInterval::tail($paid, $overtimeMinutes),
            $workday->nightIntervals(),
        );

        $lateMinutes = $workday->isScheduledWorkday()
            ? $this->lateMinutes($paid, $workday)
            : 0;

        $undertimeMinutes = $this->undertimeMinutes($timesheet, $workday, $pairs, $paidMinutes, $scheduledMinutes);

        $timesheet->actual_regular = $this->hours($regularMinutes);
        $timesheet->actual_ot = $this->hours($payableOvertimeMinutes);
        $timesheet->actual_nt = $this->hours($nightMinutes);
        $timesheet->actual_nt_ot = $this->hours($nightOvertimeMinutes);

        // Keep the legacy mirrors aligned for reports that still read them.
        $timesheet->regular = $this->hours($regularMinutes);
        $timesheet->ot = $this->hours($payableOvertimeMinutes);
        $timesheet->nt = $this->hours($nightMinutes);
        $timesheet->nt_ot = $this->hours($nightOvertimeMinutes);

        $timesheet->scheduled_minutes = $scheduledMinutes;
        $timesheet->paid_minutes = $paidMinutes;
        $timesheet->late_minutes = $lateMinutes;
        $timesheet->undertime_minutes = $undertimeMinutes;
        $timesheet->computed_at = now();
        $timesheet->rule_version = self::RULE_VERSION;

        $timesheet->logs_rates = $this->logsRates(
            $timesheet,
            $regularMinutes,
            $payableOvertimeMinutes,
            $nightMinutes,
            $nightOvertimeMinutes,
            $paid,
        );

        $timesheet->save();

        return $timesheet;
    }

    /**
     * Pair the punch sequence into complete in/out spans, in minutes.
     *
     * A time-out earlier than the time-in belongs to the next calendar day,
     * which is how an overnight 22:00-06:00 punch pair is measured.
     *
     * @return list<array{0: int, 1: int}>
     */
    private function pairs(Timesheet $timesheet, ResolvedWorkday $workday): array
    {
        $logs = collect($timesheet->logs ?? [])
            ->filter(fn ($log) => isset($log['time']))
            ->values();

        $pairs = [];
        $start = null;
        $previousEnd = null;
        $shiftStart = $workday->shiftStart();

        foreach ($logs as $log) {
            $minutes = $this->minutes((string) $log['time']);
            $type = (int) ($log['log_type'] ?? 0);

            if ($type === 0) {
                $start = $minutes;

                continue;
            }

            if ($start === null) {
                continue;
            }

            $end = $minutes;

            // A span that is earlier than the previous one belongs to the
            // following day. This keeps a 22:00-02:00 + 03:00-06:00 split on
            // the same workday instead of folding it into the morning.
            if ($previousEnd !== null && $start < $previousEnd) {
                $start += 24 * 60;
                $end += 24 * 60;
            } elseif ($previousEnd === null && $shiftStart !== null && $start < $shiftStart - 12 * 60) {
                $start += 24 * 60;
                $end += 24 * 60;
            }

            if ($end <= $start) {
                $end += 24 * 60;
            }

            $pairs[] = [$start, $end];
            $previousEnd = $end;
            $start = null;
        }

        return $pairs;
    }

    /**
     * @param  list<array{0: int, 1: int}>  $paid
     */
    private function lateMinutes(array $paid, ResolvedWorkday $workday): int
    {
        $late = 0;
        $grace = max(0, $workday->graceMinutes);

        foreach ($workday->paidSegments() as [$segmentStart, $segmentEnd]) {
            $arrival = null;

            foreach ($paid as [$start, $end]) {
                if ($start >= $segmentStart - 3 * 60 && $start < $segmentEnd) {
                    $arrival = $start;

                    break;
                }
            }

            if ($arrival === null) {
                continue;
            }

            $late += max(0, $arrival - $segmentStart - $grace);
        }

        return $late;
    }

    /**
     * @param  list<array{0: int, 1: int}>  $pairs
     */
    private function undertimeMinutes(
        Timesheet $timesheet,
        ResolvedWorkday $workday,
        array $pairs,
        int $paidMinutes,
        int $scheduledMinutes,
    ): int {
        if ($pairs === [] || $scheduledMinutes <= 0) {
            return 0;
        }

        if (! $workday->isScheduledWorkday() || $this->isLeaveOrAbsent($timesheet)) {
            return 0;
        }

        // Do not raise undertime at lunchtime; wait until the shift ended.
        if (! $this->workdayHasEnded($timesheet, $workday)) {
            return 0;
        }

        return max(0, $scheduledMinutes - $paidMinutes);
    }

    private function isLeaveOrAbsent(Timesheet $timesheet): bool
    {
        if ((bool) $timesheet->is_leave || (bool) $timesheet->is_absent) {
            return true;
        }

        return in_array((string) $timesheet->day_rate_code, [Rate::PAID_LEAVE, Rate::UNPAID_LEAVE], true);
    }

    private function workdayHasEnded(Timesheet $timesheet, ResolvedWorkday $workday): bool
    {
        $date = $timesheet->log_date;

        if ($date === null) {
            return false;
        }

        $endMinutes = $workday->shiftEnd() ?? $workday->defaultMinutes;
        $end = CarbonImmutable::parse($date->toDateString())->startOfDay()->addMinutes($endMinutes);

        return $end->lessThanOrEqualTo(now());
    }

    /**
     * Rebuild the display-friendly rate lines so the admin screen shows the
     * same numbers payroll reads.
     *
     * @param  list<array{0: int, 1: int}>  $paid
     */
    private function logsRates(
        Timesheet $timesheet,
        int $regular,
        int $overtime,
        int $night,
        int $nightOvertime,
        array $paid,
    ): ?array {
        $code = (string) ($timesheet->day_rate_code ?: $timesheet->day_type ?: Timesheet::REGULAR_DAY);
        $rate = Rate::query()->where('code', $code)->first();
        $first = $paid[0] ?? null;
        $last = $paid === [] ? null : $paid[count($paid) - 1];
        $logs = [$first[0] ?? null, $last[1] ?? null];

        $lines = [
            'regular' => $regular,
            'ot' => $overtime,
            'nt' => $night,
            'nt_ot' => $nightOvertime,
        ];

        $result = [];

        foreach ($lines as $bucket => $minutes) {
            if ($minutes <= 0) {
                continue;
            }

            $result[$code.'-'.str_replace('_', '-', $bucket)] = [
                'hours' => $this->hours($minutes),
                'actual' => $this->hours($minutes),
                'rate' => $bucket === 'regular'
                    ? (float) ($rate->regular ?? 1.0)
                    : (float) ($rate->{$bucket} ?? 1.0),
                'is_rest_day' => (bool) ($rate->is_rest_day ?? false),
                'is_holiday' => (bool) ($rate->is_holiday ?? false),
                'logs' => $logs,
            ];
        }

        return $result === [] ? null : $result;
    }

    private function minutes(string $time): int
    {
        [$hour, $minute] = array_pad(explode(':', $time), 2, '0');

        return ((int) $hour) * 60 + ((int) $minute);
    }

    private function hours(int $minutes): float
    {
        return round($minutes / 60, 2);
    }
}
