<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Services\Attendance\TimesheetImport;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Timesheet extends Model
{
    use BelongsToTenant;
    use HasFactory;

    const REGULAR_DAY = 'regular';

    const REST_DAY = 'rd';

    const SPECIAL_HOLIDAY = 'sh';

    const REGULAR_HOLIDAY = 'rh';

    const REST_DAY_SPECIAL_HOLIDAY = 'rd-sh';

    const REST_DAY_REGULAR_HOLIDAY = 'rd-rh';

    const DOUBLE_REGULAR_HOLIDAY = '2rh';

    const REST_DAY_DOUBLE_REGULAR_HOLIDAY = 'rd-2rh';

    const REGULAR_HOLIDAY_SPECIAL_HOLIDAY = 'rh-sh';

    const REST_DAY_REGULAR_HOLIDAY_SPECIAL_HOLIDAY = 'rd-rh-sh';

    const RATE_REGULAR = 'regular';

    const RATE_OVERTIME = 'ot';

    const RATE_NIGHT_DIFFERENTIAL = 'nt';

    const RATE_NIGHT_DIFFERENTIAL_OVERTIME = 'nt_ot';

    const ABSENT = 'absent';

    const UNDERTIME = 'ut';

    protected $fillable = [
        'biometric_id',
        'rate_id',
        'employee_id',
        'employee_number',
        'log_date',
        'logs',
        'hours',
        'is_leave',
        'leave_type_id',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'log_date' => 'date',
        'logs' => 'array',
        'logs_rates' => 'array',
        // 'to_date' => 'date',
    ];

    /**
     * The "booted" method of the model.
     *
     * @return void
     */
    protected static function booted()
    {
        static::created(function (Timesheet $model) {
            set_current_timestamp($model);
        });
    }

    public function regular(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value / 100,
            set: fn ($value) => $value * 100
        );
    }

    public function ot(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value / 100,
            set: fn ($value) => $value * 100
        );
    }

    public function nt(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value / 100,
            set: fn ($value) => $value * 100
        );
    }

    public function ntOt(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value / 100,
            set: fn ($value) => $value * 100
        );
    }

    public function regularRate(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value / 100,
            set: fn ($value) => $value * 100
        );
    }

    public function otRate(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value / 100,
            set: fn ($value) => $value * 100
        );
    }

    public function ntRate(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value / 100,
            set: fn ($value) => $value * 100
        );
    }

    // Devide and time so that we can get the value for
    // 1375 as 1.375
    public function ntOtRate(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value / 1000,
            set: fn ($value) => $value * 1000
        );
    }

    public function actualRegular(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value / 100,
            set: fn ($value) => $value * 100
        );
    }

    public function actualOt(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value / 100,
            set: fn ($value) => $value * 100
        );
    }

    public function actualNt(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value / 100,
            set: fn ($value) => $value * 100
        );
    }

    public function actualNtOt(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value / 100,
            set: fn ($value) => $value * 100
        );
    }

    public function getDayTypeFormatAttribute()
    {
        $redColor = "<span class='items-center justify-center px-3 text-sm font-bold text-white rounded shadow bg-red-500 hover:bg-red-400 dark:text-gray-900 focus:outline-none focus:ring ring-red-200 dark:ring-gray-600 h-9'>";
        $greenColor = "<span class='items-center justify-center px-3 text-sm font-bold text-white rounded shadow bg-green-500 hover:bg-green-400 dark:text-gray-900 focus:outline-none focus:ring ring-green-200 dark:ring-gray-600 h-9'>";
        $holiday = '';
        $absent = '';
        $leave = '';

        if ($this->holiday_id != null) {
            $holiday = '('.$this->holiday_name.')';
        }

        if ($this->is_absent == true) {
            $absent = '(Absent)';
        }

        if ($this->is_leave == true) {
            $leave = '(Leave)';
        }

        if ($this->day_type == Timesheet::REGULAR_DAY and $this->holiday_id != null) {
            return $greenColor.$holiday.'</span>';
        }
        if ($this->day_type == Timesheet::REGULAR_DAY and $this->is_absent == true) {
            return $redColor.$absent.'</span>';
        }
        if ($this->day_type == Timesheet::REST_DAY) {
            return $greenColor.day_type_label($this->day_type).$holiday.'</span>';
        }

        if ($this->is_leave == true) {
            return $greenColor.$leave.'</span>';
        }

        return day_type_label($this->day_type).$holiday;
    }

    public function getEntryHoursAttribute()
    {
        $times = 0;
        foreach (collect($this->logs_rates) as $time) {
            $times += $time['hours'];
        }

        return $times;
    }

    /**
     * Whether the day's punches alternate in/out and contain no orphan punch.
     */
    public function getIsPairedAttribute(): bool
    {
        $logs = collect($this->logs ?? [])->values();

        if ($logs->isEmpty()) {
            return $this->legacyColumnsArePaired();
        }

        if ($logs->count() % 2 !== 0) {
            return false;
        }

        $expected = 0;

        foreach ($logs as $log) {
            if ((int) ($log['log_type'] ?? 0) !== $expected) {
                return false;
            }

            $expected = $expected === 0 ? 1 : 0;
        }

        return true;
    }

    public function getHasMissingPunchAttribute(): bool
    {
        return ! $this->is_paired;
    }

    /**
     * Actual hours worked, excluding the night/premium equivalents.
     */
    public function getWorkedHoursAttribute(): float
    {
        $hours = 0.0;
        $hasLines = false;

        foreach (collect($this->logs_rates ?? []) as $key => $line) {
            if (! is_array($line)) {
                continue;
            }

            $key = (string) $key;

            // Night premium lines duplicate the regular hours; only count the
            // ordinary, overtime and night-overtime buckets.
            if (str_ends_with($key, '-nt')) {
                continue;
            }

            if (! str_ends_with($key, '-regular') && ! str_ends_with($key, '-ot')) {
                continue;
            }

            $hasLines = true;
            $actual = (float) ($line['actual'] ?? 0);

            $hours += $actual > 0 ? $actual : (float) ($line['hours'] ?? 0);
        }

        if ($hasLines) {
            return round($hours, 2);
        }

        return $this->legacyWorkedHours();
    }

    /**
     * Pay-equivalent hours: regular, overtime and the night premiums on top.
     */
    public function getEquivalentHoursAttribute(): float
    {
        return round($this->regular + $this->ot + $this->nt + $this->nt_ot, 2);
    }

    public function getTotalHoursAttribute(): float
    {
        return $this->worked_hours;
    }

    private function legacyColumnsArePaired(): bool
    {
        $hasTimeIn = filled($this->am_login) || filled($this->pm_login);
        $hasTimeOut = filled($this->am_logout) || filled($this->pm_logout) || filled($this->am_out_morning);

        return ! ($hasTimeIn xor $hasTimeOut);
    }

    private function legacyWorkedHours(): float
    {
        $minutes = 0.0;

        foreach ([['am_login', 'am_logout'], ['pm_login', 'pm_logout']] as [$in, $out]) {
            if (filled($this->{$in}) && filled($this->{$out})) {
                $minutes += max(0, strtotime((string) $this->{$out}) - strtotime((string) $this->{$in})) / 60;
            }
        }

        if (filled($this->pm_login) && filled($this->am_out_morning)) {
            $minutes += max(0, strtotime('+1 day '.$this->am_out_morning) - strtotime((string) $this->pm_login)) / 60;
        }

        return round($minutes / 60, 2);
    }

    public function getDayTypeHolidayFormatAttribute()
    {
        $dayType = $this->day_type;

        if ($this->holiday_type != null) {

            if ($dayType == static::REGULAR_DAY) {
                return $this->holiday_type;
            } else {
                return $dayType.'-'.$this->holiday_type;
            }
        }

        return $dayType;
    }

    public function getByHolidayType($id, $dayType, $logsRates = null)
    {
        $timesheet = static::find($id);

        $hours = 0;

        if ($timesheet) {

            foreach (collect($timesheet->logs_rates) as $dayRate => $time) {

                if ($dayType == $dayRate) {
                    $hours = $time['hours'];

                    return $hours;
                }
            }
        }

        return $hours;
    }

    public function getHoursByRateAttribute($rate)
    {
        $hours = 0;

        foreach (collect($this->logs_rates) as $dayRate => $time) {
            if ($dayRate == $rate) {
                $hours = $time['hours'];

                return $hours;
            }
        }

        return $hours;
    }

    public function getPmLoginFormatAttribute()
    {
        if ($this->pm_login) {
            return Carbon::createFromFormat('H:i', $this->pm_login)->format('h:i');
        }
    }

    public function getPmLogoutFormatAttribute()
    {
        if ($this->pm_logout) {
            return Carbon::createFromFormat('H:i', $this->pm_logout)->format('h:i');
        }
    }

    public function getTimeInAttribute()
    {
        $log = collect($this->logs ?? [])->first(fn ($log): bool => is_array($log) && (int) ($log['log_type'] ?? 0) === 0);

        if (is_array($log) && is_string($log['time'] ?? null)) {
            return substr($log['time'], 0, 5);
        }

        return $this->am_login ?? $this->pm_login ?? null;
    }

    public function getTimeOutAttribute()
    {
        $log = collect($this->logs ?? [])->reverse()->first(fn ($log): bool => is_array($log) && (int) ($log['log_type'] ?? 0) === 1);

        if (is_array($log) && is_string($log['time'] ?? null)) {
            return substr($log['time'], 0, 5);
        }

        return $this->am_logout ?? $this->pm_logout ?? $this->am_out_morning ?? null;
    }

    public function addNightDifferential($model, $dayType = null, $timeIn = null, $timeOut = null, $nextDay = false)
    {
        // lets say the schedule is 10pm to 6am
        // the night differential can be computed as follows
        // we multiple first the 8 hours to 1.1 then we get 8.8 hrs
        // we subtract 8 hours from 8.8 hours to get .8
        // 0.8 should be the number of hours as night differential
        if ((bool) get_setting('auto_compute_nt')) {

            // Get the time in and time out then check if the
            // hours is included in night diff
            $timeOutToday = 'today';

            if ($nextDay == true) {
                $timeOutToday = 'tomorrow';
            }

            $totalNightHours = night_difference(strtotime('today '.$timeIn), strtotime($timeOutToday.' '.$timeOut));

            if ($totalNightHours > 0) {
                $rateNight = Rate::where('code', $dayType)->first();

                $regularHoursNight = 0;
                if ($rateNight) {

                    $actualTotalNightHours = $totalNightHours;
                    $regularHoursNight = ($totalNightHours * $rateNight->nt) - $totalNightHours;

                    if ($regularHoursNight > 0) {
                        $model->actual_nt = $actualTotalNightHours;
                        $model->addTime($dayType, Timesheet::RATE_NIGHT_DIFFERENTIAL, $regularHoursNight, $timeIn, $timeOut, $actualTotalNightHours);
                    }
                }
            }
        }
    }

    public function addOverTime($model, $dayType = null, $timeIn = null, $timeOut = null, $nextDay = false)
    {
        $actualHoursOvertime = 0;
        if ((bool) get_setting('auto_compute_overtime')) {

            if ($model->schedule_id) {
                // Check if the time out is enough to make overtime
                $minutes = compute_minutes(
                    $model->schedule?->time_in,
                    $model->schedule?->time_out,
                    $timeOut,
                    $model->schedule,
                    $model->log_date->format('Y-m-d')
                );

                // If minutes is more than enough
                if ($minutes >= get_setting('minutes_qualified_overtime')) {

                    $hours = $minutes / 60;

                    $actualHours = $hours;

                    $actualHoursOvertime = $actualHours;

                    // Added
                    $rate = Rate::where('code', $dayType)->first();
                    $hours = ($hours * $rate->ot) - $hours;
                    // Added end
                    $model->actual_ot = $actualHoursOvertime;
                    $model->addTime($dayType, Timesheet::RATE_OVERTIME, $hours, $timeIn, $timeOut, $actualHours);
                }
            }
        }

        return $actualHoursOvertime;
    }

    public $overtimeMinutes;

    public $nightHours;

    public $nightOvertimeHours;

    public function addNightOverTime($model, $dayType = null, $timeIn = null, $timeOut = null, $nextDay = false)
    {
        if ((bool) get_setting('auto_compute_overtime') and (bool) get_setting('auto_compute_nt')) {

            if ($model->schedule_id) {
                // Check if the time out is enough to make overtime
                $minutesOverTime = compute_minutes(
                    $model->schedule?->time_in,
                    $model->schedule?->time_out,
                    $timeOut,
                    $model->schedule,
                    $model->log_date->format('Y-m-d')
                );

                $this->overtimeMinutes = $minutesOverTime;

                $timeOutToday = 'today';

                if ($nextDay) {
                    $timeOutToday = 'tomorrow';
                }

                $totalNightHours = night_difference(strtotime('today '.$timeIn), strtotime($timeOutToday.' '.$timeOut));

                $this->nightHours = $totalNightHours;

                $nightOvertime = $minutesOverTime >= get_setting('minutes_qualified_overtime') && $totalNightHours > 0;

                if ($nightOvertime) {

                    // Add the actual overtime
                    $hoursOvertime = $this->overtimeMinutes / 60;

                    // Added
                    $actualTotalNightHours = $totalNightHours;
                    $actualHoursOvertime = $hoursOvertime;

                    $rate = Rate::where('code', $dayType)->first();
                    $totalNightHours = ($totalNightHours * $rate->nt) - $totalNightHours;
                    $hoursOvertime = ($hoursOvertime * $rate->nt_ot) - $hoursOvertime;
                    // Added end

                    // Put hours over time
                    $model->actual_nt = $actualTotalNightHours;
                    $model->actual_nt_ot = $actualHoursOvertime;
                    $model->addTime($dayType, Timesheet::RATE_NIGHT_DIFFERENTIAL, $totalNightHours, $timeIn, $timeOut, $actualTotalNightHours);
                    $model->addTime($dayType, Timesheet::RATE_NIGHT_DIFFERENTIAL_OVERTIME, $hoursOvertime, $timeIn, $timeOut, $actualHoursOvertime);

                    // Put hours over time
                    // $model->addTime($dayType, Timesheet::RATE_OVERTIME, ($hoursOvertime - $totalNightHours), $timeIn, $timeOut);
                    // $model->addTime($dayType, Timesheet::RATE_NIGHT_DIFFERENTIAL_OVERTIME, $totalNightHours, $timeIn, $timeOut);

                }

                return $nightOvertime;
            }
        }

        return false;
    }

    public function addTime($dayType = null, $fieldRate = null, $hours = 0, $timeIn = null, $timeOut = null, $actualHours = 0)
    {
        // TODO cache me
        $rate = Rate::where('code', $dayType)->first();
        // $this->logs = ['0' => '07:59', '1' => '07:59'];

        // Check if rd then change the timesheet day_type
        if (substr($dayType, 0, 2) == static::REST_DAY) {
            $this->day_type = static::REST_DAY;
        } else {
            $this->day_type = static::REGULAR_DAY;
        }

        $this->save();

        $isLeave = false;
        if (is_leave($rate?->code)) {
            $isLeave = true;
        }

        $logRates = collect($this->logs_rates);

        $index = $dayType.'-'.str_replace('_', '-', $fieldRate);
        // $rateIndex = $fields->rate;

        // If leave then lets use the code like pl or upl (Paid Leave and Unpaid Leave)
        if ($isLeave) {
            $index = $rate->code;
        }

        $hours = $hours;

        if ($dayType == static::ABSENT) {
            $hours = 8;
            // Absent
            $this->is_absent = true;
            $this->save();
        } else {
            $this->is_absent = false;
            $this->save();
        }

        $logRates[$index] = [
            'hours' => $hours,
            'actual' => $actualHours,
            'rate' => $rate->{$fieldRate} ?? 1,
            'is_rest_day' => $rate->is_rest_day,
            'is_holiday' => $rate->is_holiday,
            'logs' => [$timeIn, $timeOut],
        ];

        // If not absent then remove the absent item in array
        if ($dayType != static::ABSENT) {
            if (isset($logRates['absent-'])) {
                unset($logRates['absent-']);
            }
        }

        // If no hours then remove from array
        if ($hours == 0 or $hours == null) {
            unset($logRates[$index]);
        }

        $this->logs_rates = $logRates;

        if (! $isLeave and $dayType != static::ABSENT) {
            // Store the hours to a field like
            // ->regular, ->ot, ->nt ->nt_ot
            $this->{$fieldRate} = $hours;

            // Store rates
            // ->regular_rate, ->ot_rate, ->nt_rate ->nt_ot_rate
            $this->{$fieldRate.'_rate'} = $rate->{$fieldRate};
        }

        // If no hours then remove from timesheet column
        if ($hours == 0 or $hours == null) {
            $this->{$fieldRate} = 0;

            $this->{$fieldRate.'_rate'} = 0;
        }

        if ($dayType == static::ABSENT) {
            // Absent, then remove all entries
            $this->logs_rates = null;
        }

        if ($dayType == static::REST_DAY and $fieldRate == Rate::NO_LOG) {
            // Rest day and selected no log then remove all entries
            // and also set the rate code to regular
            $this->rate_code = static::REGULAR_DAY;
            $this->day_type = static::REST_DAY;
            $this->logs_rates = null;
        }

        if ($timeIn) {
            if ($timeIn >= '12:00') {
                $this->pm_login = $timeIn;
            } else {
                $this->am_login = $timeIn;
            }

            if ($timeOut >= '12:00') {
                $this->pm_logout = $timeOut;
            } else {
                $this->am_logout = $timeOut;
            }

            if ($timeIn >= '12:00' and $timeOut <= '11:59') {
                $this->am_logout = null;
                $this->am_out_morning = $timeOut;
            }
        }

        $this->save();
    }

    // public function addTime($fields, $dayType = null, $fieldRate = null, $hours = null, $timeIn = null, $timeOut = null)
    // {
    //     // TODO cache me
    //     $rate = Rate::where('code', $fields->day_type)->first();
    //     // $this->logs = ['0' => '07:59', '1' => '07:59'];

    //     // Check if rd then change the timesheet day_type
    //     if (substr($fields->day_type, 0, 2) == static::REST_DAY) {
    //         $this->day_type = static::REST_DAY;
    //     } else {
    //         $this->day_type = static::REGULAR_DAY;
    //     }

    //     $this->save();

    //     $isLeave = false;
    //     if (is_leave($rate->code)) {
    //         $isLeave = true;
    //     }

    //     $logRates = collect($this->logs_rates);

    //     $index = $fields->day_type.'-'.str_replace('_', '-', $fields->rate);
    //     // $rateIndex = $fields->rate;

    //     // If leave then lets use the code like pl or upl (Paid Leave and Unpaid Leave)
    //     if ($isLeave) {
    //         $index = $rate->code;
    //     }

    //     $hours = $fields->hours;

    //     if ($fields->day_type == static::ABSENT) {
    //         $hours = 8;
    //         // Absent
    //         $this->is_absent = true;
    //         $this->save();
    //     } else {
    //         $this->is_absent = false;
    //         $this->save();
    //     }

    //     $logRates[$index] = [
    //         'hours' => $hours,
    //         'rate' => $rate->{$fields->rate} ?? 1,
    //         'is_rest_day' => $rate->is_rest_day,
    //         'is_holiday' => $rate->is_holiday,
    //         'logs' => [$fields->time_in, $fields->time_out],
    //     ];

    //     // If not absent then remove the absent item in array
    //     if ($fields->day_type != static::ABSENT) {
    //         if (isset($logRates['absent-'])) {
    //             unset($logRates['absent-']);
    //         }
    //     }

    //     // If no hours then remove from array
    //     if ($hours == 0 or $hours == null) {
    //         unset($logRates[$index]);
    //     }

    //     $this->logs_rates = $logRates;

    //     if (! $isLeave and ! $fields->day_type == static::ABSENT) {
    //         // Store the hours to a field like
    //         // ->regular, ->ot, ->nt ->nt_ot
    //         $this->{$fields->rate} = $hours;

    //         // Store rates
    //         // ->regular_rate, ->ot_rate, ->nt_rate ->nt_ot_rate
    //         $this->{$fields->rate.'_rate'} = $rate->{$index};
    //     }

    //     if ($fields->day_type == static::ABSENT) {
    //         // Absent, then remove all entries
    //         $this->logs_rates = null;
    //     }

    //     if ($fields->day_type == static::REST_DAY and $fields->rate == Rate::NO_LOG) {
    //         // Rest day and selected no log then remove all entries
    //         // and also set the rate code to regular
    //         $this->rate_code = static::REGULAR_DAY;
    //         $this->day_type = static::REST_DAY;
    //         $this->logs_rates = null;
    //     }

    //     $this->save();
    // }

    public function getLogsDisplayAttribute()
    {
        $display = '';
        $display .= '<table class=w-full divide-y divide-gray-100 dark:divide-gray-700"">'; // bg-gray-50 dark:bg-gray-800
        $display .= '<thead class="bg-gray-50 dark:bg-gray-800">
        <th class="text-left px-6 whitespace-nowrap uppercase text-gray-500 text-xxs tracking-wide py-2">Day Type</th>
        <th class="text-left px-6 whitespace-nowrap uppercase text-gray-500 text-xxs tracking-wide py-2">Hrs</th>
        <th class="text-left px-6 whitespace-nowrap uppercase text-gray-500 text-xxs tracking-wide py-2">Rate</th>
        <th class="text-left px-6 whitespace-nowrap uppercase text-gray-500 text-xxs tracking-wide py-2">Logs</th>
        <th class="text-left px-6 whitespace-nowrap uppercase text-gray-500 text-xxs tracking-wide py-2">Hours Equivalent</th>
        </thead>';

        $display .= '<tbody class="divide-y divide-gray-100 dark:divide-gray-700">';

        foreach (collect($this->logs_rates) as $key => $rate) {
            $display .= '<tr>';
            $display .= '<td class="px-2 py-2 whitespace-nowrap dark:bg-gray-800 group-hover:bg-gray-50 dark:group-hover:bg-gray-900">'.$key.'</td>';
            $display .= '<td class="px-2 py-2 whitespace-nowrap dark:bg-gray-800 group-hover:bg-gray-50 dark:group-hover:bg-gray-900">'.@$rate['actual'].'</td>';
            $display .= '<td class="px-2 py-2 whitespace-nowrap dark:bg-gray-800 group-hover:bg-gray-50 dark:group-hover:bg-gray-900">'.$rate['rate'].'</td>';
            $logs = '';
            if (isset($rate['logs'])) {
                $logs = json_encode($rate['logs']);
            }
            $display .= '<td class="px-2 py-2 whitespace-nowrap dark:bg-gray-800 group-hover:bg-gray-50 dark:group-hover:bg-gray-900">'.$logs.'</td>';
            $display .= '<td class="px-2 py-2 whitespace-nowrap dark:bg-gray-800 group-hover:bg-gray-50 dark:group-hover:bg-gray-900">'.$rate['hours'].'</td>';
            $display .= '</tr>';
        }

        $display .= '</tbody></table>';

        return $display;

        return '<p class="text-danger">'.$this->employee?->fullname.' .</p>';
    }

    public function getLogDateDisplayAttribute()
    {
        return '<p class="text-danger"><strong>'.$this->log_date?->format('m/d/Y').' .</strong></p>';

    }

    public function getBioLogsAttribute()
    {
        $bioLogs = '';
        if ($this->am_login && $this->am_logout) {
            $bioLogs .= Carbon::createFromFormat('H:i', $this->am_login)->format('h:i A').' - '.Carbon::createFromFormat('H:i', $this->am_logout)->format('h:i A');
        }
        if ($this->pm_login && $this->pm_logout) {
            $bioLogs .= ', ';
            $bioLogs .= Carbon::createFromFormat('H:i', $this->pm_login)->format('h:i A').' - '.Carbon::createFromFormat('H:i', $this->pm_logout)->format('h:i A');
        }

        return $bioLogs;
    }

    public function office()
    {
        return $this->belongsTo(Office::class);
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function schedule()
    {
        return $this->belongsTo(Schedule::class);
    }

    public function scopeOrderDateName($query)
    {
        // https://reinink.ca/articles/ordering-database-queries-by-relationship-columns-in-laravel
        // $query
        // ->whereNotNull('employee_id')
        // ->orderBy(Employee::select('last_name'))
        // ->whereColumn('employees.id', 'timesheets.employee_id');
        // $this->db->order_by('dtr.office_id, employee.lname, employee.fname, dtr.log_date');
    }

    public function scopeByDates($query, $from, $to)
    {
        if ($from != null && $to != null) {
            $query->where('log_date', '>=', $from)
                ->where('log_date', '<', Carbon::parse($to)->addDay()->toDateString());
        }
    }

    /**
     * Import a biometric log file into the timesheets.
     *
     * @return array{accepted: int, duplicates: int, unmatched: int, invalid: int, blocked: int, timesheets: int}
     */
    public function processImport($file = 'test.txt')
    {
        return app(TimesheetImport::class)->import($file);
    }
}
