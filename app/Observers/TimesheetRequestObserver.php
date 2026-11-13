<?php

namespace App\Observers;

use App\Models\TimesheetRequest;
use Laravel\Nova\URL;

class TimesheetRequestObserver
{
    /**
     * Handle the TimesheetRequest "created" event.
     *
     * @return void
     */
    public function created(TimesheetRequest $timesheetRequest)
    {
        // When leave request is created then create a notification for
        // Make sure that notification is allowed
        // Check the approver use first
        // $user = $timesheetRequest->employee?->department?->leaveApprovalEmployee?->user;

        // Get the immediate head of the employee
        $user = $timesheetRequest->employee?->immediateHead?->user;

        if ($user) {
            notify($user, 'New Attendance Application received.', URL::remote(request()->root().config('nova.path').'/resources/attendance-requests/'.$timesheetRequest->id));
        }
    }

    /**
     * Handle the TimesheetRequest "updated" event.
     *
     * @return void
     */
    public function updated(TimesheetRequest $timesheetRequest)
    {

    }

    /**
     * Handle the TimesheetRequest "deleted" event.
     *
     * @return void
     */
    public function deleted(TimesheetRequest $timesheetRequest)
    {
        //
    }

    /**
     * Handle the TimesheetRequest "restored" event.
     *
     * @return void
     */
    public function restored(TimesheetRequest $timesheetRequest)
    {
        //
    }

    /**
     * Handle the TimesheetRequest "force deleted" event.
     *
     * @return void
     */
    public function forceDeleted(TimesheetRequest $timesheetRequest)
    {
        //
    }
}
