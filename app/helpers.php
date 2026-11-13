<?php

use App\Models\Allowance;
use App\Models\Bonus;
use App\Models\Commission;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\PayrollDate;
use App\Models\PayrollGroup;
use App\Models\Rate;
use App\Models\Schedule;
use App\Models\Timesheet;
use App\Models\User;
use App\Support\Tenancy\TenantCache;
use App\Support\Tenancy\TenantContext;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Notifications\NovaNotification;

if (! defined('STATUS_PENDING')) {
    define('STATUS_PENDING', 'Pending');
    define('STATUS_APPROVED', 'Approved');
    define('STATUS_REJECTED', 'Rejected');
    define('STATUS_CANCELLED', 'Cancelled');
}

// const STATUS_APPROVED = 'Approved';

// const STATUS_REJECTED = 'Rejected';

// const STATUS_CANCELLED = 'Cancelled';

if (! function_exists('nova_get_setting')) {
    /**
     * Laravel Nova's settings package is not installed in this build, so read
     * the tenant-scoped settings table directly. The guard keeps the package
     * helper when it is available.
     */
    function nova_get_setting($settingKey, $default = null)
    {
        $setting = App\Models\Setting::query()
            ->where('key', $settingKey)
            ->orderByDesc('id')
            ->first();

        return $setting?->value ?? $default;
    }
}

if (! function_exists('get_setting')) {
    function get_setting($settingKey, $default = null)
    {
        return nova_get_setting($settingKey, $default);
    }
}

if (! function_exists('set_company')) {
    /**
     * @deprecated Tenant models fill company_id automatically.
     */
    function set_company($model, $companyId = null)
    {
        if ($model->company_id != null) {
            return;
        }

        $tenantId = $companyId ?? app(TenantContext::class)->id();

        if ($tenantId != null) {
            $model->company_id = $tenantId;
            $model->save();
        }
    }
}

if (! function_exists('company_settings')) {
    /**
     * @deprecated Tenant models fill company_id automatically.
     */
    function company_settings($model)
    {
        set_company($model);
    }
}

if (! function_exists('default_company_settings')) {
    function default_company_settings()
    {
        return [
            // General settings
            'company_name' => 'Company ABC',
            'trade_name' => 'Trade Name',
            'type' => 'Private',
            'tin' => ' ',
            'rdo' => ' ',
            'sss' => ' ',
            'hdmf' => ' ',
            'phil_health' => ' ',

            // Attendace
            'maximum_pairing_range' => '20',
            'maximum_pairing_range' => '20',
            'minimum_pairing_range' => '5',
            'night_shift_differential_start' => '18:00',
            'night_shift_differential_end' => '06:00',

            // Payroll
            'days_year_no_weeked' => '261',
            'days_year_with_weeked' => '313',
        ];
    }
}

if (! function_exists('date_ft')) {
    function date_ft()
    {
        return 'm/d/Y';
    }
}

if (! function_exists('can_see')) {
    function can_see($menu)
    {
        return function (NovaRequest $request) use ($menu) {

            // If the menu should be hide from user then return false
            //  like this on companies, -> hidden_menu table ["devices"]
            if (in_array($menu, explode(',', env('HIDDEN_MENU')) ?? [])) {
                return false;
            }
            // env('HIDDEN_MENU')
            // if (in_array($menu, $request->user()->company->hidden_menu ?? [])) {
            //     return false;
            // }

            return true;

            return $request->user()->group->permissions[$menu] ?? false;
        };
    }
}

if (! function_exists('can_see_filament')) {
    function can_see_filament($menu, $user = null)
    {
        return true;
        if (in_array($menu, explode(',', env('HIDDEN_MENU')) ?? [])) {
            return false;
        }

        return true;
    }
}

if (! function_exists('filter_fields')) {
    function filter_fields($request, $class = null)
    {
        $defaultClass = '\FieldsFilter';

        if ($class != null) {
            $defaultClass = $class;
        }

        $filterFields = [];

        if (isset($request->filters)) {
            $filters = json_decode(base64_decode($request->filters), true);

            $filterFields = collect($filters)->filter(function ($value, $key) use ($defaultClass) {
                return key($value) == 'App\Nova\Filters'.$defaultClass;
            })->values()->all();

            $filterFields = $filterFields[0]['App\Nova\Filters'.$defaultClass] ?? [];
        }

        return $filterFields;
    }
}

