<?php

namespace App\Observers;

use App\Models\Schedule;

class ScheduleObserver
{
    /**
     * Handle the schedule "created" event.
     *
     * @return void
     */
    public function created(Schedule $schedule)
    {
        // $schedule->times = request('pm_in');
        // $schedule->save();
    }

    /**
     * Handle the schedule "updated" event.
     *
     * @return void
     */
    public function updated(Schedule $schedule)
    {
        //
    }

    /**
     * Handle the schedule "deleted" event.
     *
     * @return void
     */
    public function deleted(Schedule $schedule)
    {
        //
    }

    /**
     * Handle the schedule "restored" event.
     *
     * @return void
     */
    public function restored(Schedule $schedule)
    {
        //
    }

    /**
     * Handle the schedule "force deleted" event.
     *
     * @return void
     */
    public function forceDeleted(Schedule $schedule)
    {
        //
    }
}
