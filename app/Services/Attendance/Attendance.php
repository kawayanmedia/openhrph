<?php

namespace App\Services\Attendance;

use App\Models\Employee;
use App\Models\Payroll;
use App\Models\PayrollDetail;
use App\Models\PayrollDetailPay;
use App\Models\Timesheet;
use Illuminate\Support\Facades\Log;

class Attendance
{
    public $paymentType = Employee::PAYMENT_TYPE_MONTHLY;

    public $totalHours = 0;

    public $totalDays = 0;

    public $dateStart;

    public $dateEnd;

    // Regular Day
    public $totalDayHours;

    public $totalHolidayHours;

    public $totalHolidayPay = 0;

    public $totalSpecialHolidayHours;

    public $totalPremiumHolidayHours;

    public $totalOverTimeHours;

    public $totalOverTimePay = 0;

    public $totalNightDifferentialHours;

    public $totalNightDifferentialPay = 0;

    public $totalNightDifferentialOvertTimeHours;

    // Rest Day
    public $totalRestDayHours = 0;

    public $totalRestDayPay = 0;

    public $totalRestDayHolidayHours;

    public $totalRestDayHolidayPay;

    public $totalRestDaySpecialHolidayHours;

    public $totalRestDayPremiumHolidayHours;

    public $totalRestDayOverTimeHours;

    public $totalRestDayNightDifferentialHours;

    public $totalRestDayNightDifferentialOvertTimeHours;

    public $totalAbsentDays = 0;

    public $payrollDetail;

    public function __construct(public Employee $employee, public Payroll $payroll)
    {
        $this->dateStart = $payroll->date_from;
        $this->dateEnd = $payroll->date_to;
    }

    public function setEmployee(Employee $employee)
    {
        $this->employee = $employee;

        return $this;
    }

    public function setDateStart($dateStart)
    {
        $this->dateStart = $dateStart;

        return $this;
    }

    public function setDateEnd($dateEnd)
    {
        $this->dateEnd = $dateEnd;

        return $this;
    }

