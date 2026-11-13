<?php

namespace App\Nova\Actions;

use App\Models\Holiday;
use App\Models\Rate;
use App\Models\Schedule;
use App\Models\Timesheet;
use Eminiarts\Tabs\Traits\HasTabs;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\FormData;
use Laravel\Nova\Fields\Heading;
use Laravel\Nova\Fields\Hidden;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use Lexicon\ActionButtonSelector\ActionAsButton;

class EditViewTimeSheet extends Action
{
    use InteractsWithQueue, Queueable;
    // use HasTabs;

    public $name = 'Time';

    public $confirmButtonText = 'Update';

    public $confirmText = '';

    public $holiday;

    public $model;

    public function __construct($model = null)
    {
        $this->model = $model;

        // Check the date for any holiday;
        if ($this->model) {
            // $this->holiday = Holiday::where('date', $this->model->log_date->format('Y-m-d'))->first();
        }

        if (! is_null($resourceId = request('resourceId'))) {
            $this->model = Timesheet::find($resourceId);
        }
    }

    /**
     * Perform the action on the given models.
     *
     * @return mixed
     */
    public function handle(ActionFields $fields, Collection $models)
    {
        foreach ($models as $model) {
            // $model->day_type = $fields->day_type;
            // $model->holiday_type = $fields->holiday_type;
            $model->is_paid_leave = $fields->day_type == Rate::PAID_LEAVE; //  id paid leave
            if ($fields->day_type == Rate::PAID_LEAVE or $fields->day_type == Rate::UNPAID_LEAVE) {
                $model->is_leave = true;
            }

            $rate = $fields->rate;

            $regularHours = $fields->hours;

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

            if ($fields->day_type == Timesheet::REST_DAY && $rate == Rate::NO_LOG) {
                $model->day_type = Timesheet::REST_DAY;
                $model->save();

                return Action::message('Timesheet has been updated.');
            }

            if ($fields->day_type != Timesheet::ABSENT) {

                // Check first of the overtime is also in night
                $isOtNt = $model->addNightOverTime($model, $fields->day_type, $fields->time_in, $fields->time_out, $fields->time_out_next_day);

                if ($isOtNt) {
                    $regularHours = 8;
                }

                if (! $isOtNt) {
                    $actualHoursOvertime = $model->addOverTime($model, $fields->day_type, $fields->time_in, $fields->time_out, $fields->time_out_next_day);

                    if ($actualHoursOvertime != 0) {
                        $regularHours = 8;
                    }
                    $model->addNightDifferential($model, $fields->day_type, $fields->time_in, $fields->time_out, $fields->time_out_next_day);
                }
            }

            $model->actual_regular = $regularHours;
            $model->addTime($fields->day_type, $rate, $regularHours, $fields->time_in, $fields->time_out, $regularHours);
        }

        return Action::message('Timesheet has been updated.');
    }

