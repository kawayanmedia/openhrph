<?php

namespace App\Livewire\Admin\Actions;

use App\Livewire\Admin\Fields\Text;
use Illuminate\Bus\Queueable;
use Illuminate\Http\Request;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class RejectLeave extends Action
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public string $name = 'Reject Leave';

    public string $confirmText = 'Are you sure you want to reject this leave?';

    public string $confirmButtonText = 'Reject Leave';

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
            $leaveRequest->status = STATUS_REJECTED;
            $leaveRequest->reject_reason = $fields['reject_reason'];
            $leaveRequest->rejected_at = now();
            $leaveRequest->approved_at = null;

            // Process the timesheet entry
            $leaveRequest->save();
        }

        return self::message('Leave has been rejected!');
    }

    /**
     * Get the fields available on the action.
     */
    public function fields(Request $request): array
    {
        return [
            Text::make('Reject Reason', 'reject_reason')
                ->rules('required'),
        ];
    }
}