    public function handle()
    {
        // Need to check paymentType so that we know that employee is paid by daily(arawan)
        // or monthly paid
        $this->paymentType = $this->employee->payment_type;

        // Reset all total first
        $this->reset();
        $timesheets = Timesheet::query()
            ->where('employee_id', $this->employee->id)
            ->whereBetween('log_date', [$this->dateStart->format('Y-m-d'), $this->dateEnd->format('Y-m-d')])
            ->orderBy('log_date', 'asc')
            ->get();

        foreach ($timesheets as $timesheet) {
            // Proceed with default schedule of 8am to 5pm
            $this->getTotalHours($timesheet, $this->employee, true);

            // Use this on strick basis
            // if ($timesheet->is_absent or $timesheet->logs_rates == null) {
            //     $this->totalAbsentDays += 1;
            // }

            // For monthly paid
            // We dont count absent since employee is paid by number of days
            //
            if ($this->paymentType == Employee::PAYMENT_TYPE_MONTHLY) {
                if ($timesheet->is_absent and $timesheet->day_type != Timesheet::REST_DAY) {
                    $this->totalAbsentDays += 1;
                }
            }
        }

        // Compute the amount per hours and type
        if (! empty($this->hours)) {
            foreach ($this->hours as $rateCode => $hour) {
                foreach ($hour as $type => $value) { // type can be regular, or, nt, nt_ot
                    // dd($value);
                    // dd($code . ' ' . $type . ' ' . $value['hours']);
                    // dd(hour_rates_value($code, $type));
                    $this->hours[$rateCode][$type]['rate'] = hour_rates_value($rateCode, $type);
                    $this->hours[$rateCode][$type]['equivalent_hours'] = $value['hours'] * hour_rates_value($rateCode, $type);
                    $this->hours[$rateCode][$type]['daily_rate'] = $this->employee->daily_rate;
                    $this->hours[$rateCode][$type]['hourly_rate'] = $this->employee->hourly_rate;

                    // Check if the employee is paid by daily
                    // For daily paid
                    if ($this->paymentType == Employee::PAYMENT_TYPE_DAILY) {
                        // Total amount should be times number days
                        $days = floor($this->hours[$rateCode][$type]['equivalent_hours'] / 8);
                        $this->hours[$rateCode][$type]['total_amount'] = round($days * $this->employee->daily_rate, 2);
                    } else {
                        $this->hours[$rateCode][$type]['total_amount'] = round($this->hours[$rateCode][$type]['equivalent_hours'] * $this->employee->hourly_rate, 2);
                    }
                    // Store to database
                    $payrollDetailPay = PayrollDetailPay::firstOrNew([
                        'company_id' => $this->payroll->payrollGroup->company_id,
                        'payroll_id' => $this->payroll->id,
                        'employee_id' => $this->employee->id,
                        'day_type' => $rateCode,
                        'rate_type' => $type,
                    ]);

                    $payrollDetailPay->is_rest_day = $value['is_rest_day'];
                    $payrollDetailPay->is_holiday = $value['is_holiday'];
                    $payrollDetailPay->hours = $value['hours'];
                    $payrollDetailPay->rate = $this->hours[$rateCode][$type]['rate'];
                    $payrollDetailPay->equivalent_hours = $this->hours[$rateCode][$type]['equivalent_hours'];
                    $payrollDetailPay->daily_rate = $this->employee->daily_rate;
                    $payrollDetailPay->hourly_rate = $this->employee->hourly_rate;
                    $payrollDetailPay->total_amount = $this->hours[$rateCode][$type]['total_amount'];

                    // Overtime
                    if ($type == Timesheet::RATE_OVERTIME or $type == Timesheet::RATE_NIGHT_DIFFERENTIAL_OVERTIME) {
                        $payrollDetailPay->is_overtime_pay = true;
                    }

                    if ($type == Timesheet::RATE_NIGHT_DIFFERENTIAL) {
                        $payrollDetailPay->is_night_pay = true;
                    }

                    if ($value['is_rest_day']) {
                        $payrollDetailPay->is_rest_day_pay = true;

                        // If already an overtime pay then make the restday false
                        // we prioritize overtime pay instead of rest day pay
                        if ($payrollDetailPay->is_overtime_pay == true) {
                            $payrollDetailPay->is_rest_day_pay = false;
                        }
                    }

                    if ($value['is_holiday']) {
                        $payrollDetailPay->is_holiday_pay = true;
                    }

                    $payrollDetailPay->save();
                }

                // We may combine all or other details to simple array
                // holiday pay
                // rest day pay
                // over time
                // night differential
            }
        } else {
            // Log::info($this->employee->id . ' no log');
        }

        // Store also values to payroll details
        $payrollDetailPays = PayrollDetailPay::query()
            ->where('company_id', $this->payroll->payrollGroup->company_id)
            ->where('payroll_id', $this->payroll->id)
            ->where('employee_id', $this->employee->id)
            ->get();

        $payrollDetail = PayrollDetail::firstOrNew([
            'company_id' => $this->payroll->payrollGroup->company_id,
            'payroll_id' => $this->payroll->id,
            'employee_id' => $this->employee->id,
        ]);

        $payrollDetail->regular_hours = $payrollDetailPays
            ->where('day_type', 'regular')
            ->where('rate_type', 'regular')
            ->sum('hours');

        $payrollDetail->regular_pay = $payrollDetailPays
            ->where('day_type', 'regular')
            ->where('rate_type', 'regular')
            ->sum('total_amount');

        $payrollDetail->overtime_hours = $payrollDetailPays->where('is_overtime_pay', true)->sum('hours');
        $payrollDetail->overtime_pay = $payrollDetailPays->where('is_overtime_pay', true)->sum('total_amount');

        $payrollDetail->night_hours = $payrollDetailPays->where('is_night_pay', true)->sum('hours');
        $payrollDetail->night_pay = $payrollDetailPays->where('is_night_pay', true)->sum('total_amount');

        $payrollDetail->rest_day_hours = $payrollDetailPays->where('is_rest_day_pay', true)->sum('hours');
        $payrollDetail->rest_day_pay = $payrollDetailPays->where('is_rest_day_pay', true)->sum('total_amount');

        $payrollDetail->holiday_hours = $payrollDetailPays->where('is_holiday_pay', true)->sum('hours');
        $payrollDetail->holiday_pay = $payrollDetailPays->where('is_holiday_pay', true)->sum('total_amount');

        $payrollDetail->save();

        $this->payrollDetail = $payrollDetail;

        $this->totalOverTimeHours = $payrollDetail->overtime_hours;
        $this->totalOverTimePay = $payrollDetail->overtime_pay;

        $this->totalNightDifferentialHours = $payrollDetail->night_hours;
        $this->totalNightDifferentialPay = $payrollDetail->night_pay;

        $this->totalRestDayHours = $payrollDetail->rest_day_hours;
        $this->totalRestDayPay = $payrollDetail->rest_day_pay;

        $this->totalHolidayHours = $payrollDetail->holiday_hours;
        $this->totalHolidayPay = $payrollDetail->holiday_pay;
        // If the type is regular then put the amount
        // to restday pay if restday and put to holiday pay if
        // holiday. if both restday and holiday then put to restday
        // holiday pay.

        // if ot then put to ot, if nt put to nt, if nt_ot put to ot
        return $this;

    }

    // we can use hours for all computations of salary
    public $hours = [];

