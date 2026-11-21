<?php

namespace App\Services\Attendance;

use App\Models\Schedule;
use App\Models\Timesheet;

/**
 * Derives the payable hour buckets from raw punches.
 *
 * The engine reads actual_regular/actual_ot/actual_nt/actual_nt_ot. Every
 * punch source funnels through here so the buckets are consistent, instead of
 * depending on someone opening and saving the day in the admin editor.
 */
final class TimesheetCalculator
{
    public const NIGHT_START_MINUTES = 22 * 60;

    public const NIGHT_END_MINUTES = 6 * 60;

    public function compute(Timesheet $timesheet): Timesheet
    {
        if ((bool) $timesheet->is_locked) {
            return $timesheet;
        }

        $pairs = $this->pairs($timesheet);

        // An incomplete punch is a missing punch; keep any hours already
        // computed rather than zeroing them.
        if ($pairs === []) {
            return $timesheet;
        }

        $schedule = $timesheet->schedule ?? $timesheet->employee?->schedule;
        $scheduled = $this->scheduledMinutes($timesheet, $schedule);

        $regular = 0;
        $overtime = 0;
        $night = 0;
        $nightOT = 0;

        foreach ($pairs as [$start, $end]) {
            $worked = $end - $start;
            $regularMinutes = min($worked, max(0, $scheduled - $regular));
            $otMinutes = max(0, $worked - $regularMinutes);

            $regular += $regularMinutes;
            $overtime += $otMinutes;
            $night += $this->nightOverlap($start, $end);

            if ($otMinutes > 0) {
                $nightOT += $this->nightOverlap($end - $otMinutes, $end);
            }
        }

        $timesheet->actual_regular = $this->hours($regular);
        $timesheet->actual_ot = $this->hours($overtime);
        $timesheet->actual_nt = $this->hours($night);
        $timesheet->actual_nt_ot = $this->hours($nightOT);

        // Keep the legacy mirrors aligned for reports that still read them.
        $timesheet->regular = $this->hours($regular);
        $timesheet->ot = $this->hours($overtime);
        $timesheet->nt = $this->hours($night);
        $timesheet->nt_ot = $this->hours($nightOT);

        $timesheet->save();

        return $timesheet;
    }

    /**
     * Pair the punch sequence into complete in/out spans, in minutes.
     *
     * @return list<array{0: int, 1: int}>
     */
    private function pairs(Timesheet $timesheet): array
    {
        // Keep the stored order: the recorder already sorts by recorded_at,
        // which is what distinguishes an overnight out from the next morning.
        $logs = collect($timesheet->logs ?? [])
            ->filter(fn ($log) => isset($log['time']))
            ->values();

        $pairs = [];
        $start = null;

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

            if ($end <= $start) {
                $end += 24 * 60;
            }

            $pairs[] = [$start, $end];
            $start = null;
        }

        return $pairs;
    }

    private function scheduledMinutes(Timesheet $timesheet, ?Schedule $schedule): int
    {
        if ($schedule !== null) {
            $minutes = 0;

            foreach ([[$schedule->am_in, $schedule->am_out], [$schedule->pm_in, $schedule->pm_out]] as [$in, $out]) {
                if ($in === null || $out === null) {
                    continue;
                }

                $start = $this->minutes((string) $in);
                $end = $this->minutes((string) $out);

                if ($end <= $start) {
                    $end += 24 * 60;
                }

                $minutes += $end - $start;
            }

            if ($minutes > 0) {
                if ($schedule->break_time_start !== null && $schedule->break_time_end !== null) {
                    $breakStart = $this->minutes((string) $schedule->break_time_start);
                    $breakEnd = $this->minutes((string) $schedule->break_time_end);

                    if ($breakEnd > $breakStart) {
                        $minutes -= $breakEnd - $breakStart;
                    }
                }

                return max(0, $minutes);
            }
        }

        $hours = (int) ($timesheet->employee?->payrollGroup?->hours_day ?? 8);

        return max(1, $hours) * 60;
    }

    private function nightOverlap(int $start, int $end): int
    {
        if ($end <= $start) {
            return 0;
        }

        $minutes = 0;

        for ($offset = -1; $offset <= 2; $offset++) {
            $eveningStart = $offset * 24 * 60 + self::NIGHT_START_MINUTES;
            $eveningEnd = $offset * 24 * 60 + 24 * 60;
            $minutes += max(0, min($end, $eveningEnd) - max($start, $eveningStart));

            $morningStart = $offset * 24 * 60;
            $morningEnd = $offset * 24 * 60 + self::NIGHT_END_MINUTES;
            $minutes += max(0, min($end, $morningEnd) - max($start, $morningStart));
        }

        return $minutes;
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