if (! function_exists('show_on_index')) {
    function show_on_index($filterFields, $key)
    {
        if (isset($filterFields[$key])) {
            return $filterFields[$key];
        }

        return false;
    }
}

if (! function_exists('currency_symbol')) {
    function currency_symbol()
    {
        return '';
        // return '₱';
    }
}

if (! function_exists('allow_premium')) {
    function allow_premium()
    {
        return false;
    }
}

if (! function_exists('enable_steps')) {
    function enable_steps($count = 0)
    {
        return false;
        if ($count == 0) {
            return true;
        }

        return false;
    }
}

if (! function_exists('is_leave')) {
    function is_leave($dayType)
    {
        return $dayType == Rate::PAID_LEAVE or $dayType == Rate::UNPAID_LEAVE;
    }
}

// if (! function_exists('rates_options')) {
//     function rates_options()
//     {
//         return cache_rates_options();
//     }
// }

if (! function_exists('rates')) {
    function rates($dayType = null)
    {
        $rates = [];

        $ratesOptions = cache_rates_options();

        foreach ($ratesOptions as $code => $ratesOption) {
            // If we supply day type then we return only one
            if ($dayType != null) {
                if ($dayType == $code) {
                    $rates[$code] = $ratesOption['day_type'];
                }
            } else {
                $rates[$code] = $ratesOption['day_type'];
            }
        }

        return $rates;
    }
}

if (! function_exists('hour_rates')) {
    function hour_rates($cd)
    {
        $rates = [];

        $ratesOptions = cache_rates_options();

        foreach ($ratesOptions as $code => $ratesOption) {
            $rates[$code] = $ratesOption;
        }

        // If rd then we can add option as no log
        if ($cd == Timesheet::REST_DAY) {
            $rates[$cd]['options']['no_log'] = 'No Log (Rest Day)';
        }

        return $rates[$cd]['options'];
    }
}

if (! function_exists('hour_rates_label')) {
    function hour_rates_label($cd, $rate = false)
    {
        $rates = [];

        $ratesOptions = cache_rates_options();

        foreach ($ratesOptions as $code => $ratesOption) {
            $rates[$code] = $ratesOption;
        }

        // if ($rate) {
        //     return $rates[$cd]['options_labels'][$rate];
        // }

        return $rates[$cd]['options_labels'];
    }
}

if (! function_exists('hour_rates_value')) {
    function hour_rates_value($code, $type = 'regular') // can be regular, ot, nt, nt_ot
    {
        return cache_rates_options()[$code][$type];
    }
}

if (! function_exists('day_type_label')) {
    function day_type_label($cd)
    {
        $rates = [];

        $ratesOptions = cache_rates_options();

        foreach ($ratesOptions as $code => $ratesOption) {
            $rates[$code] = $ratesOption;
        }

        if (isset($rates[$cd]['day_type'])) {
            return $rates[$cd]['day_type'];
        }
    }
}

if (! function_exists('compensation_options')) {

    function compensation_options($type)
    {
        if ($type == 'Bonus') {

            return Bonus::pluck('name', 'id')->toArray();
        }

        if ($type == 'Allowance') {

            return Allowance::pluck('name', 'id')->toArray();
        }

        if ($type == 'Commission') {

            return Commission::pluck('name', 'id')->toArray();
        }

        return [];
    }
}

if (! function_exists('isActiveRoute')) {
    function isActiveRoute($routeName, string $addClass = '', ?string $default = null)
    {
        if (is_array($routeName)) {
            if (in_array(request()->route()->getName(), $routeName)) {
                return $addClass.'border-indigo-500 text-gray-900';
            }
        }
        if (request()->route()->named($routeName)) {
            return $addClass.'border-indigo-500 text-gray-900';
        }

        return 'border-transparent text-gray-500';

        if ($default) {
            return $default;
        }
    }
}

