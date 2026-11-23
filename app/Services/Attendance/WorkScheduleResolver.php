<?php

namespace App\Services\Attendance;

use App\Models\AttendanceScheduleAssignment;
use App\Models\AttendanceScheduleOverride;
use App\Models\AttendanceShift;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\PayrollGroup;
use App\Models\Schedule;
use App\Models\Timesheet;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Throwable;

/**
 * Resolves the shift an employee actually works on a date.
 *
 * Precedence:
 *   1. a one-day override,
 *   2. an effective-dated assignment (including a rotation cycle),
 *   3. the shift snapshot stored on the timesheet,
 *   4. the legacy `schedules` row on the employee,
 *   5. the company's default shift,
 *   6. an eight-hour default.
 *
 * Every attendance entry point talks to this resolver so a schedule change
 * cannot silently rewrite an already computed historical day.
 */
final class WorkScheduleResolver
{
    public function resolve(?Employee $employee, CarbonInterface|string|null $date = null, ?Timesheet $timesheet = null): ResolvedWorkday
    {
        $employee ??= $timesheet?->employee;
        $day = CarbonImmutable::parse($date ?? $timesheet?->log_date ?? now())->startOfDay();

        $defaultMinutes = $this->defaultMinutes($employee);
        $isHoliday = $this->isHoliday($timesheet, $day);
        $storedRestDay = $timesheet?->day_type === Timesheet::REST_DAY;
        $shift = null;
        $isRestDay = false;
        $source = 'default';

        if ($employee !== null) {
            $resolved = $this->resolveAssignment($employee, $day);

            if ($resolved !== null) {
                [$shift, $isRestDay] = $resolved;
                $source = 'assignment';
            }
        }

        if ($shift === null && ! $isRestDay) {
            $snapshot = $this->shiftForTimesheet($timesheet);

            if ($snapshot !== null) {
                $shift = $snapshot;
                $source = 'timesheet_shift';
            }
        }

        if ($shift === null && ! $isRestDay) {
            $schedule = $timesheet?->schedule ?? $employee?->schedule;

            if ($schedule !== null) {
                $isRestDay = $timesheet?->day_type === Timesheet::REST_DAY
                    || ($timesheet?->day_type === null && $this->legacyRestDay($employee, $day));
                $source = 'legacy_schedule';

                [$nightStart, $nightEnd] = $this->nightWindow(null);

                return $this->fromSchedule(
                    $schedule,
                    $day,
                    $isRestDay,
                    $isHoliday,
                    $nightStart,
                    $nightEnd,
                    $defaultMinutes,
                );
            }
        }

        if ($shift === null && ! $isRestDay) {
            $shift = $this->defaultShift($employee);
            $source = $shift !== null ? 'company_default' : 'default';
        }

        [$nightStart, $nightEnd] = $this->nightWindow($shift);

        if ($shift !== null) {
            return $this->fromShift($shift, $day, $isRestDay || $storedRestDay, $isHoliday, $nightStart, $nightEnd, $defaultMinutes, $source);
        }

        return new ResolvedWorkday(
            date: $day->toDateString(),
            name: 'Default 8 hours',
            graceMinutes: (int) $this->setting('minutes_tardy', config('attendance.grace_minutes', 0)),
            nightStartMinutes: $nightStart,
            nightEndMinutes: $nightEnd,
            isRestDay: $isRestDay || $storedRestDay || $this->legacyRestDay($employee, $day),
            isHoliday: $isHoliday,
            source: $source,
            defaultMinutes: $defaultMinutes,
        );
    }

