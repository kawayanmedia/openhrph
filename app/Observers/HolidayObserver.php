<?php

namespace App\Observers;

use App\Models\Holiday;
use App\Models\Timesheet;

class HolidayObserver
{
    /**
     * Handle the Holiday "created" event.
     *
     * @return void
     */
    public function created(Holiday $holiday)
    {
        $this->updateTimesheet($holiday);
    }

    /**
     * Handle the Holiday "updated" event.
     *
     * @return void
     */
    public function updated(Holiday $holiday)
    {
        $this->updateTimesheet($holiday);
    }

    /**
     * Handle the Holiday "deleted" event.
     *
     * @return void
     */
    public function deleted(Holiday $holiday)
    {
        $this->updateTimesheet($holiday, true);
    }

    /**
     * Handle the Holiday "restored" event.
     *
     * @return void
     */
    public function restored(Holiday $holiday)
    {
        //
    }

    /**
     * Handle the Holiday "force deleted" event.
     *
     * @return void
     */
    public function forceDeleted(Holiday $holiday)
    {
        //
    }

    public function updateTimesheet(Holiday $holiday, $isDeleted = false)
    {
        if ($isDeleted) {
            // Update all timesheet date
            Timesheet::where('log_date', $holiday->date)->update([
                'holiday_type' => null,
                'holiday_id' => null,
                'holiday_name' => null,
                'holiday_code' => null,
            ]);

            return;
        }

        $holiday->year = substr($holiday->date, 0, 4);
        $holiday->saveQuietly();

        // Update all timesheet date
        Timesheet::where('log_date', $holiday->date)->update([
            'holiday_type' => $holiday->holiday_type,
            'holiday_id' => $holiday->id,
            'holiday_name' => $holiday->name,
            'holiday_code' => $holiday->code,
        ]);
    }
}