if (! function_exists('portalMainActiveRoute')) {
    function portalMainActiveRoute($routeName, string $addClass = '', ?string $default = null)
    {
        if ($routeName == 'requests') {
            if (in_array(request()->route()->getName(), [
                'portal.leaves',
                'portal.attendance',
                'portal.timesheet',
                'portal.schedules',
                'portal.overtimes',
                'portal.officialbusiness',
            ])) {
                return $addClass.'rounded-md bg-gray-900 px-3 py-2 text-sm font-medium text-white';
            }
        }

        if (is_array($routeName)) {
            if (in_array(request()->route()->getName(), $routeName)) {
                return $addClass.'rounded-md bg-gray-900 px-3 py-2 text-sm font-medium text-white';
            }
        }
        if (request()->route()->named($routeName)) {
            return $addClass.'rounded-md bg-gray-900 px-3 py-2 text-sm font-medium text-white';
        }

        return 'rounded-md px-3 py-2 text-sm font-medium text-gray-300 hover:bg-gray-700 hover:text-white';

        if ($default) {
            return $default;
        }
    }
}

if (! function_exists('isActiveRouteSideBar')) {
    function isActiveRouteSideBar(?string $routeName, string $addClass = '', ?string $default = null)
    {
        if (request()->route()->named($routeName)) {
            return $addClass.'bg-gray-100 text-gray-900';
        }

        return 'bg-white text-gray-600';
    }
}

if (! function_exists('isActiveRouteIcon')) {
    function isActiveRouteIcon(?string $routeName, string $addClass = '', ?string $default = null)
    {
        if (request()->route()->named($routeName)) {
            return $addClass.'text-indigo-500 group-hover:text-indigo-500';
        }

        return 'text-gray-400 group-hover:text-gray-500';
    }
}

if (! function_exists('isActiveTab')) {
    function isActiveTab(?string $view)
    {
        if (request('view') == $view) {
            return 'border-indigo-500 text-gray-900';
        }

        return 'border-transparent text-gray-500';
    }
}

if (! function_exists('isActiveRecordSideBarStatus')) {
    function isActiveRecordSideBarStatus(?string $status)
    {
        if (request('status') == $status) {
            return 'bg-gray-50 text-indigo-700 hover:text-indigo-700 hover:bg-white';
        }

        return 'text-gray-900 hover:text-gray-900 hover:bg-gray-50';
    }
}

if (! function_exists('current_now')) {
    function current_now()
    {
        $currentNow = now();

        // If we have fake date then we can run this processes
        // if (nova_get_setting('fake_date_time') != null) {

        //     $currentNow = Carbon::createFromFormat('Y-m-d', nova_get_setting('fake_date_time'));
        // }

        return $currentNow;
    }
}

// cache me
if (! function_exists('night_diff_hours')) {
    function night_diff_hours($schedule = null, $timeFrom = '23:00', $timeTo = '06:00')
    {
        $timeFrom = Carbon::createFromFormat('Y-m-d H:i', '2023-01-01 '.$timeFrom);
        $timeTo = Carbon::createFromFormat('Y-m-d H:i', '2023-01-02 '.$timeTo);

        $hours = CarbonPeriod::create($timeFrom, '1 hour', $timeTo);

        $hoursArray = [];

        foreach ($hours as $hour) {
            $hoursArray[] = $hour->format('H:i');
        }

        return $hoursArray;
    }
}

// https://stackoverflow.com/questions/10532687/time-night-differential-computation-in-php

if (! defined('START_NIGHT_HOUR')) {
    define('START_NIGHT_HOUR', '22');
    define('START_NIGHT_MINUTE', '00');
    define('END_NIGHT_HOUR', '06');
    define('END_NIGHT_MINUTE', '00');
}

