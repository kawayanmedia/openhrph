<?php

namespace App\Livewire\Admin\Actions;

use App\Livewire\Admin\Fields\Boolean;
use App\Livewire\Admin\Fields\Heading;
use App\Livewire\Admin\Fields\Select;
use App\Livewire\Admin\Fields\Text;
use App\Livewire\Admin\Support\FormData;
use App\Models\Rate;
use App\Models\Schedule;
use App\Models\Timesheet;
use App\Services\Attendance\AttendancePeriod;
use App\Services\Attendance\PunchLedger;
use App\Services\Attendance\TimesheetCalculator;
use App\Services\HrAudit;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Http\Request;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Collection;

/**
 * Correct one attendance day.
 *
 * The old editor wrote premium-equivalent hours through Timesheet::addTime().
 * This one only classifies the day and, when asked, replaces the raw punches;
 * every hour bucket is then derived by the shared TimesheetCalculator.
 */
class EditViewTimeSheet extends Action
{
    use InteractsWithQueue, Queueable;

    public string $name = 'Time';

    public string $confirmButtonText = 'Update';

    public string $confirmText = '';

    public function __construct($model = null)
    {
        parent::__construct($model);

        if (! is_null($resourceId = request('resourceId'))) {
            $this->model = Timesheet::find($resourceId);
        }
    }

    /**
     * Perform the action on the given models.
     */
    public function handle(Request $request, array $fields, Collection $models): mixed
    {
        $period = app(AttendancePeriod::class);
        $calculator = app(TimesheetCalculator::class);

        if ($models->contains(fn (Timesheet $model): bool => $this->isLocked($model, $period))) {
            return self::message('This timesheet is locked because payroll for the period has been approved or the cut-off is closed. Reopen the payroll before editing attendance.');
        }

        foreach ($models as $model) {
            $before = $this->snapshot($model);

            $code = (string) ($fields['day_type'] ?? $model->day_rate_code ?? Timesheet::REGULAR_DAY);

            $model->day_rate_code = $code;
            $model->is_paid_leave = $code === Rate::PAID_LEAVE;
            $model->is_leave = in_array($code, [Rate::PAID_LEAVE, Rate::UNPAID_LEAVE], true);
            $model->is_absent = $code === Timesheet::ABSENT;
            $model->day_type = str_starts_with($code, Timesheet::REST_DAY)
                ? Timesheet::REST_DAY
                : Timesheet::REGULAR_DAY;

            if (! empty($fields['schedule_id'])) {
                $model->schedule_id = (int) $fields['schedule_id'];
            }

            $model->save();

            $isNoLog = $model->is_leave || $model->is_absent;

            if (! $isNoLog && (bool) ($fields['replace_punches'] ?? false)) {
                $error = $this->replacePunches($model, $fields);

                if ($error !== null) {
                    return self::message($error);
                }
            }

            $calculator->compute($model);

            $this->recordAudit($model, $before);
        }

        return self::message('Timesheet has been updated.');
    }

