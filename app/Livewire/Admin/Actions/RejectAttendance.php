<?php

namespace App\Livewire\Admin\Actions;

use Illuminate\Bus\Queueable;
use Illuminate\Http\Request;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class RejectAttendance extends Action
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public string $name = 'Reject';

    public string $confirmText = 'Are you sure you want to reject this request(s)?';

    public string $confirmButtonText = 'Reject Attendance';

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
            $model->status = STATUS_REJECTED;
            $model->save();
        }

        return self::message('Attendance has been rejected!');
    }

    /**
     * Get the fields available on the action.
     */
    public function fields(Request $request): array
    {
        return [];
    }
}