// var_dump(night_difference(strtotime('today 19:00'),strtotime('tomorrow 05:30')));
// var_dump(night_difference(strtotime('today 10:00'),strtotime('today 19:00')));
// var_dump(night_difference(strtotime('yesterday 10:00'),strtotime('today 08:00')));
// var_dump(night_difference(strtotime('yesterday 23:00'),strtotime('today 04:00')));
if (! function_exists('night_difference')) {
    function night_difference($startWork, $endWork)
    {
        // nova_get_setting('night_shift_differential_start')
        // nova_get_setting('night_shift_differential_end')
        $startNight = mktime(START_NIGHT_HOUR, START_NIGHT_MINUTE, '00', date('m', $startWork), date('d', $startWork), date('Y', $startWork));
        // dd($startNight);
        // $times = Carbon::createFromFormat('H:i', '22:00')->timestamp;
        // dd($times->timestamp);
        $endNight = mktime(END_NIGHT_HOUR, END_NIGHT_MINUTE, '00', date('m', $startWork), date('d', $startWork) + 1, date('Y', $startWork));

        // When start work is between night diff time or between
        // 10pm and 6am
        if ($startWork >= $startNight && $startWork <= $endNight) {
            if ($endWork >= $endNight) {
                return ($endNight - $startWork) / 3600;
            } else {
                return ($endWork - $startWork) / 3600;
            }
        } elseif ($endWork >= $startNight && $endWork <= $endNight) {
            if ($startWork <= $startNight) {
                return ($endWork - $startNight) / 3600;
            } else {
                return ($endWork - $startWork) / 3600;
            }
        } else {
            if ($startWork < $startNight && $endWork > $endNight) {
                return ($endNight - $startNight) / 3600;
            }

            return 0;
        }
    }
}

if (! function_exists('night_hours')) {
    function night_hours(?Schedule $schedule, $timeFrom = '22:00', $timeTo = '06:00')
    {
        $dateFrom = '2023-01-01';
        $dateTo = '2023-01-01';

        if ($schedule) {
            $timeFrom = $schedule->pm_in;

            // step 1 hour
            $timeFrom = Carbon::createFromFormat('H:i', $timeFrom);
            $timeFrom->addHour();
            $timeFrom = $timeFrom->format('H:i');

            // step 1 hour
            $timeTo = Carbon::createFromFormat('H:i', $timeTo);
            $timeTo->addHour();
            $timeTo = $timeTo->format('H:i');
        }

        // Go to next day
        if ($timeFrom > '12:00' and $timeTo <= '11:59') {
            $dateTo = '2023-01-02';
        }

        $timeFrom = Carbon::createFromFormat('Y-m-d H:i', $dateFrom.' '.$timeFrom);
        $timeTo = Carbon::createFromFormat('Y-m-d H:i', $dateTo.' '.$timeTo);

        $hours = CarbonPeriod::create($timeFrom, '1 hour', $timeTo);

        $hoursArray = [];

        foreach ($hours as $hour) {
            $h = $hour->format('H:').'00';

            if ($h >= '23:00' or $h <= '06:00') {
                $hoursArray[] = $h;
            }
        }

        return $hoursArray;
    }
}

if (! function_exists('setting_field')) {
    function setting_field($field)
    {
        return DB::table('settings')->where('name', '=', $field)->first()->setting_value;
    }
}

if (! function_exists('is_sat_sun')) {
    function is_sat_sun($date)
    {
        return $date->format('l');
    }
}

if (! function_exists('change_format')) {
    function change_format($log, $isPm, $format = '')
    {
        if (
            $log == 'Official Business' || $log == 'Travel Order' || $log == 'TO' || $log == 'Leave' ||
            $log == 'OB' || strtolower($log) == 'offset' ||
            strtolower($log) == 'meeting' || strtolower($log) == 'off' ||
            $log == 'Travel Order'
        ) {
            return $log;
        }
        if ($log == '') {
            return $log;
        }

        if ($isPm == 1) {
            $log = date('h:i', strtotime($log));
        }

        return $log;
    }
}

if (! function_exists('set_current_timestamp')) {
    function set_current_timestamp($model)
    {
        // Set the created_at and updated at if we have fake date
        $model->created_at = current_now();
        $model->updated_at = current_now();
        $model->saveQuietly();
    }
}

if (! function_exists('selectedTab')) {
    function selectedTab(string $thisTab, string $selectedTab)
    {
        if ($thisTab == $selectedTab) {
            return 'border-primary-500 text-primary-600 whitespace-nowrap pb-4 px-1 border-b-2 font-medium text-sm';
        }

        return 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap pb-4 px-1 border-b-2 font-medium text-sm';
    }
}

if (! function_exists('is_holiday')) {
    function is_holiday($date)
    {
        return (bool) Holiday::where('date', $date)->first();
    }
}

if (! function_exists('find_holiday')) {
    function find_holiday($date)
    {
        return Holiday::where('date', $date)->first();
    }
}

