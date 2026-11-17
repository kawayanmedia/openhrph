<?php

namespace App\Livewire\Admin\Actions;

use App\Jobs\ApproveLeaveJob;
use Illuminate\Bus\Queueable;
use Illuminate\Http\Request;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

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
            ApproveLeaveJob::dispatch($leaveRequest, $leaveRequest->employee);
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
}
