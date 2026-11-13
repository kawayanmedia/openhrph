<?php

namespace App\Nova\Actions;

use App\Models\Schedule;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Http\Requests\NovaRequest;

class RejectAttendance extends Action
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

    public $name = 'Reject';

    public $confirmText = 'Are you sure you want to reject this request(s)?';

    public $confirmButtonText = 'Reject Attendance';

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
            $model->status = STATUS_REJECTED;
            $model->save();
        }

        return Action::message('Attendance has been rejected!');
    }

    /**
     * Get the fields available on the action.
     *
     * @return array
     */
    public function fields(NovaRequest $request)
    {
        return [
            // Select::make('Schedule', 'schedule_id')
            //     ->default($this->model->schedule_id)
            //     ->options(
            //         Schedule::pluck('name', 'id')->toArray()
            //     )->sortable()
            //     ->rules('required'),
        ];
    }
}