    /**
     * Replace the day's punches with the times entered in the form.
     *
     * The old punches stay in the audit snapshot; the append-only punch ledger
     * (when present) records the replacement separately.
     *
     * @param  array<string, mixed>  $fields
     */
    private function replacePunches(Timesheet $model, array $fields): ?string
    {
        $timeIn = trim((string) ($fields['time_in'] ?? ''));
        $timeOut = trim((string) ($fields['time_out'] ?? ''));

        if ($timeIn === '' || $timeOut === '') {
            return 'Enter both a time in and a time out, or clear "Replace raw punches".';
        }

        $date = $model->log_date;

        if ($date === null) {
            return 'This attendance day has no date.';
        }

        $nextDay = (bool) ($fields['time_out_next_day'] ?? false);
        $outDate = CarbonImmutable::parse($date->toDateString());

        if ($nextDay || $timeOut <= $timeIn) {
            $outDate = $outDate->addDay();
        }

        $in = $this->punch($date->toDateString(), $timeIn, 0);
        $out = $this->punch($outDate->toDateString(), $timeOut, 1);

        $model->logs = [$in, $out];
        $model->save();

        app(PunchLedger::class)->replace($model, [$in, $out]);

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function punch(string $date, string $time, int $logType): array
    {
        $time = strlen($time) === 5 ? $time.':00' : $time;

        return [
            'log_date' => $date,
            'time' => $time,
            'log_type' => $logType,
            'source' => 'manual',
            'device' => null,
            'recorded_at' => now()->toDateTimeString(),
            'punched_at' => $date.' '.$time,
        ];
    }

    private function isLocked(Timesheet $timesheet, AttendancePeriod $period): bool
    {
        if ((bool) $timesheet->is_locked) {
            return true;
        }

        $employee = $timesheet->employee;

        return $employee !== null && $period->isClosedFor($employee, $timesheet->log_date);
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(Timesheet $timesheet): array
    {
        return [
            'day_type' => $timesheet->day_type,
            'day_rate_code' => $timesheet->day_rate_code,
            'regular' => $timesheet->getRawOriginal('regular'),
            'ot' => $timesheet->getRawOriginal('ot'),
            'nt' => $timesheet->getRawOriginal('nt'),
            'nt_ot' => $timesheet->getRawOriginal('nt_ot'),
            'actual_regular' => $timesheet->getRawOriginal('actual_regular'),
            'actual_ot' => $timesheet->getRawOriginal('actual_ot'),
            'actual_nt' => $timesheet->getRawOriginal('actual_nt'),
            'actual_nt_ot' => $timesheet->getRawOriginal('actual_nt_ot'),
            'paid_minutes' => $timesheet->getRawOriginal('paid_minutes'),
            'scheduled_minutes' => $timesheet->getRawOriginal('scheduled_minutes'),
            'late_minutes' => $timesheet->getRawOriginal('late_minutes'),
            'undertime_minutes' => $timesheet->getRawOriginal('undertime_minutes'),
            'is_absent' => (bool) $timesheet->is_absent,
            'is_leave' => (bool) $timesheet->is_leave,
            'logs' => $timesheet->logs,
            'logs_rates' => $timesheet->logs_rates,
        ];
    }

    /**
     * @param  array<string, mixed>  $before
     */
    private function recordAudit(Timesheet $model, array $before): void
    {
        HrAudit::record((int) $model->company_id, 'timesheet_updated', $model->employee_id, null, [
            'timesheet_id' => $model->getKey(),
            'log_date' => $model->log_date?->toDateString(),
            'before' => $before,
            'after' => $this->snapshot($model->refresh()),
        ]);
    }

    /**
     * Get the fields available on the action.
     */
    public function fields(Request $request): array
    {
        $dayType = $this->model->day_type;

        if ($this->model->is_absent) {
            $dayType = Timesheet::ABSENT;
        }

        if ($this->model->holiday_code) {
            $dayType = $this->model->day_type == Timesheet::REST_DAY
                ? Timesheet::REST_DAY.'-'.$this->model->holiday_code
                : $this->model->holiday_code;
        }

        return [
            Text::make('timesheet_id', 'timesheet_id')->default($this->model->id)->readonly(),

            Heading::make($this->model->log_date_display)->asHtml(),

            Select::make('Schedule', 'schedule_id')
                ->options(Schedule::pluck('name', 'id')->toArray())
                ->default($this->model->schedule_id)
                ->searchable()
                ->sortable(),

            Select::make('Day Type', 'day_type')
                ->options(rates())
                ->default($dayType)
                ->sortable()
                ->rules('required'),

            Text::make('Time In', 'time_in')
                ->rules('required')
                ->default($this->model->time_in ?? $this->model->schedule?->time_in ?? '08:00')
                ->withMeta(['extraAttributes' => ['type' => 'time']])
                ->dependsOn(['day_type'], function (Text $field, Request $request, FormData $formData): void {
                    if (is_leave($formData->day_type) || $formData->day_type == Timesheet::ABSENT) {
                        $field->hide();
                        $field->rules();
                    }
                }),

            Text::make('Time Out', 'time_out')
                ->rules('required')
                ->default($this->model->time_out ?? $this->model->schedule?->time_out ?? '17:00')
                ->withMeta(['extraAttributes' => ['type' => 'time']])
                ->dependsOn(['day_type'], function (Text $field, Request $request, FormData $formData): void {
                    if (is_leave($formData->day_type) || $formData->day_type == Timesheet::ABSENT) {
                        $field->hide();
                        $field->rules();
                    }
                }),

            Boolean::make('Time Out Next Day', 'time_out_next_day')
                ->default(false),

            Boolean::make('Replace raw punches', 'replace_punches')
                ->default(false)
                ->help('Write the times above as the day\'s punches. Leave off to keep the device, portal and correction punches.'),

            Heading::make($this->model->punches_display)->asHtml(),

            Heading::make($this->model->logs_display)->asHtml(),
        ];
    }
}