    public function setHours($timesheet, $rate, $key) // key rest_day_hours
    {
        if (isset($this->hours[$timesheet->rate_code][$key]['hours'])) {
            $this->hours[$timesheet->rate_code][$key]['hours'] += $rate['hours'];
        } else {
            $this->hours[$timesheet->rate_code][$key]['is_rest_day'] = $rate['is_rest_day'] ?? false;
            $this->hours[$timesheet->rate_code][$key]['is_holiday'] = $rate['is_holiday'] ?? false;
            $this->hours[$timesheet->rate_code][$key]['hours'] = $rate['hours'];
        }

        // For daily paid
        if ($this->paymentType == Employee::PAYMENT_TYPE_DAILY) {
            // $this->totalDays += 1;
        } else {

        }
    }

    public function getTotalHours(Timesheet $timesheet, Employee $employee)
    {
        // Check if leave
        if ($timesheet->is_leave) {
            // $this->setHours($timesheet, ['hours' => 8], 'pl'); // key ot;
            return;
        }

        if ($timesheet->logs_rates != null) {
            foreach ($timesheet->logs_rates as $rateKey => $rate) {

                // If restday is not paid then just continue to next timesheet
                if ($timesheet->day_type == Timesheet::REST_DAY) {

                    if ($employee->rest_day_pay == false) {
                        continue;
                    }
                }

                // If regular holiday is not paid then just continue to next timesheet
                if ($timesheet->rate_code == Timesheet::REGULAR_HOLIDAY) {
                    if ($employee->regular_holiday_pay == false) {
                        continue;
                    }
                }

                // If special holiday is not paid then just continue to next timesheet
                if ($timesheet->rate_code == Timesheet::SPECIAL_HOLIDAY) {
                    if ($employee->special_holiday_pay == false) {
                        continue;
                    }
                }

                // If double regular holiday is not paid then just continue to next timesheet
                if ($timesheet->rate_code == Timesheet::DOUBLE_REGULAR_HOLIDAY) {
                    if ($employee->premium_holiday_pay == false) {
                        continue;
                    }
                }

                // If regular_holiday_special_holiday is not paid then just continue to next timesheet
                if ($timesheet->rate_code == Timesheet::REGULAR_HOLIDAY_SPECIAL_HOLIDAY) {
                    if ($employee->regular_holiday_special_holiday_pay == false) {
                        continue;
                    }
                }

                [$dayType, $rateKey] = explode('-', $rateKey, 2); // limit to 2 so that we explode only for 2 array items
                $rateKey = str_replace('-', '_', $rateKey);

                // $rateKey = str_replace('regular-', '', $rateKey);
                // $rateKey = str_replace('rd-', '', $rateKey);
                // $rateKey = str_replace('-', '_', $rateKey);

                if ($rateKey == Timesheet::RATE_REGULAR) {

                    $this->setHours($timesheet, $rate, Timesheet::RATE_REGULAR); // key regular
                }

                if ($rateKey == Timesheet::RATE_OVERTIME) {
                    // If allowed to do overtime
                    if ($employee->overtime_pay == true) {
                        $this->setHours($timesheet, $rate, Timesheet::RATE_OVERTIME); // key ot;
                    }
                }

                if ($rateKey == Timesheet::RATE_NIGHT_DIFFERENTIAL) {

                    // If allowed to do night differential
                    if ($employee->night_differential == true) {
                        $this->setHours($timesheet, $rate, Timesheet::RATE_NIGHT_DIFFERENTIAL); // key nt
                    }
                }

                if ($rateKey == Timesheet::RATE_NIGHT_DIFFERENTIAL_OVERTIME) {
                    // If allowed to do night differential and overtime
                    if ($employee->night_differential == true and $employee->overtime_pay == true) {
                        $this->setHours($timesheet, $rate, Timesheet::RATE_NIGHT_DIFFERENTIAL_OVERTIME); // key nt_ot
                    }
                }
            }
        }

        return $this;
    }

    public function reset()
    {
        $this->hours = [];
        $this->totalHours = 0;
        $this->totalDays = 0;

        // Regular Day
        $this->totalDayHours = 0;

        $this->totalHolidayHours = 0;

        $this->totalSpecialHolidayHours = 0;

        $this->totalPremiumHolidayHours = 0;

        $this->totalOverTimeHours = 0;

        $this->totalNightDifferentialHours = 0;

        $this->totalNightDifferentialOvertTimeHours = 0;

        // Rest Day
        $this->totalRestDayHours = 0;

        $this->totalRestDayHolidayHours = 0;

        $this->totalRestDaySpecialHolidayHours = 0;

        $this->totalRestDayPremiumHolidayHours = 0;

        $this->totalRestDayOverTimeHours = 0;

        $this->totalRestDayNightDifferentialHours = 0;

        $this->totalRestDayNightDifferentialOvertTimeHours = 0;

        $this->payrollDetail;

    }
}
