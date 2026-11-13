<?php

namespace App\Jobs;

use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\Timesheet;
use Carbon\CarbonPeriod;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ApproveLeaveJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $employee;

    public $leaveRequest;

    /**
     * Create a new job instance.
     */
    public function __construct(LeaveRequest $leaveRequest, $employee)
    {
        $this->leaveRequest = $leaveRequest;

        $this->employee = $employee;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->leaveRequest->approved_user_id = $this->employee?->id;
        $this->leaveRequest->status = STATUS_APPROVED;
        $this->leaveRequest->approved_at = now();

        // TODO process the leave so that we can deduct leave credits
        // Get the dates of leave
        $dates = CarbonPeriod::create($this->leaveRequest->date_from, $this->leaveRequest->date_to);

        foreach ($dates as $date) {
            $timesheet = Timesheet::firstOrNew([
                'log_date' => $date,
                'employee_id' => $this->leaveRequest->employee_id,
                'employee_number' => $this->leaveRequest->employee->employee_number,
            ]);

            $timesheet->is_leave = true;
            $timesheet->leave_type_id = $this->leaveRequest->leave_type_id;
            $timesheet->save();
        }

        // Process the timesheet entry
        $this->leaveRequest->save();

        // Update also the employee record
        $employee = Employee::find($this->leaveRequest->employee_id);

        if ($employee) {
            if ($this->leaveRequest->leave_type_id == 1) {
                $employee->total_vacation_leave -= $this->leaveRequest->days_with_pay;
            }

            if ($this->leaveRequest->leave_type_id == 2) {
                $employee->total_sick_leave -= $this->leaveRequest->days_with_pay;
            }

            $employee->save();

        }
    }
}
