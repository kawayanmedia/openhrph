<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Services\Attendance\TimesheetImport;
use App\Services\Payroll\Computation\Rules;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

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

    /**
     * Rule version written by the punch-derived calculator. From this version
     * on, `actual_ot` already includes night overtime, so the payroll mapper
     * must not add `actual_nt_ot` again.
     */
    const RULE_VERSION_NIGHT_OT_INCLUDED = 2;

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
        'computed_at' => 'datetime',
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

    /**
     * The canonical payable classification (regular_holiday, special_holiday,
     * rest_day_regular_holiday, paid_leave, ...).
     */
    public function getPaidDayTypeAttribute(): string
    {
        return Rules::effectiveDayType(
            $this->getAttribute('day_type'),
            $this->getAttribute('day_rate_code'),
        );
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
     * Everything a timekeeper should review on this day.
     *
     * @return list<string>
     */
    public function getExceptionFlagsAttribute(): array
    {
        $flags = [];

        if ($this->has_missing_punch) {
            $flags[] = 'Missing punch';
        }

        if ((int) ($this->late_minutes ?? 0) > 0) {
            $flags[] = 'Late';
        }

        if ((int) ($this->undertime_minutes ?? 0) > 0) {
            $flags[] = 'Undertime';
        }

        if ($this->schedule_id === null && $this->shift_id === null) {
            $flags[] = 'No schedule';
        }

        if ($this->day_type === static::REST_DAY && (int) ($this->paid_minutes ?? 0) > 0) {
            $flags[] = 'Rest-day work';
        }

        if ((bool) $this->is_absent) {
            $flags[] = 'Absent';
        }

        return $flags;
    }

    public function getExceptionFlagsFormatAttribute(): string
    {
        $flags = $this->exception_flags;

        return $flags === [] ? '—' : implode(', ', $flags);
    }

    /**
     * Actual hours worked, excluding the night/premium equivalents.
     */
    public function getWorkedHoursAttribute(): float
    {
        if ($this->getAttribute('paid_minutes') !== null) {
            return round(((int) $this->getAttribute('paid_minutes')) / 60, 2);
        }

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

    /**
     * Raw punches, as recorded, for the admin day detail.
     */
    public function getPunchesDisplayAttribute(): string
    {
        $ledger = Schema::hasTable('attendance_punches')
            ? AttendancePunch::query()
                ->where('timesheet_id', $this->getKey())
                ->orderBy('punched_at')
                ->get()
            : collect();

        if ($ledger->isNotEmpty()) {
            return $this->renderPunchLedger($ledger);
        }

        $logs = collect($this->logs ?? [])->filter(fn ($log): bool => is_array($log) && isset($log['time']))->values();

        if ($logs->isEmpty()) {
            return '<p class="text-sm text-gray-500 dark:text-gray-400">No punches recorded.</p>';
        }

        $display = '<table class="w-full divide-y divide-gray-100 dark:divide-gray-700">';
        $display .= '<thead class="bg-gray-50 dark:bg-gray-800"><tr>';
        $display .= '<th class="text-left px-3 py-2 uppercase text-gray-500 text-xxs tracking-wide">Time</th>';
        $display .= '<th class="text-left px-3 py-2 uppercase text-gray-500 text-xxs tracking-wide">Type</th>';
        $display .= '<th class="text-left px-3 py-2 uppercase text-gray-500 text-xxs tracking-wide">Source</th>';
        $display .= '<th class="text-left px-3 py-2 uppercase text-gray-500 text-xxs tracking-wide">Device</th>';
        $display .= '</tr></thead><tbody class="divide-y divide-gray-100 dark:divide-gray-700">';

        foreach ($logs as $log) {
            $type = (int) ($log['log_type'] ?? 0) === 0 ? 'In' : 'Out';

            $display .= '<tr>';
            $display .= '<td class="px-3 py-2 whitespace-nowrap text-sm">'.e((string) $log['time']).'</td>';
            $display .= '<td class="px-3 py-2 whitespace-nowrap text-sm">'.e($type).'</td>';
            $display .= '<td class="px-3 py-2 whitespace-nowrap text-sm">'.e((string) ($log['source'] ?? 'biometric')).'</td>';
            $display .= '<td class="px-3 py-2 whitespace-nowrap text-sm">'.e((string) ($log['device'] ?? '—')).'</td>';
            $display .= '</tr>';
        }

        $display .= '</tbody></table>';

        return $display;
    }

    /**
     * @param  Collection<int, AttendancePunch>  $punches
     */
    private function renderPunchLedger($punches): string
    {
        $display = '<table class="w-full divide-y divide-gray-100 dark:divide-gray-700">';
        $display .= '<thead class="bg-gray-50 dark:bg-gray-800"><tr>';
        $display .= '<th class="text-left px-3 py-2 uppercase text-gray-500 text-xxs tracking-wide">Punched</th>';
        $display .= '<th class="text-left px-3 py-2 uppercase text-gray-500 text-xxs tracking-wide">Type</th>';
        $display .= '<th class="text-left px-3 py-2 uppercase text-gray-500 text-xxs tracking-wide">Source</th>';
        $display .= '<th class="text-left px-3 py-2 uppercase text-gray-500 text-xxs tracking-wide">Device</th>';
        $display .= '<th class="text-left px-3 py-2 uppercase text-gray-500 text-xxs tracking-wide">Status</th>';
        $display .= '</tr></thead><tbody class="divide-y divide-gray-100 dark:divide-gray-700">';

        foreach ($punches as $punch) {
            $voided = $punch->isVoided();

            $display .= '<tr'.($voided ? ' class="text-gray-400 line-through"' : '').'>';
            $display .= '<td class="px-3 py-2 whitespace-nowrap text-sm">'.e((string) $punch->punched_at?->format('Y-m-d H:i:s')).'</td>';
            $display .= '<td class="px-3 py-2 whitespace-nowrap text-sm">'.e($punch->directionLabel()).'</td>';
            $display .= '<td class="px-3 py-2 whitespace-nowrap text-sm">'.e((string) $punch->source).'</td>';
            $display .= '<td class="px-3 py-2 whitespace-nowrap text-sm">'.e((string) ($punch->device ?? '—')).'</td>';
            $display .= '<td class="px-3 py-2 whitespace-nowrap text-sm">'.e($voided ? 'Voided: '.($punch->void_reason ?? '') : 'Active').'</td>';
            $display .= '</tr>';
        }

        $display .= '</tbody></table>';

        return $display;
    }

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