    /**
     * @return array{0: AttendanceShift|null, 1: bool}|null
     */
    private function resolveAssignment(Employee $employee, CarbonImmutable $day): ?array
    {
        $override = AttendanceScheduleOverride::query()
            ->where('employee_id', $employee->getKey())
            ->whereDate('date', $day->toDateString())
            ->first();

        if ($override !== null) {
            if ($override->is_rest_day || $override->shift_id === null) {
                return [null, true];
            }

            $shift = $this->findShift((int) $override->shift_id);

            if ($shift !== null) {
                return [$shift, false];
            }
        }

        $assignment = AttendanceScheduleAssignment::query()
            ->where('employee_id', $employee->getKey())
            ->whereDate('effective_from', '<=', $day->toDateString())
            ->where(function ($query) use ($day): void {
                $query->whereNull('effective_to')
                    ->orWhereDate('effective_to', '>=', $day->toDateString());
            })
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->first();

        if ($assignment === null) {
            return null;
        }

        $shiftId = $assignment->shiftIdFor($day);

        if ($shiftId === null) {
            return [null, true];
        }

        $shift = $this->findShift($shiftId);

        return $shift === null ? [null, true] : [$shift, false];
    }

    private function shiftForTimesheet(?Timesheet $timesheet): ?AttendanceShift
    {
        $shiftId = $timesheet?->getAttribute('shift_id');

        return $shiftId === null ? null : $this->findShift((int) $shiftId);
    }

    private function findShift(int $shiftId): ?AttendanceShift
    {
        return AttendanceShift::query()->whereKey($shiftId)->where('is_active', true)->first();
    }

    private function defaultShift(?Employee $employee): ?AttendanceShift
    {
        $query = AttendanceShift::query()->where('is_active', true);

        if ($employee?->company_id !== null) {
            $query->where('company_id', $employee->company_id);
        }

        return $query->where('is_default', true)->orderBy('id')->first();
    }

    private function fromShift(
        AttendanceShift $shift,
        CarbonImmutable $day,
        bool $isRestDay,
        bool $isHoliday,
        int $nightStart,
        int $nightEnd,
        int $defaultMinutes,
        string $source,
    ): ResolvedWorkday {
        [$segments, $breaks] = $this->normalizeSegments(
            is_array($shift->segments) ? $shift->segments : [],
            is_array($shift->breaks) ? $shift->breaks : [],
        );

        return new ResolvedWorkday(
            date: $day->toDateString(),
            name: (string) $shift->name,
            segments: $segments,
            breaks: $breaks,
            graceMinutes: $isRestDay ? 0 : max(0, (int) $shift->grace_minutes),
            nightStartMinutes: $nightStart,
            nightEndMinutes: $nightEnd,
            isRestDay: $isRestDay,
            isHoliday: $isHoliday,
            shiftId: (int) $shift->getKey(),
            source: $source,
            defaultMinutes: $defaultMinutes,
        );
    }

    private function fromSchedule(
        Schedule $schedule,
        CarbonImmutable $day,
        bool $isRestDay,
        bool $isHoliday,
        int $nightStart,
        int $nightEnd,
        int $defaultMinutes,
    ): ResolvedWorkday {
        $raw = [];

        if ($schedule->am_in !== null && $schedule->am_out !== null) {
            $raw[] = [$schedule->am_in, $schedule->am_out];
        }

        if ($schedule->pm_in !== null && $schedule->pm_out !== null) {
            $raw[] = [$schedule->pm_in, $schedule->pm_out];
        } elseif ($schedule->pm_in !== null && $schedule->am_out_morning !== null) {
            $raw[] = [$schedule->pm_in, $schedule->am_out_morning];
        }

        if ($raw === [] && $schedule->time_in !== null && $schedule->time_out !== null) {
            $raw[] = [$schedule->time_in, $schedule->time_out];
        }

        $breaks = [];

        if ($schedule->break_time_start !== null && $schedule->break_time_end !== null) {
            $breaks[] = [$schedule->break_time_start, $schedule->break_time_end];
        }

        [$segments, $normalizedBreaks] = $this->normalizeSegments($raw, $breaks);

        return new ResolvedWorkday(
            date: $day->toDateString(),
            name: (string) ($schedule->name ?? 'Schedule'),
            segments: $segments,
            breaks: $normalizedBreaks,
            graceMinutes: $isRestDay ? 0 : (int) $this->setting('minutes_tardy', config('attendance.grace_minutes', 0)),
            nightStartMinutes: $nightStart,
            nightEndMinutes: $nightEnd,
            isRestDay: $isRestDay,
            isHoliday: $isHoliday,
            shiftId: null,
            source: 'legacy_schedule',
            defaultMinutes: $defaultMinutes,
        );
    }

