<?php

namespace App\Nova\Actions;

use App\Jobs\ApproveUndertimeJob;
use App\Models\Schedule;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;

class ApproveUndertime extends Action
{
    use InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Determine if the given resource is authorizable.
     *
     * @return bool
     */
    // public static function authorizable()
    // {
    //     return true;
    // }

    public $name = 'Approve';

    public $confirmText = 'Are you sure you want to approve this undertime(s)?';

    public $confirmButtonText = 'Approve Undertime';

    public $model;

    public function __construct($model = null)
    {
        $this->model = $model;
    }

    /**
     * Perform the action on the given models.
     *
     * @return mixed
     */
    public function handle(ActionFields $fields, Collection $models)
    {
        foreach ($models as $model) {
            $model->status = STATUS_APPROVED;
            $model->save();

            ApproveUndertimeJob::dispatch($model, $model->employee, $fields);
        }

        return Action::message('Overtime has been approved!');
    }

    /**
     * Get the fields available on the action.
     *
     * @return array
     */
    public function fields(NovaRequest $request)
    {
        return [
            Text::make('Remarks'),
            // Select::make('Schedule', 'schedule_id')
            //     ->default($this->model->schedule_id)
            //     ->options(
            //         Schedule::pluck('name', 'id')->toArray()
            //     )->sortable()
            //     ->rules('required'),
        ];
    }
}
