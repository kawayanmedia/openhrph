<?php

namespace App\Nova\Actions;

use App\Models\Timesheet;
use Brightspot\Nova\Tools\DetachedActions\DetachedAction;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\File;
use Laravel\Nova\Http\Requests\NovaRequest;

class ImportTimeSheet
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
    /**
     * Perform the action on the given models.
     *
     * @return mixed
     */
    public function handle(ActionFields $fields, Collection $models)
    {
        foreach ($models as $model) {
        }

        $path = $fields->timesheets->store('logs'); // $path can be storage/app/logs/xsdsdsdsds.txt

        $timesheet = new Timesheet();
        $timesheet->processImport($path);

        return Action::message('Timesheet has been Imported!');
    }

    /**
     * Get the fields available on the action.
     *
     * @return array
     */
    public function fields(NovaRequest $request)
    {
        // https://github.com/laravel/nova-issues/issues/786#issuecomment-441609662
        return [
            File::make('Timesheets', 'timesheets')->rules('required', 'mimes:txt,log,xls')
                ->help(
                    "<a target='_blank' href='" . request()->root() . "/files/logs.txt'>Get Sample File</a>"
                ),
        ];
    }
}
