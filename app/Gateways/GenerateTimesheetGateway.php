<?php

namespace App\Gateways;

use App\Models\Employee;
use App\Models\PayrollGroup;
use App\Models\Timesheet;

class GenerateTimesheetGateway
{
    public function addDays(Employee $employee, $days = null, $scheduleId = null)
    {
        // If employee is not active then return
        if (! $employee->allow_timesheet_edit) {
            return false;
        }

        // if we supply days
        if ($days) {
            $dates = $days;
        } else {
            $date = now();
            $dateTo = now();
            $dates = \Carbon\CarbonPeriod::create($date->subMonth(2), '1 day', $dateTo->addMonth());
        }

        foreach ($dates as $date) {

            $logdate = $date->format('Y-m-d');

            $timesheet = Timesheet::firstOrNew([
                'employee_id' => $employee->id,
                'employee_number' => $employee->employee_number,
                'log_date' => $logdate,
            ]);

            // If we already have data then just continue
            if ($timesheet->rate_code != null and $timesheet->day_type != null) {
                // continue;
            }

            $timesheet->day_type = Timesheet::REGULAR_DAY;

            $isRestDay = false;

            // $this->comment($employee->payrollGroup?->frequency);
            // $this->comment($employee->payrollGroup?->days_year);
            if ($employee->payrollGroup?->days_year == PayrollGroup::NUMBER_WORKING_DAYS_MONFRI) {

                // If saturday or sunday then it should be restday
                if ($date->isSaturday() or $date->isSunday()) {
                    $isRestDay = true;
                }
            }

            if ($employee->payrollGroup?->days_year == PayrollGroup::NUMBER_WORKING_DAYS_MONSAT) {

                // If Sunday then it should be restday
                if ($date->isSunday()) {
                    $isRestDay = true;
                }
            }

            // $this->comment($timesheet->id . '' . $date->isSaturday());
            // Check if holiday
            $holiday = find_holiday($date->format('Y-m-d'));

            // If restday then lets check if holiday
            if ($isRestDay) {
                $timesheet->day_type = Timesheet::REST_DAY;
            }

            if ($holiday) {
                $timesheet->holiday_type = $holiday->code;
                $timesheet->holiday_id = $holiday->id;
                $timesheet->holiday_name = $holiday->name;
            }

            // Regular day
            if ($timesheet->day_type == Timesheet::REGULAR_DAY and $timesheet->holiday_type == null) {
                $timesheet->rate_code = Timesheet::REGULAR_DAY;
            }

            if ($timesheet->day_type == Timesheet::REGULAR_DAY and $timesheet->holiday_type != null) {
                $timesheet->rate_code = $timesheet->holiday_type;
            }

            // Rest Day
            if ($timesheet->day_type == Timesheet::REST_DAY and $timesheet->holiday_type == null) {
                $timesheet->rate_code = Timesheet::REST_DAY;
            }

            if ($timesheet->day_type == Timesheet::REST_DAY and $timesheet->holiday_type != null) {
                $timesheet->rate_code = Timesheet::REST_DAY.'-'.$timesheet->holiday_type;
            }

            $timesheet->schedule_id = $scheduleId ?? $employee->schedule_id;

            $timesheet->save();
        }
    }
}
