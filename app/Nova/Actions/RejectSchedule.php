<?php

namespace App\Nova\Actions;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;

class RejectSchedule extends Action
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

    public $name = 'Reject Schedule';

    public $confirmText = 'Are you sure you want to reject this schedule?';

    public $confirmButtonText = 'Reject Schedule';

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
        foreach ($models as $leaveRequest) {
            $leaveRequest->status = STATUS_REJECTED;
            $leaveRequest->reject_reason = $fields->reject_reason;
            $leaveRequest->rejected_at = now();
            $leaveRequest->approved_at = null;

            // Process the timesheet entry
            $leaveRequest->save();
        }

        return Action::message('Leave has been rejected!');
    }

    /**
     * Get the fields available on the action.
     *
     * @return array
     */
    public function fields(NovaRequest $request)
    {
        return [
            Text::make('Reject Reason', 'reject_reason')
                ->rules('required'),
            // Select::make('Schedule', 'schedule_id')
            //     ->default($this->model->schedule_id)
            //     ->options(
            //         Schedule::pluck('name', 'id')->toArray()
            //     )->sortable()
            //     ->rules('required'),
        ];
    }
}
