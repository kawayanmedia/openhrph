<?php

namespace App\Observers;

use App\Models\LeaveRequest;
use Laravel\Nova\URL;

class LeaveRequestObserver
{
    /**
     * Handle the LeaveRequest "created" event.
     *
     * @return void
     */
    public function created(LeaveRequest $leaveRequest)
    {
        // When leave request is created then create a notification for
        // Make sure that notification is allowed
        // Check the approver use first
        // $user = $leaveRequest->employee?->department?->leaveApprovalEmployee?->user;

        // Get the immediate head of the employee
        $user = $leaveRequest->employee?->immediateHead?->user;

        if ($user) {
            notify($user, 'New Leave Application received.', URL::remote(request()->root().config('nova.path').'/resources/leave-requests/'.$leaveRequest->id));
        }
    }

    /**
     * Handle the LeaveRequest "updated" event.
     *
     * @return void
     */
    public function updated(LeaveRequest $leaveRequest)
    {

    }

    /**
     * Handle the LeaveRequest "deleted" event.
     *
     * @return void
     */
    public function deleted(LeaveRequest $leaveRequest)
    {
        //
    }

    /**
     * Handle the LeaveRequest "restored" event.
     *
     * @return void
     */
    public function restored(LeaveRequest $leaveRequest)
    {
        //
    }

    /**
     * Handle the LeaveRequest "force deleted" event.
     *
     * @return void
     */
    public function forceDeleted(LeaveRequest $leaveRequest)
    {
        //
    }
}
