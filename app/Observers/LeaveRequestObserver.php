<?php

namespace App\Observers;

use App\Models\LeaveRequest;

class LeaveRequestObserver
{
    /**
     * Notify the immediate head that a leave request is waiting.
     */
    public function created(LeaveRequest $leaveRequest)
    {
        $user = $leaveRequest->employee?->immediateHead?->user;

        if ($user === null) {
            return;
        }

        $url = url('/'.trim((string) config('admin.path', 'app'), '/')
            .'/resources/leave-request/'.$leaveRequest->getKey());

        notify($user, 'New Leave Application received.', $url);
    }

    public function updated(LeaveRequest $leaveRequest)
    {
        //
    }

    public function deleted(LeaveRequest $leaveRequest)
    {
        //
    }

    public function restored(LeaveRequest $leaveRequest)
    {
        //
    }

    public function forceDeleted(LeaveRequest $leaveRequest)
    {
        //
    }
}
