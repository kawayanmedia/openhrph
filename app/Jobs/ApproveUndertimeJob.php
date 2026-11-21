<?php

namespace App\Jobs;

use App\Models\OvertimeRequest;
use App\Models\Timesheet;
use App\Support\Tenancy\TenantContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Auth;

class ApproveUndertimeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $employee;

    public $overtimeRequest;

    public $fields;

    /**
     * Create a new job instance.
     */
    public function __construct(OvertimeRequest $overtimeRequest, $employee, $fields, public ?int $approverId = null)
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
        app(TenantContext::class)->run((int) $this->overtimeRequest->company_id, function (): void {
            $this->approve();
        });
    }

    private function approve(): void
    {
        $this->overtimeRequest->approved_user_id = $this->approverId ?? Auth::id();
        $this->overtimeRequest->approved_at = now();
        $this->overtimeRequest->remarks = $this->fields->remarks;
        $this->overtimeRequest->status = STATUS_APPROVED;

        $timesheet = Timesheet::firstOrNew([
            'employee_id' => $this->overtimeRequest->employee_id,
            'employee_number' => $this->overtimeRequest->employee->employee_number,
            'log_date' => $this->overtimeRequest->date,
        ]);

        if ($timesheet->exists && (bool) $timesheet->is_locked) {
            return;
        }

        // Keep the undertime hours on the day row so payroll can deduct them.
        $timesheet->ut = (float) $timesheet->ut + (float) $this->overtimeRequest->hours;
        $timesheet->day_type ??= Timesheet::REGULAR_DAY;
        $timesheet->save();

        $this->overtimeRequest->save();
    }
}
