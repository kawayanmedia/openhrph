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

class ApproveOvertimeJob implements ShouldQueue
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
            'log_date' => $this->overtimeRequest->date,
        ]);

        $timesheet->employee_number ??= $this->overtimeRequest->employee->employee_number;

        if ($timesheet->exists && (bool) $timesheet->is_locked) {
            return;
        }

        $this->overtimeRequest->save();

        // Record the approved minutes on the day instead of blindly adding to
        // the punch-derived bucket, which a later recompute would overwrite.
        $approvedHours = (float) OvertimeRequest::query()
            ->where('employee_id', $this->overtimeRequest->employee_id)
            ->whereDate('date', $this->overtimeRequest->date)
            ->where('status', STATUS_APPROVED)
            ->where('type', OvertimeRequest::TYPE_OVERTIME)
            ->sum('hours');

        $approvedMinutes = max(0, (int) round($approvedHours * 60));

        $timesheet->approved_ot_minutes = $approvedMinutes;
        $timesheet->actual_ot = max((float) $timesheet->actual_ot, $approvedHours);
        $timesheet->day_type ??= Timesheet::REGULAR_DAY;
        $timesheet->save();
    }
}
