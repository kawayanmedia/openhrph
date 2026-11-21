<?php

namespace App\Observers;

use App\Models\TimesheetRequest;
use Throwable;

class TimesheetRequestObserver
{
    /**
     * Handle the TimesheetRequest "created" event.
     *
     * @return void
     */
    public function created(TimesheetRequest $timesheetRequest)
    {
        try {
            // Get the immediate head of the employee
            $user = $timesheetRequest->employee?->immediateHead?->user;

            if (! $user) {
                return;
            }

            $url = route('admin.resource.detail', [
                'resource' => 'attendance-request',
                'resourceId' => $timesheetRequest->getKey(),
            ]);

            notify($user, 'New Attendance Application received.', $url);
        } catch (Throwable $exception) {
            // A notification must never block submitting an attendance request.
            report($exception);
        }
    }

    /**
     * Handle the TimesheetRequest "updated" event.
     *
     * @return void
     */
    public function updated(TimesheetRequest $timesheetRequest) {}

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