    /**
     * Get the fields available on the action.
     *
     * @return array
     */
    public function fields(NovaRequest $request)
    {
        $dayType = $this->model->day_type;

        // If absent
        if ($this->model->is_absent) {
            $dayType = Timesheet::ABSENT;
        }

        if ($this->model->holiday_code) {

            // Check if rd
            if ($this->model->day_type == Timesheet::REST_DAY) {
                $dayType = Timesheet::REST_DAY . '-' . $this->model->holiday_code;
            } else {
                $dayType = $this->model->holiday_code;
            }
        }

        return [
            Hidden::make('timesheet_id', 'timesheet_id')->default($this->model->id),

            Heading::make($this->model->log_date_display)->asHtml(),

            Select::make('Schedule', 'schedule_id')
                ->options(Schedule::pluck('name', 'id')->toArray()) //
                ->default($this->model->schedule_id)
                ->searchable()
                ->sortable()
                ->rules('required'),

            Select::make('Day Type', 'day_type')
                // ->options(rates($dayType)) // use me on prod
                ->options(rates()) //
                ->default($dayType)
                ->sortable()
                ->rules('required'),

            Select::make('Rate', 'rate')
                ->sortable()
                ->default($this->model->holiday_type ?? Timesheet::REGULAR_DAY)
                ->rules('required')
                ->hideFromIndex()
                ->dependsOn(
                    ['day_type'],
                    function (Select $field, NovaRequest $request, FormData $formData) {
                        if ($formData->day_type) {
                            $field->options(hour_rates($formData->day_type));
                        }

                        if (is_leave($formData->day_type) or $formData->day_type == Timesheet::ABSENT or $formData->day_type == Timesheet::UNDERTIME) {
                            $field->hide();
                            $field->rules('');
                        }
                    }
                ),

            // We can show depending on schedule of the employee
            Text::make('Time In', 'time_in')
                ->rules('required')
                // ->default('08:00')
                ->default($this->model->time_in ?? $this->model->schedule?->time_in ?? '08:00')
                ->dependsOn(
                    ['day_type', 'rate'],
                    function (Text $field, NovaRequest $request, FormData $formData) {

                        if (is_leave($formData->day_type) or $formData->day_type == Timesheet::ABSENT or $formData->rate == Rate::NO_LOG) {
                            $field->hide();
                            $field->rules('');
                        }
                    }
                )
                ->withMeta([
                    'extraAttributes' => ['type' => 'time']
                ]),

            // We can add break in depending on schedule of the employee
            // Text::make('Break In', 'break_in')
            //     ->default($this->model->time_out ?? $this->model->schedule?->time_out ?? '17:00')
            //     ->hide()
            //     ->dependsOn(
            //         ['day_type', 'rate'],
            //         function (Text $field, NovaRequest $request, FormData $formData) {

            //             // if (is_leave($formData->day_type) or $formData->day_type == Timesheet::ABSENT or $formData->rate == Rate::NO_LOG) {
            //             //     $field->hide();
            //             //     $field->rules('');
            //             // }
            //         }
            //     )
            //     ->withMeta([
            //         'extraAttributes' => ['type' => 'time']]),

            Text::make('Time Out', 'time_out')
                ->rules('required')
                ->default($this->model->time_out ?? $this->model->schedule?->time_out ?? '17:00')
                ->dependsOn(
                    ['day_type', 'rate'],
                    function (Text $field, NovaRequest $request, FormData $formData) {

                        if (is_leave($formData->day_type) or $formData->day_type == Timesheet::ABSENT or $formData->rate == Rate::NO_LOG) {
                            $field->hide();
                            $field->rules('');
                        }
                    }
                )
                ->withMeta([
                    'extraAttributes' => ['type' => 'time']
                ]),

            Boolean::make('Time Out Next Day', 'time_out_next_day')
                ->hide()
                ->dependsOn(
                    ['time_out', 'time_in'],
                    function (Boolean $field, NovaRequest $request, FormData $formData) {

                        if ($formData->time_out <= '11:59') {
                            $field->show();
                            if ($formData->time_in >= '12:00') {
                                $field->withMeta(['value' => true]);
                            } else {
                                $field->withMeta(['value' => false]);
                                $field->hide();
                            }
                        }
                    }
                ),

            Text::make('Hours')
                ->rules('required')
                // ->default($this->model->entry_hours)
                ->dependsOn(
                    ['day_type', 'rate', 'timesheet_id', 'time_in', 'time_out'],
                    function (Text $field, NovaRequest $request, FormData $formData) {
                        if ($formData->rate) {
                            $type = $formData->day_type . '-' . str_replace('_', '-', $formData->rate);
                            $hours = $this->model->getByHolidayType($formData->timesheet_id, $type);

                            if ($formData->time_in == '08:00' and $formData->time_out == '17:00') {
                                $hours = 8;
                            } else {
                                // Compute the difference if user change the time
                                $timesheet = Timesheet::find($request->timesheet_id);
                                if ($timesheet) {
                                    if ($formData->rate != Rate::NO_LOG) {
                                        $hours = compute_hours($formData->time_in, $formData->time_out, $timesheet);
                                    }
                                }
                            }

                            // if ($hours > 9) {
                            //     $field->help('The hours is more than 8.  Suggestion: Create other entry and put on Rate - OT');
                            // }

                            $field->value = $hours;
                        }

                        if ($formData->day_type == Timesheet::ABSENT) {
                            $field->hide();
                            $field->rules('');
                        }

                        if ($formData->rate == Rate::NO_LOG) {
                            $field->hide();
                            $field->rules('');
                        }
                    }
                )
                ->help(''),

            Heading::make($this->model->logs_display)->asHtml(),
        ];
    }
}