if (! function_exists('holiday_name')) {
    function holiday_name($date)
    {
        $holiday = find_holiday($date);
        if ($holiday) {
            return $holiday->name;
        }
    }
}

if (! function_exists('monthly_periods')) {
    function monthly_periods($payrollGroup)
    {
        $payrollDates = PayrollDate::query()
            // ->select('payroll_date', 'first_second')
            ->where('payroll_group_id', $payrollGroup->id)
            ->where('status', PayrollDate::STATUS_OPEN)
            ->where('payroll_date', '>=', now()->subMonth(3)->format('Y-m-d'))
            ->orderBy('payroll_date')
            ->get();

        $periods = [];

        foreach ($payrollDates as $payrollDate) {

            $firstSecond = '';

            if ($payrollDate->first_second != null) {
                $firstSecond = '-'.$payrollDate->first_second;
            }

            $periods[$payrollDate->payroll_date->format('Y-m-d').$firstSecond.'-'.$payrollDate->id] = $payrollDate->payroll_date->format('M d, Y').
                ' ('.$payrollDate->attendance_date_start?->format('M d, Y').' to '.$payrollDate->attendance_date_end?->format('M d, Y').')';
        }

        return $periods;
    }
}

if (! function_exists('holidays')) {
    function holidays($date)
    {
        $days = TenantCache::rememberForever('holidays', function () {
            return Holiday::all();
        });

        $holidays = [];

        foreach ($days as $day) {
            $holidays[$day->date->format('Y-m-d')] = $day->code;
        }

        if (isset($holidays[$date])) {
            return $holidays[$date];
        }

        // TODO we may need to check also if RD for current employee
        return null;
    }
}

// TODO
if (! function_exists('is_log_next_day')) {
    function is_log_next_day($timeIn, $timeOut) {}
}

if (! function_exists('generate_employee_number')) {
    function generate_employee_number()
    {
        
    }
}

if (! function_exists('compute_hours')) {
    function compute_hours($timeIn, $timeOut, $timesheet = null, $nextDay = false)
    {
        if ($timeOut == null) {
            return 0;
        }

        $nextDay = is_next_day($timesheet?->schedule?->time_in, $timesheet?->schedule?->time_out, $timeOut);

        $breakHours = 0;
        if ($timesheet) {
            $logDate = $timesheet->log_date->format('Y-m-d');

            // Need to check if the schedule have break time
            if ($timesheet->schedule?->break_time_start and $timesheet->schedule?->break_time_end) {

                $today = current_now();

                $breakStart = Carbon::createFromFormat('H:i', $timesheet->schedule?->break_time_start);
                $breakEnd = Carbon::createFromFormat('H:i', $timesheet->schedule?->break_time_end);

                // Need to check if the break is another day
                if ($timesheet->schedule?->break_time_start >= '12:00' and $timesheet->schedule?->break_time_end <= '00:00') {
                    // Next day break
                    $breakStart = Carbon::createFromFormat('Y-m-d H:i', $today->format('Y-m-d').' '.$timesheet->schedule?->break_time_start);
                    $breakEnd = Carbon::createFromFormat('Y-m-d H:i', $today->addDay()->format('Y-m-d').' '.$timesheet->schedule?->break_time_end);
                }

                $breakHours = $breakStart->diffInHours($breakEnd);
            }
        }

        if ($timesheet->schedule?->am_out_morning) {
            $nextDay = true;
        }
        // Compute the difference
        $timeIn = Carbon::createFromFormat('Y-m-d H:i', $logDate.' '.$timeIn);
        $timeOut = Carbon::createFromFormat('Y-m-d H:i', $logDate.' '.$timeOut);

        if ($nextDay == true) {
            $timeOut->addDay();
        }

        return $timeIn->diffInHours($timeOut) - $breakHours;
    }
}

