<?php

namespace App\Observers;

use App\Actions\GeneratePayrollDateAction;
use App\Models\PayrollGroup;

class PayrollGroupObserver
{
    /**
     * Handle the PayrollGroup "created" event.
     *
     * @return void
     */
    public function created(PayrollGroup $payrollGroup)
    {
        // GeneratePayrollDateAction::run($payrollGroup);
    }

    /**
     * Handle the PayrollGroup "updating" event.
     *
     * @return void
     */
    public function updating(PayrollGroup $payrollGroup)
    {
        // Check if the first pay date is dirty
        if ($payrollGroup->isDirty('first_pay_date') or $payrollGroup->isDirty('second_pay_date')) {
            GeneratePayrollDateAction::run($payrollGroup, true);
        }
    }

    /**
     * Handle the PayrollGroup "updated" event.
     *
     * @return void
     */
    public function updated(PayrollGroup $payrollGroup)
    {
    }

    /**
     * Handle the PayrollGroup "deleted" event.
     *
     * @return void
     */
    public function deleted(PayrollGroup $payrollGroup)
    {
        //
    }

    /**
     * Handle the PayrollGroup "restored" event.
     *
     * @return void
     */
    public function restored(PayrollGroup $payrollGroup)
    {
        //
    }

    /**
     * Handle the PayrollGroup "force deleted" event.
     *
     * @return void
     */
    public function forceDeleted(PayrollGroup $payrollGroup)
    {
        //
    }
}
