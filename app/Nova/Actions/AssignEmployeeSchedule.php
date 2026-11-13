<?php

namespace App\Nova\Actions;

use App\Gateways\GenerateTimesheetGateway;
use App\Models\EmployeeSchedule;
use App\Models\Schedule;
use App\Models\Timesheet;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Date;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Http\Requests\NovaRequest;

class AssignEmployeeSchedule extends Action
{
    use InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Determine if the given resource is authorizable.
     *
     * @return bool
     */
    // public static function authorizable()
    // {
    //     return true;
    // }

    public $model;

    public function __construct($model = null)
    {
        $this->model = $model;
    }

    /**
     * Perform the action on the given models.
     *
     * @return mixed
     */
    public function handle(ActionFields $fields, Collection $models)
    {
        foreach ($models as $model) {

            $endDate = $fields->effective_date_end;

            // Remove schedule first
            EmployeeSchedule::where('employee_id', $model->id)
                ->where('from_date', $fields->effective_date_start)
                ->where('to_date', $endDate)
                ->delete();

            EmployeeSchedule::firstOrCreate([
                'schedule_id' => $fields->schedule_id,
                'employee_id' => $model->id,
                'from_date' => $fields->effective_date_start,
                'to_date' => $endDate,
            ]);

            $model->schedule_id = $fields->schedule_id;
            $model->schedule_end_date = $endDate;
            $model->save();

            $schedule = Schedule::find($fields->schedule_id);

            if ($schedule && $endDate != null) {
                // Generate timesheet entry
                $dates = \Carbon\CarbonPeriod::create($fields->effective_date_start, '1 day', $endDate);
                $gateway = new GenerateTimesheetGateway;
                $gateway->addDays($model, $dates, $fields->schedule_id);
            }
        }

        return Action::message('Schedule assigned.');
    }

    /**
     * Get the fields available on the action.
     *
     * @return array
     */
    public function fields(NovaRequest $request)
    {
        return [
            Select::make('Schedule', 'schedule_id')
                ->default($this->model->schedule_id)
                ->options(
                    Schedule::pluck('name', 'id')->toArray()
                )->sortable()
                ->rules('required'),

            Date::make('Effective Date Start', 'effective_date_start')
                ->rules('required')
                ->hideFromIndex(),

            Date::make('Effective Date End', 'effective_date_end')
                // ->rules('required')
                ->hideFromIndex(),
        ];
    }
}
