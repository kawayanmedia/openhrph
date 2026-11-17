<?php

namespace App\Jobs;

use App\Models\OvertimeRequest;
use App\Models\Timesheet;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ApproveOvertimeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $employee;

    public $overtimeRequest;

    public $fields;

    /**
     * Create a new job instance.
     */
    public function __construct(OvertimeRequest $overtimeRequest, $employee, $fields)
    {
        $this->overtimeRequest = $overtimeRequest;

        $this->employee = $employee;

        $this->fields = $fields;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->overtimeRequest->approved_user_id = $this->employee->id;
        $this->overtimeRequest->approved_at = now();
        $this->overtimeRequest->remarks = $this->fields->remarks;
        $this->overtimeRequest->status = STATUS_APPROVED;

        $timesheet = Timesheet::firstOrNew([
            'employee_id' => $this->overtimeRequest->employee_id,
            'employee_number' => $this->overtimeRequest->employee->employee_number,
            'log_date' => $this->overtimeRequest->date,
        ]);

        $timesheet->addTime(
            $timesheet->day_type,
            Timesheet::RATE_OVERTIME,
            $this->overtimeRequest->hours,
            $this->overtimeRequest->time_from->format('H:i:s'),
            $this->overtimeRequest->time_to->format('H:i:s')
        );

        $timesheet->save();

        $this->overtimeRequest->save();
    }
}
