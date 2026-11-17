<?php

namespace App\Livewire\Admin\Actions;

use App\Livewire\Admin\Fields\Text;
use Illuminate\Bus\Queueable;
use Illuminate\Http\Request;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class RejectOfficialBusiness extends Action
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public string $name = 'Reject';

    public string $confirmText = 'Are you sure you want to reject this official business(s)?';

    public string $confirmButtonText = 'Reject Official Business';

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
            $model->rejected_at = now();
            $model->remarks = $fields['remarks'];
            $model->reject_reason = $fields['reject_reason'];
            $model->save();
        }

        return self::message('Overtime has been rejected!');
    }

    /**
     * Get the fields available on the action.
     */
    public function fields(Request $request): array
    {
        return [
            Text::make('Remarks', 'remarks'),
            Text::make('Reject Reason', 'reject_reason'),
        ];
    }
}