if (! function_exists('compute_minutes')) {
    function compute_minutes($schedTimeIn, $schedTimeout, $timeOut, $schedule = null, $logDate = null)
    {
        $nextDay = is_next_day($schedTimeIn, $schedTimeout, $timeOut);

        // Compute the difference in minutes
        $scheduleTimeOut = Carbon::createFromFormat('Y-m-d H:i', $logDate.' '.$schedTimeout);

        // Schedule like 10pm to 6am
        // set the schedule time out next day
        if ($schedTimeIn >= '12:00' and $schedTimeout <= '11:59') {
            $scheduleTimeOut->addDay();
        }

        $timeOut = Carbon::createFromFormat('Y-m-d H:i', $logDate.' '.$timeOut);

        if ($nextDay == true) {
            $timeOut->addDay();
        }

        // If time out is less than the scheduled
        if ($timeOut < $scheduleTimeOut) {
            return 0;
        }

        return $scheduleTimeOut->diffInMinutes($timeOut);
    }
}

if (! function_exists('is_next_day')) {
    function is_next_day($schedTimeIn, $schedTimeout, $timeOut)
    {
        $nextDay = false;

        // Schedule like 2pm to 10pm
        if ($schedTimeIn >= '12:00' and $schedTimeout >= '12:00' and $timeOut <= '11:59') {
            $nextDay = true;
        }

        // Schedule like 10pm to 6am
        if ($schedTimeIn >= '12:00' and $schedTimeout <= '11:59' and $timeOut <= '11:59') {
            $nextDay = true;
        }

        return $nextDay;
    }
}

if (! function_exists('convert_time')) {
    function convert_time($time)
    {
        $time = Carbon::createFromFormat('H:i', $time);

        return $time->format('h:i A');
    }
}

if (! function_exists('menu_icons')) {
    function menu_icons()
    {
        //
    }
}

if (! function_exists('sss_loan_types')) {
    function sss_loan_types()
    {
        return [
            'salary' => 'Salary',
            'calamity' => 'Calamity',
            'educational' => 'Educational',
            'emergency' => 'Emergency',
            'stock' => 'Stock Investment',
        ];
    }
}

if (! function_exists('hdmf_loan_types')) {
    function hdmf_loan_types()
    {
        return [
            'multi_purpose' => 'Multi-Purpose',
            'housing' => 'Housing',
            'short_term' => 'Short-Term',
            'calamity' => 'Calamity',
        ];
    }
}

if (! function_exists('payment_schemes')) {
    function payment_schemes()
    {
        return [
            'first_pay' => 'First pay of the month',
            'last_pay' => 'Last pay of the month',
            'every_pay' => 'Every pay of the month',
        ];
    }
}

if (! function_exists('disburse')) {
    function disburse()
    {
        return [
            'disbursed' => 'Disbursed',
            'not_release' => 'Not yet release',
        ];
    }
}

if (! function_exists('payment_schedules')) {
    function payment_schedules()
    {
        return [
            PayrollGroup::PAY_SCHEDULE_FIRSTPAY => PayrollGroup::PAY_SCHEDULE_FIRSTPAY_TEXT,
            PayrollGroup::PAY_SCHEDULE_LASTPAY => PayrollGroup::PAY_SCHEDULE_LASTPAY_TEXT,
            PayrollGroup::PAY_SCHEDULE_EVERYPAY => PayrollGroup::PAY_SCHEDULE_EVERYPAY_TEXT,
        ];
    }
}

if (! function_exists('payment_types')) {
    function payment_types()
    {
        return [
            Employee::PAYMENT_TYPE_MONTHLY => Employee::PAYMENT_TYPE_MONTHLY_TEXT,
            Employee::PAYMENT_TYPE_DAILY => Employee::PAYMENT_TYPE_DAILY_TEXT,
        ];
    }
}

if (! function_exists('frequency')) {
    function frequency()
    {
        return [
            'daily' => 'Daily',
            'first_day' => 'First day of the month',
            'last_day' => 'Last day of the month',
        ];
    }
}

if (! function_exists('prorated_by')) {
    function prorated_by()
    {
        return [
            'hour' => 'Prorated by hour',
            'day' => 'Prorated by day',
        ];
    }
}

if (! function_exists('is_semi_monthly_first_pay')) {
    function is_semi_monthly_first_pay($payrollGroup, $schedule = 'sss_schedule')
    {
        return $payrollGroup->frequency == PayrollGroup::FREQUENCY_SEMI_MONTHLY and
            $payrollGroup->$schedule === PayrollGroup::PAY_SCHEDULE_FIRSTPAY;
    }
}

