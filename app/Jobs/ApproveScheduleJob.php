<?php

namespace App\Jobs;

use App\Models\ScheduleAdjustmentRequest;
use App\Models\Timesheet;
use Carbon\CarbonPeriod;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ApproveScheduleJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $employee;

    public $scheduleRequest;

    public $fields;

    /**
     * Create a new job instance.
     */
    public function __construct(ScheduleAdjustmentRequest $scheduleRequest, $employee, $fields)
    {
        $this->scheduleRequest = $scheduleRequest;

        $this->employee = $employee;

        $this->fields = $fields;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->scheduleRequest->approved_user_id = $this->employee->id;
        $this->scheduleRequest->approved_at = now();
        $this->scheduleRequest->remarks = $this->fields->remarks;
        $this->scheduleRequest->status = STATUS_APPROVED;

        $dates = CarbonPeriod::create($this->scheduleRequest->date_from, $this->scheduleRequest->date_to);

        foreach ($dates as $date) {
            $timesheet = Timesheet::firstOrNew([
                'employee_id' => $this->scheduleRequest->employee_id,
                'employee_number' => $this->scheduleRequest->employee->employee_number,
                'log_date' => $date,
            ]);

            if ($this->scheduleRequest->is_rd == true) {
                $timesheet->day_type = Timesheet::REST_DAY;
                $timesheet->rate_code = Timesheet::REST_DAY;
            } else {
                $timesheet->schedule_id = $this->scheduleRequest->schedule_id;
            }

            $timesheet->save();
        }

        $this->scheduleRequest->save();
    }
}