    /**
     * Convert clock strings into ordered, midnight-relative minute intervals.
     *
     * @param  array<int, array{0: mixed, 1: mixed}>  $rawSegments
     * @param  array<int, array{0: mixed, 1: mixed}>  $rawBreaks
     * @return array{0: list<array{0: int, 1: int}>, 1: list<array{0: int, 1: int}>}
     */
    private function normalizeSegments(array $rawSegments, array $rawBreaks): array
    {
        $segments = [];
        $anchor = null;

        foreach ($rawSegments as $key => $pair) {
            $start = $this->minutes($pair[0] ?? null);
            $end = $this->minutes($pair[1] ?? null);

            if ($start === null || $end === null) {
                continue;
            }

            if ($end <= $start) {
                $end += 24 * 60;
            }

            if ($anchor !== null) {
                while ($start < $anchor - 12 * 60) {
                    $start += 24 * 60;
                    $end += 24 * 60;
                }
            }

            if ($key === array_key_first($rawSegments) || $anchor === null) {
                $anchor = $start;
            }

            $segments[] = [$start, $end];
        }

        usort($segments, fn (array $a, array $b): int => $a[0] <=> $b[0]);

        $breaks = [];

        foreach ($rawBreaks as $pair) {
            $start = $this->minutes($pair[0] ?? null);
            $end = $this->minutes($pair[1] ?? null);

            if ($start === null || $end === null) {
                continue;
            }

            if ($end <= $start) {
                $end += 24 * 60;
            }

            if ($segments !== [] && $start < $segments[0][0] - 12 * 60) {
                $start += 24 * 60;
                $end += 24 * 60;
            }

            $breaks[] = [$start, $end];
        }

        return [$segments, $breaks];
    }

    private function isHoliday(?Timesheet $timesheet, CarbonImmutable $day): bool
    {
        if ($timesheet !== null && ($timesheet->holiday_id !== null || $timesheet->holiday_type !== null)) {
            return true;
        }

        return Holiday::query()->whereDate('date', $day->toDateString())->exists();
    }

    private function legacyRestDay(?Employee $employee, CarbonImmutable $day): bool
    {
        $days = (int) ($employee?->payrollGroup?->days_year ?? 0);

        if ($days === PayrollGroup::NUMBER_WORKING_DAYS_MONFRI) {
            return $day->isSaturday() || $day->isSunday();
        }

        if ($days === PayrollGroup::NUMBER_WORKING_DAYS_MONSAT) {
            return $day->isSunday();
        }

        return false;
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function nightWindow(?AttendanceShift $shift): array
    {
        $start = $shift?->night_window_start
            ?: $this->setting('night_shift_differential_start', config('attendance.night_window_start', '22:00'));
        $end = $shift?->night_window_end
            ?: $this->setting('night_shift_differential_end', config('attendance.night_window_end', '06:00'));

        return [
            $this->minutes($start) ?? 22 * 60,
            $this->minutes($end) ?? 6 * 60,
        ];
    }

    private function defaultMinutes(?Employee $employee): int
    {
        $hours = (int) ($employee?->payrollGroup?->hours_day ?? 0);

        return ($hours > 0 ? $hours : 8) * 60;
    }

    private function minutes(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $parts = array_pad(explode(':', trim($value)), 3, '0');

        if (! is_numeric($parts[0]) || ! is_numeric($parts[1])) {
            return null;
        }

        return ((int) $parts[0]) * 60 + ((int) $parts[1]);
    }

    private function setting(string $key, mixed $default = null): mixed
    {
        try {
            $value = get_setting($key);

            return $value === null || $value === '' ? $default : $value;
        } catch (Throwable) {
            return $default;
        }
    }
}