if (! function_exists('is_semi_monthly_last_pay')) {
    function is_semi_monthly_last_pay($payrollGroup, $schedule = 'sss_schedule')
    {
        return $payrollGroup->frequency == PayrollGroup::FREQUENCY_SEMI_MONTHLY and
            $payrollGroup->$schedule === PayrollGroup::PAY_SCHEDULE_LASTPAY;
    }
}

if (! function_exists('is_semi_monthly_every_pay')) {
    function is_semi_monthly_every_pay($payrollGroup, $schedule = 'sss_schedule')
    {
        return $payrollGroup->frequency == PayrollGroup::FREQUENCY_SEMI_MONTHLY and
            $payrollGroup->$schedule === PayrollGroup::PAY_SCHEDULE_EVERYPAY;
    }
}

if (! function_exists('is_semi_monthly_compensation_first_pay')) {
    function is_semi_monthly_compensation_first_pay($payrollGroup, $payroll = null)
    {
        return $payrollGroup->frequency == PayrollGroup::FREQUENCY_SEMI_MONTHLY and
            $payroll->cut_off === PayrollGroup::PAY_SCHEDULE_FIRSTPAY;
    }
}

if (! function_exists('is_semi_monthly')) {
    function is_semi_monthly($payrollGroup)
    {
        return $payrollGroup->frequency == PayrollGroup::FREQUENCY_SEMI_MONTHLY;
    }
}

if (! function_exists('is_empty')) {
    function is_empty($field)
    {
        return $field == null or $field == 0;
    }
}

if (! function_exists('requests_status')) {
    function requests_status()
    {
        return [
            STATUS_PENDING => STATUS_PENDING,
            STATUS_APPROVED => STATUS_APPROVED,
            STATUS_CANCELLED => STATUS_CANCELLED,
            STATUS_REJECTED => STATUS_REJECTED,
        ];
    }
}

if (! function_exists('status_badge')) {
    function status_badge()
    {
        return [
            STATUS_PENDING => 'warning',
            STATUS_APPROVED => 'success',
            STATUS_CANCELLED => 'danger',
            STATUS_REJECTED => 'danger',
        ];
    }
}

if (! function_exists('notify')) {
    function notify(User $user, $title, $url, $icon = 'download', $type = 'info')
    {
        if ($user != null) {
            $user->notify(
                NovaNotification::make()
                    ->message($title)
                    ->action('View', $url)
                    // ->icon('download')
                    ->type($type)
            );
        }
    }
}

if (! function_exists('group_access')) {
    function group_access()
    {
        return [
            'Company Settings' => 'Company Settings',
            'Offices' => 'Offices',
            'Employees' => 'Employees',
            'Users' => 'Users',
            'Departments' => 'Departments',
            'Devices' => 'Devices',
            'Holidays' => 'Holidays',

            // 'Users' => [
            //     'Users' => 'Users',
            // ],
        ];
    }
}

if (! function_exists('region_options')) {
    function region_options(): array
    {
        return [
            'NCR' => 'NCR',
            'CAR' => 'CAR',
            'Region I' => 'Region I',
            'Region II' => 'Region II',
            'Region III' => 'Region III',
            'Region IVA' => 'Region IV-A (CALABARZON)',
            'Region IVB' => 'Region IV-B (MIMAROPA)',
            'Region V' => 'Region V',
            'Region VI' => 'Region VI',
            'Region VII' => 'Region VII',
            'Region VIII' => 'Region VIII',
            'Region IX' => 'Region IX',
            'Region X' => 'Region X',
            'Region XI' => 'Region XI',
            'Region XII' => 'Region XII',
            'Region XIII' => 'Region XIII',
            'BARMM' => 'BARMM',
        ];
    }
}

if (! function_exists('industry_options')) {
    function industry_options(): array
    {
        return [
            'NON-AGRICULTURE' => 'Non-agriculture',
            'AGRICULTURE' => 'Agriculture',
            'GENERAL' => 'General',
        ];
    }
}

if (! function_exists('mask_id')) {
    function mask_id(?string $value): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return '—';
        }

        $length = strlen($value);

        return $length <= 4
            ? str_repeat('•', $length)
            : str_repeat('•', $length - 4).substr($value, -4);
    }
}
