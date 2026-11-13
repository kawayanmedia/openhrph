<?php

namespace App\Livewire\Admin\Actions;

use App\Jobs\ApproveUndertimeJob;
use App\Livewire\Admin\Fields\Text;
use Illuminate\Bus\Queueable;
use Illuminate\Http\Request;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class ApproveUndertime extends Action
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public string $name = 'Approve';

    public string $confirmText = 'Are you sure you want to approve this undertime(s)?';

    public string $confirmButtonText = 'Approve Undertime';

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
            $model->status = STATUS_APPROVED;
            $model->save();

            ApproveUndertimeJob::dispatch($model, $model->employee, (object) $fields);
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
