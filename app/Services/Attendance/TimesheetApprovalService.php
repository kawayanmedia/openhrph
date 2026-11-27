<?php

namespace App\Services\Attendance;

use App\Models\Timesheet;
use App\Models\TimesheetApproval;
use App\Models\User;
use App\Services\Hr\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Submit → approve/reject → payroll lock workflow for timesheets.
 */
class TimesheetApprovalService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function submit(Timesheet $timesheet, User $actor): Timesheet
    {
        return DB::transaction(function () use ($timesheet, $actor): Timesheet {
            $timesheet = Timesheet::withoutTenant()->whereKey($timesheet->getKey())->lockForUpdate()->firstOrFail();

            if ($timesheet->is_locked) {
                throw ValidationException::withMessages([
                    'timesheet' => 'A payroll-locked timesheet cannot be resubmitted.',
                ]);
            }

            $timesheet->forceFill([
                'approval_status' => 'submitted',
                'submitted_at' => now(),
            ])->save();

            $this->record($timesheet, $actor, TimesheetApproval::ACTION_SUBMIT);

            return $timesheet->refresh();
        });
    }

    public function approve(Timesheet $timesheet, User $actor, ?string $comments = null): Timesheet
    {
        return DB::transaction(function () use ($timesheet, $actor, $comments): Timesheet {
            $timesheet = Timesheet::withoutTenant()->whereKey($timesheet->getKey())->lockForUpdate()->firstOrFail();

            if ($timesheet->approval_status !== 'submitted') {
                throw ValidationException::withMessages([
                    'timesheet' => 'Only a submitted timesheet can be approved.',
                ]);
            }

            $timesheet->forceFill([
                'approval_status' => 'approved',
                'approved_by' => $actor->getKey(),
                'approved_at' => now(),
            ])->save();

            $this->record($timesheet, $actor, TimesheetApproval::ACTION_APPROVE, $comments);

            $this->audit->record('attendance.timesheet_approved', $timesheet, after: [
                'approved_by' => $actor->getKey(),
            ]);

            return $timesheet->refresh();
        });
    }

    public function reject(Timesheet $timesheet, User $actor, string $comments): Timesheet
    {
        return DB::transaction(function () use ($timesheet, $actor, $comments): Timesheet {
            $timesheet = Timesheet::withoutTenant()->whereKey($timesheet->getKey())->lockForUpdate()->firstOrFail();

            if ($timesheet->is_locked) {
                throw ValidationException::withMessages([
                    'timesheet' => 'A payroll-locked timesheet cannot be rejected.',
                ]);
            }

            $timesheet->forceFill(['approval_status' => 'rejected'])->save();

            $this->record($timesheet, $actor, TimesheetApproval::ACTION_REJECT, $comments);

            return $timesheet->refresh();
        });
    }

    public function lock(Timesheet $timesheet, User $actor, ?string $comments = null): Timesheet
    {
        return DB::transaction(function () use ($timesheet, $actor, $comments): Timesheet {
            $timesheet = Timesheet::withoutTenant()->whereKey($timesheet->getKey())->lockForUpdate()->firstOrFail();

            if ($timesheet->approval_status !== 'approved') {
                throw ValidationException::withMessages([
                    'timesheet' => 'Only an approved timesheet can be locked for payroll.',
                ]);
            }

            $timesheet->forceFill([
                'is_locked' => true,
                'locked_at' => now(),
            ])->save();

            $this->record($timesheet, $actor, TimesheetApproval::ACTION_LOCK, $comments);

            return $timesheet->refresh();
        });
    }

    protected function record(Timesheet $timesheet, User $actor, string $action, ?string $comments = null): TimesheetApproval
    {
        return TimesheetApproval::create([
            'company_id' => $timesheet->company_id,
            'timesheet_id' => $timesheet->getKey(),
            'actor_id' => $actor->getKey(),
            'action' => $action,
            'comments' => $comments,
            'decided_at' => now(),
        ]);
    }
}
