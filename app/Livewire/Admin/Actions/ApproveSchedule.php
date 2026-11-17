<?php

namespace App\Livewire\Admin\Actions;

use App\Jobs\ApproveScheduleJob;
use App\Livewire\Admin\Fields\Text;
use Illuminate\Bus\Queueable;
use Illuminate\Http\Request;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class ApproveSchedule extends Action
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public string $name = 'Approve';

    public string $confirmText = 'Are you sure you want to approve this schedule(s)?';

    public string $confirmButtonText = 'Approve Schedule';

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
            ApproveScheduleJob::dispatch($model, $model->employee, (object) $fields);

            $model->status = STATUS_APPROVED;
            $model->save();
        }

        return self::message('Schedule has been approved!');
    }

    /**
     * Get the fields available on the action.
     */
    public function fields(Request $request): array
    {
        return [
            Text::make('Remarks', 'remarks')
                ->rules('required'),
        ];
    }
}
