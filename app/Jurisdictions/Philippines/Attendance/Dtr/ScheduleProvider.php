<?php

namespace App\Jurisdictions\Philippines\Attendance\Dtr;

use App\Models\EmployeeSchedule;
use App\Models\Schedule;
use KawayanMedia\DtrPdf\Contract\ScheduleProviderInterface;
use KawayanMedia\DtrPdf\Model\EmployeeSchedule as DtrEmployeeSchedule;
use KawayanMedia\DtrPdf\Model\ShiftTimes;

/**
 * Shift times for the DTR engine.
 *
 * The application schedules a company-wide shift per employee/schedule
 * assignment, so the legacy `shift_type => times` table does not exist: the
 * default schedule (or the first active one) supplies the window, and a dated
 * assignment overrides it.
 */
final class ScheduleProvider implements ScheduleProviderInterface
{
    /**
     * Legacy regular shift used when the company has no schedule row at all.
     */
    private const FALLBACK = ['08:00', '12:00', '13:00', '17:00'];

    public function shiftTimes(int $shiftType, int $dayNumber = 1): ShiftTimes
    {
        $schedule = $this->defaultSchedule();

        if ($schedule === null) {
            return new ShiftTimes(...self::FALLBACK);
        }

        return $this->shiftTimesFor($schedule);
    }

    public function scheduleFor(string $employeeId, ?string $date = null): ?DtrEmployeeSchedule
    {
        $date ??= now()->toDateString();

        $assignment = EmployeeSchedule::query()
            ->where('employee_id', $employeeId)
            ->whereDate('from_date', '<=', $date)
            ->where(function ($query) use ($date): void {
                $query->whereNull('to_date')->orWhereDate('to_date', '>=', $date);
            })
            ->orderByDesc('from_date')
            ->first();

        if ($assignment === null) {
            return null;
        }

        $schedule = Schedule::query()->find($assignment->schedule_id);

        if ($schedule === null) {
            return null;
        }

        return new DtrEmployeeSchedule(
            amIn: (string) $schedule->am_in,
            amOut: (string) $schedule->am_out,
            pmIn: (string) $schedule->pm_in,
            pmOut: (string) $schedule->pm_out,
            hourFrom: (string) $schedule->am_in,
            hourTo: (string) $schedule->pm_out,
        );
    }

    private function defaultSchedule(): ?Schedule
    {
        return Schedule::query()
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->first();
    }

    private function shiftTimesFor(Schedule $schedule): ShiftTimes
    {
        return new ShiftTimes(
            (string) $schedule->am_in,
            (string) $schedule->am_out,
            (string) $schedule->pm_in,
            (string) $schedule->pm_out,
        );
    }
}
