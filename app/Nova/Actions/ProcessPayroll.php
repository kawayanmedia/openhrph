<?php

namespace App\Nova\Actions;

use App\Jobs\ProcessPayrollJob;
use App\Models\Payroll;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Http\Requests\NovaRequest;

class ProcessPayroll extends Action
{
    use InteractsWithQueue, Queueable, SerializesModels;
    // use ProcessPayrollTrait;

    /**
     * Perform the action on the given models.
     *
     * @param  \Illuminate\Support\Collection  $models
     * @return mixed
     */
    public function handle(ActionFields $fields, Collection $payrolls)
    {
        foreach ($payrolls as $payroll) {

            $payroll->status = Payroll::STATUS_RUNNING;
            $payroll->save();

            ProcessPayrollJob::dispatch($payroll);
        }

        return Action::visit('/resources/pay-rolls');

        // return Action::message('Payroll has been processed!');
    }

    /**
     * Get the fields available on the action.
     *
     * @return array
     */
    public function fields(NovaRequest $request)
    {
        return [];
    }
}
