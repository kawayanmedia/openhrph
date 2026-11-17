<?php

namespace App\Nova\Actions;

use App\Gateways\GenerateTimesheetGateway;
use App\Models\Employee;
use App\Models\Office;
// use Brightspot\Nova\Tools\DetachedActions\DetachedAction;
use Carbon\CarbonPeriod;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Date;
use Laravel\Nova\Fields\MultiSelect;
use Laravel\Nova\Http\Requests\NovaRequest;

class GenerateTimeSheet implements ShouldQueue
{
    use InteractsWithQueue, Queueable;

    /**
     * Perform the action on the given models.
     *
     * @return mixed
     */
    public function handle(ActionFields $fields, Collection $models)
    {
        $employees = Employee::query()
            ->byOfficeIds($fields->office_ids)
            ->with('payrollGroup')
            ->get();

        $days = CarbonPeriod::create($fields->start_date, '1 day', $fields->end_date);

        $gateway = new GenerateTimesheetGateway;

        foreach ($employees as $employee) {
            $gateway->addDays($employee, $days);
        }

        return Action::message('Timesheet has been generated.');
    }

    /**
     * Get the fields available on the action.
     *
     * @return array
     */
    public function fields(NovaRequest $request)
    {
        return [
            Date::make('Start Date')->rules('required'),
            Date::make('End Date')->rules('required'),

            MultiSelect::make('Office', 'office_ids')
                ->options(Office::officesList())
                ->rules('required')
                ->onlyOnForms(),
        ];
    }
}
