<?php

namespace App\Livewire\Admin\Actions;

use App\Jobs\ApproveLeaveJob;
use App\Models\LeaveRequest;
use App\Models\Timesheet;
use Illuminate\Bus\Queueable;
use Illuminate\Http\Request;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class ApproveLeave extends Action
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public string $name = 'Approve Leave';

    public string $confirmText = 'Are you sure you want to approve this leave?';

    public string $confirmButtonText = 'Approve Leave';

    public function __construct($model = null)
    {
        parent::__construct($model);
    }

    /**
     * Perform the action on the given models.
     */
    public function handle(Request $request, array $fields, Collection $models): mixed
    {
        foreach ($models as $leaveRequest) {
            if (! $leaveRequest instanceof LeaveRequest) {
                continue;
            }

            if ($leaveRequest->status === LeaveRequest::APPROVED) {
                continue;
            }

            if ((int) $leaveRequest->employee_id === (int) Auth::user()?->employee?->id) {
                return self::message('You cannot approve your own leave request.');
            }

            if ($this->periodIsLocked($leaveRequest)) {
                return self::message('This leave overlaps a payroll period that is already approved and locked. Reopen the payroll before approving the leave.');
            }

            ApproveLeaveJob::dispatch($leaveRequest, $leaveRequest->employee, Auth::id());
        }

        return self::message('Leave has been approved!');
    }

    /**
     * Get the fields available on the action.
     */
    public function fields(Request $request): array
    {
        return [];
    }

    private function periodIsLocked(LeaveRequest $leaveRequest): bool
    {
        return Timesheet::query()
            ->where('employee_id', $leaveRequest->employee_id)
            ->whereDate('log_date', '>=', $leaveRequest->date_from)
            ->whereDate('log_date', '<=', $leaveRequest->date_to)
            ->where('is_locked', true)
            ->exists();
    }
}
