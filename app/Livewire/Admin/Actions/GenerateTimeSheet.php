<?php

namespace App\Livewire\Admin\Actions;

use App\Gateways\GenerateTimesheetGateway;
use App\Livewire\Admin\Fields\Date;
use App\Livewire\Admin\Fields\Select;
use App\Models\Employee;
use App\Models\Office;
use Carbon\CarbonPeriod;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Http\Request;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

class GenerateTimeSheet extends Action implements ShouldQueue
{
    use InteractsWithQueue, Queueable;

    /**
     * Perform the action on the given models.
     */
    public function handle(Request $request, array $fields, Collection $models): mixed
    {
        $employees = Employee::query()
            ->byOfficeIds(Arr::wrap($fields['office_ids'] ?? null))
            ->with('payrollGroup')
            ->get();

        $days = CarbonPeriod::create($fields['start_date'], '1 day', $fields['end_date']);

        $gateway = new GenerateTimesheetGateway;

        foreach ($employees as $employee) {
            $gateway->addDays($employee, $days);
        }

        return self::message('Timesheet has been generated.');
    }

    /**
     * Get the fields available on the action.
     */
    public function fields(Request $request): array
    {
        return [
            Date::make('Start Date', 'start_date')->rules('required'),

            Date::make('End Date', 'end_date')->rules('required'),

            Select::make('Office', 'office_ids')
                ->options(Office::officesList())
                ->rules('required')
                ->onlyOnForms(),
        ];
    }
}
