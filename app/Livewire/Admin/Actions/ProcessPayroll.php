<?php

namespace App\Livewire\Admin\Actions;

use App\Jobs\ProcessPayrollJob;
use App\Models\Payroll;
use Illuminate\Bus\Queueable;
use Illuminate\Http\Request;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class ProcessPayroll extends Action
{
    use InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Perform the action on the given models.
     */
    public function handle(Request $request, array $fields, Collection $payrolls): mixed
    {
        foreach ($payrolls as $payroll) {
            $payroll->status = Payroll::STATUS_RUNNING;
            $payroll->save();

            ProcessPayrollJob::dispatchFor($payroll);
        }

        return self::message('Payroll has been processed!');
    }

    /**
     * Get the fields available on the action.
     */
    public function fields(Request $request): array
    {
        return [];
    }
}
