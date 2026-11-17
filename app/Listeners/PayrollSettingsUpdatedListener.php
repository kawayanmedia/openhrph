<?php

namespace App\Listeners;

use App\Events\PayrollSettingsUpdated;

class PayrollSettingsUpdatedListener
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(PayrollSettingsUpdated $event): void
    {
        // Update all employees daily rate
        // if ($event->attributes['key'] == 'days_year_no_weeked' or $event->attributes['key'] == 'days_year_with_weeked') {

        //     foreach ($event->company->employees as $employee) {
        //         if ($employee->basic_salary != null) {
        //             $employee->daily_rate = ($employee->basic_salary * 12) / 261;
        //             $employee->saveQuietly();
        //         }
        //     }
        // }
    }
}
