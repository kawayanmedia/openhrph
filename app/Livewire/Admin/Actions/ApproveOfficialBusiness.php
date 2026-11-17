<?php

namespace App\Livewire\Admin\Actions;

use App\Livewire\Admin\Fields\Text;
use Illuminate\Bus\Queueable;
use Illuminate\Http\Request;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class ApproveOfficialBusiness extends Action
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public string $name = 'Approve';

    public string $confirmText = 'Are you sure you want to approve this official business(s)?';

    public string $confirmButtonText = 'Approve Official Business';

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
            $model->approved_user_id = $model->employee?->id;
            $model->status = STATUS_APPROVED;
            $model->approved_at = now();
            $model->remarks = $fields['remarks'];
            $model->save();
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
