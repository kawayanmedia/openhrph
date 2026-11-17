<?php

namespace App\Livewire\Admin\Actions;

use App\Gateways\GenerateTimesheetGateway;
use App\Livewire\Admin\Fields\Date;
use App\Livewire\Admin\Fields\Select;
use App\Models\EmployeeSchedule;
use App\Models\Schedule;
use Carbon\CarbonPeriod;
use Illuminate\Bus\Queueable;
use Illuminate\Http\Request;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class AssignEmployeeSchedule extends Action
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public function __construct($model = null)
    {
        parent::__construct($model);
    }

    /**
     * Perform the action on the given models.
     */
    public function handle(Request $request, array $fields, Collection $models): mixed
    {
        foreach ($models as $model) {
            $endDate = $fields['effective_date_end'];

            // Remove schedule first
            EmployeeSchedule::where('employee_id', $model->id)
                ->where('from_date', $fields['effective_date_start'])
                ->where('to_date', $endDate)
                ->delete();

            EmployeeSchedule::firstOrCreate([
                'schedule_id' => $fields['schedule_id'],
                'employee_id' => $model->id,
                'from_date' => $fields['effective_date_start'],
                'to_date' => $endDate,
            ]);

            $model->schedule_id = $fields['schedule_id'];
            $model->schedule_end_date = $endDate;
            $model->save();

            $schedule = Schedule::find($fields['schedule_id']);

            if ($schedule && $endDate != null) {
                // Generate timesheet entry
                $dates = CarbonPeriod::create($fields['effective_date_start'], '1 day', $endDate);
                $gateway = new GenerateTimesheetGateway;
                $gateway->addDays($model, $dates, $fields['schedule_id']);
            }
        }

        return self::message('Schedule assigned.');
    }

    /**
     * Get the fields available on the action.
     */
    public function fields(Request $request): array
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
                ->hideFromIndex(),
        ];
    }
}
