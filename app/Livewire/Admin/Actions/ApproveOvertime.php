<?php

namespace App\Livewire\Admin\Actions;

use App\Jobs\ApproveOvertimeJob;
use App\Livewire\Admin\Fields\Text;
use App\Models\Timesheet;
use Illuminate\Bus\Queueable;
use Illuminate\Http\Request;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class ApproveOvertime extends Action
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public string $name = 'Approve';

    public string $confirmText = 'Are you sure you want to approve this overtime(s)?';

    public string $confirmButtonText = 'Approve Overtime';

    public function __construct($model = null)
    {
        parent::__construct($model);
    }

    /**
     * Perform the action on the given models.
     */
    public function handle(Request $request, array $fields, Collection $models): mixed
    {
        foreach ($models as $model) {
            if ($model->status === STATUS_APPROVED) {
                continue;
            }

            $locked = Timesheet::query()
                ->where('employee_id', $model->employee_id)
                ->whereDate('log_date', $model->date)
                ->where('is_locked', true)
                ->exists();

            if ($locked) {
                return self::message('This date is locked by an approved payroll. Reopen the payroll before approving the request.');
            }

            $model->status = STATUS_APPROVED;
            $model->approved_user_id = Auth::id();
            $model->approved_at = now();
            $model->save();

            ApproveOvertimeJob::dispatch($model, $model->employee, (object) $fields, Auth::id());
        }

        return self::message('Overtime has been approved!');
    }

    /**
     * Get the fields available on the action.
     */
    public function fields(Request $request): array
    {
        return [
            Text::make('Remarks', 'remarks'),
        ];
    }
}
