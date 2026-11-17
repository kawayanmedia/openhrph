<?php

namespace App\Jobs;

use App\Models\Timesheet;
use App\Models\TimesheetRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ApproveAttendanceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $employee;

    public $timesheetRequest;

    public $fields;

    /**
     * Create a new job instance.
     */
    public function __construct(TimesheetRequest $timesheetRequest, $employee, $fields)
    {
        $this->timesheetRequest = $timesheetRequest;

        $this->employee = $employee;

        $this->fields = $fields;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->timesheetRequest->approved_user_id = $this->employee->id;
        $this->timesheetRequest->approved_at = now();
        $this->timesheetRequest->remarks = $this->fields->remarks;
        $this->timesheetRequest->status = STATUS_APPROVED;

        $timesheet = Timesheet::firstOrNew([
            'employee_id' => $this->timesheetRequest->employee_id,
            'log_date' => $this->timesheetRequest->log_date,
        ]);

        if ($this->timesheetRequest->time > '12:00:00') {

            // PM
            if ($this->timesheetRequest->log_type == 0) {
                $timesheet->pm_login = $this->timesheetRequest->time;
            } else {
                $timesheet->pm_logout = $this->timesheetRequest->time;
            }
        } else {
            // PM
            if ($this->timesheetRequest->log_type == 0) {
                $timesheet->am_login = $this->timesheetRequest->time;
            } else {
                $timesheet->am_logout = $this->timesheetRequest->time;
            }
        }

        $timesheet->save();

        // Process the timesheet entry
        $this->timesheetRequest->save();

        // TODO we can have observer for this to process the timesheet
        // to get the data from am_in, out, pm_login etc and add to
        // ->logs_rates
    }
}
