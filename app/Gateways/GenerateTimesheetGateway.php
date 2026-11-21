<?php

namespace App\Gateways;

use App\Models\Employee;
use App\Models\Holiday;
use App\Models\PayrollGroup;
use App\Models\Timesheet;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;

class GenerateTimesheetGateway
{
    /**
     * Create the blank attendance rows an employee needs for a date range.
     *
     * Existing rows whose day type and rate are already set are left alone so a
     * daily regeneration cannot wipe a manual correction. Employees without a
     * work schedule are skipped: a blank row without a schedule cannot be
     * classified as a working day or a rest day.
     *
     * @return array{created: int, updated: int, skipped: int}
     */
    public function addDays(Employee $employee, $days = null, ?int $scheduleId = null): array
    {
        $dates = $this->normalizeDates($days);
        $scheduleId ??= $employee->schedule_id;

        $summary = ['created' => 0, 'updated' => 0, 'skipped' => 0];

        if ($scheduleId === null || $dates === []) {
            $summary['skipped'] = count($dates);

            return $summary;
        }

        $start = $dates[0]->toDateString();
        $end = $dates[count($dates) - 1]->toDateString();

        $endExclusive = CarbonImmutable::parse($end)->addDay()->toDateString();

        $existing = Timesheet::query()
            ->where('employee_id', $employee->getKey())
            ->where('log_date', '>=', $start)
            ->where('log_date', '<', $endExclusive)
            ->get()
            ->keyBy(fn (Timesheet $timesheet): ?string => $timesheet->log_date?->toDateString());

        $holidays = Holiday::query()
            ->where('date', '>=', $start)
            ->where('date', '<', $endExclusive)
            ->get()
            ->keyBy(fn (Holiday $holiday): ?string => $holiday->date?->toDateString());

        foreach ($dates as $date) {
            $logDate = $date->toDateString();
            $timesheet = $existing->get($logDate);

            if ($timesheet !== null && $timesheet->day_type !== null && $timesheet->rate_code !== null) {
                // Keep the classification but follow a schedule reassignment so
                // overtime and shift rules use the new schedule.
                if ((int) $timesheet->schedule_id !== $scheduleId) {
                    $timesheet->schedule_id = $scheduleId;
                    $timesheet->save();
                    $summary['updated']++;

                    continue;
                }

                $summary['skipped']++;

                continue;
            }

            $isNew = $timesheet === null;

            if ($isNew) {
                $timesheet = new Timesheet;
                $timesheet->employee_id = $employee->getKey();
                $timesheet->employee_number = $employee->employee_number;
                $timesheet->biometric_id = $employee->biometric_id;
                $timesheet->log_date = $logDate;
                $timesheet->office_id = $employee->office_id;
                $timesheet->department_id = $employee->department_id;
            }

            $isRestDay = $this->isRestDay($date, $employee->payrollGroup);
            $holiday = $holidays->get($logDate);

            $timesheet->day_type = $isRestDay ? Timesheet::REST_DAY : Timesheet::REGULAR_DAY;
            $timesheet->schedule_id = $scheduleId;

            if ($holiday !== null) {
                $timesheet->holiday_type = $holiday->holiday_type;
                $timesheet->holiday_id = $holiday->getKey();
                $timesheet->holiday_name = $holiday->name;
                $timesheet->holiday_code = $holiday->code;
            }

            $timesheet->rate_code = $this->rateCode($isRestDay, $holiday);

            $timesheet->save();

            $isNew ? $summary['created']++ : $summary['updated']++;
        }

        return $summary;
    }

    /**
     * @return array<int, CarbonImmutable>
     */
    private function normalizeDates($days): array
    {
        if ($days instanceof CarbonPeriod) {
            $days = $days->toArray();
        }

        if (! is_array($days) || $days === []) {
            $date = now();
            $days = CarbonPeriod::create($date->copy()->subMonth(2), '1 day', $date->copy()->addMonth())->toArray();
        }

        $normalized = [];

        foreach ($days as $day) {
            $normalized[] = CarbonImmutable::parse($day)->startOfDay();
        }

        usort($normalized, fn ($a, $b) => $a <=> $b);

        return $normalized;
    }

    private function isRestDay(CarbonInterface $date, ?PayrollGroup $payrollGroup): bool
    {
        if ($payrollGroup?->days_year == PayrollGroup::NUMBER_WORKING_DAYS_MONFRI) {
            return $date->isSaturday() || $date->isSunday();
        }

        if ($payrollGroup?->days_year == PayrollGroup::NUMBER_WORKING_DAYS_MONSAT) {
            return $date->isSunday();
        }

        return false;
    }

    private function rateCode(bool $isRestDay, ?Holiday $holiday): string
    {
        $dayType = $isRestDay ? Timesheet::REST_DAY : Timesheet::REGULAR_DAY;

        if ($holiday === null) {
            return $dayType;
        }

        return $isRestDay
            ? Timesheet::REST_DAY.'-'.$holiday->code
            : (string) $holiday->code;
    }
}
