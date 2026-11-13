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
use Illuminate\Bus\Queueable;
use Illuminate\Http\Request;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Collection;

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
        foreach ($models as $model) {
            $model->is_paid_leave = $fields['day_type'] == Rate::PAID_LEAVE;

            if ($fields['day_type'] == Rate::PAID_LEAVE or $fields['day_type'] == Rate::UNPAID_LEAVE) {
                $model->is_leave = true;
            }

            $rate = $fields['rate'];

            $regularHours = $fields['hours'];

            // Reset the timesheet
            $model->logs_rates = null;
            $model->am_login = null;
            $model->pm_login = null;
            $model->am_logout = null;
            $model->pm_logout = null;
            $model->am_out_morning = null;

            $model->regular = null;
            $model->ot = null;
            $model->nt = null;
            $model->nt_ot = null;

            $model->regular_rate = null;
            $model->ot_rate = null;
            $model->nt_rate = null;
            $model->nt_ot_rate = null;

            $model->actual_regular = null;
            $model->actual_ot = null;
            $model->actual_nt = null;
            $model->actual_nt_ot = null;

            $model->save();

            if ($fields['day_type'] == Timesheet::REST_DAY && $rate == Rate::NO_LOG) {
                $model->day_type = Timesheet::REST_DAY;
                $model->save();

                return self::message('Timesheet has been updated.');
            }

            if ($fields['day_type'] != Timesheet::ABSENT) {
                // Check first of the overtime is also in night
                $isOtNt = $model->addNightOverTime($model, $fields['day_type'], $fields['time_in'], $fields['time_out'], $fields['time_out_next_day']);

                if ($isOtNt) {
                    $regularHours = 8;
                }

                if (! $isOtNt) {
                    $actualHoursOvertime = $model->addOverTime($model, $fields['day_type'], $fields['time_in'], $fields['time_out'], $fields['time_out_next_day']);

                    if ($actualHoursOvertime != 0) {
                        $regularHours = 8;
                    }

                    $model->addNightDifferential($model, $fields['day_type'], $fields['time_in'], $fields['time_out'], $fields['time_out_next_day']);
                }
            }

            $model->actual_regular = $regularHours;
            $model->addTime($fields['day_type'], $rate, $regularHours, $fields['time_in'], $fields['time_out'], $regularHours);
        }

        return self::message('Timesheet has been updated.');
    }

    /**
     * Get the fields available on the action.
     */
    public function fields(Request $request): array
    {
        $dayType = $this->model->day_type;

        // If absent
        if ($this->model->is_absent) {
            $dayType = Timesheet::ABSENT;
        }

        if ($this->model->holiday_code) {
            // Check if rd
            if ($this->model->day_type == Timesheet::REST_DAY) {
                $dayType = Timesheet::REST_DAY.'-'.$this->model->holiday_code;
            } else {
                $dayType = $this->model->holiday_code;
            }
        }

        return [
            Text::make('timesheet_id', 'timesheet_id')->default($this->model->id)->readonly(),

            Heading::make($this->model->log_date_display)->asHtml(),

            Select::make('Schedule', 'schedule_id')
                ->options(Schedule::pluck('name', 'id')->toArray())
                ->default($this->model->schedule_id)
                ->searchable()
                ->sortable()
                ->rules('required'),

            Select::make('Day Type', 'day_type')
                ->options(rates())
                ->default($dayType)
                ->sortable()
                ->rules('required'),

            Select::make('Rate', 'rate')
                ->options(hour_rates($dayType))
                ->sortable()
                ->default($this->model->holiday_type ?? Timesheet::REGULAR_DAY)
                ->rules('required')
                ->hideFromIndex()
                ->dependsOn(['day_type'], function (Select $field, Request $request, FormData $formData) {
                    if ($formData->day_type) {
                        $field->options(hour_rates($formData->day_type));
                    }

                    if (is_leave($formData->day_type) || $formData->day_type == Timesheet::ABSENT || $formData->day_type == Timesheet::UNDERTIME) {
                        $field->hide();
                        $field->rules();
                    }
                }),

            Text::make('Time In', 'time_in')
                ->rules('required')
                ->default($this->model->time_in ?? $this->model->schedule?->time_in ?? '08:00')
                ->withMeta([
                    'extraAttributes' => ['type' => 'time'],
                ])
                ->dependsOn(['day_type', 'rate'], function (Text $field, Request $request, FormData $formData) {
                    if (is_leave($formData->day_type) || $formData->day_type == Timesheet::ABSENT || $formData->rate == Rate::NO_LOG) {
                        $field->hide();
                        $field->rules();
                    }
                }),

            Text::make('Time Out', 'time_out')
                ->rules('required')
                ->default($this->model->time_out ?? $this->model->schedule?->time_out ?? '17:00')
                ->withMeta([
                    'extraAttributes' => ['type' => 'time'],
                ])
                ->dependsOn(['day_type', 'rate'], function (Text $field, Request $request, FormData $formData) {
                    if (is_leave($formData->day_type) || $formData->day_type == Timesheet::ABSENT || $formData->rate == Rate::NO_LOG) {
                        $field->hide();
                        $field->rules();
                    }
                }),

            Boolean::make('Time Out Next Day', 'time_out_next_day'),

            Text::make('Hours', 'hours')
                ->rules('required')
                ->help(''),

            Heading::make($this->model->logs_display)->asHtml(),
        ];
    }
}
