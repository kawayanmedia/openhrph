<?php

namespace App\Jobs;

use App\Models\Employee;
use App\Models\TimesheetRequest;
use App\Services\Attendance\AttendanceCorrection;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ApproveAttendanceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public TimesheetRequest $timesheetRequest,
        public ?Employee $approver = null,
        public ?string $remarks = null,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(AttendanceCorrection $service): void
    {
        $service->apply($this->timesheetRequest, $this->approver, $this->remarks);
    }
}
