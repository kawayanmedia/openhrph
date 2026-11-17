<?php

namespace App\Livewire\Admin\Actions;

use App\Livewire\Admin\Fields\File;
use App\Models\Timesheet;
use Illuminate\Bus\Queueable;
use Illuminate\Http\Request;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class ImportTimeSheet extends Action
{
    use InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Perform the action on the given models.
     */
    public function handle(Request $request, array $fields, Collection $models): mixed
    {
        foreach ($models as $model) {
        }

        $path = $fields['timesheets']->store('logs');

        $timesheet = new Timesheet();
        $timesheet->processImport($path);

        return self::message('Timesheet has been Imported!');
    }

    /**
     * Get the fields available on the action.
     */
    public function fields(Request $request): array
    {
        return [
            File::make('Timesheets', 'timesheets')->rules('required', 'mimes:txt,log,xls')
                ->help(
                    "<a target='_blank' href='" . request()->root() . "/files/logs.txt'>Get Sample File</a>"
                ),
        ];
    }
}
