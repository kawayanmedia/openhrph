<?php

namespace App\Livewire\Admin\Actions;

use App\Livewire\Admin\Fields\File;
use App\Services\Attendance\TimesheetImport;
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
        $path = $fields['timesheets']->store('logs', 'local');

        if (! is_string($path) || $path === '') {
            return self::message('The file could not be stored. Please try again.');
        }

        $summary = app(TimesheetImport::class)->import($path);

        return self::message(sprintf(
            'Timesheet imported: %d punches accepted, %d duplicates, %d unmatched, %d blocked, %d invalid across %d days.',
            $summary['accepted'],
            $summary['duplicates'],
            $summary['unmatched'],
            $summary['blocked'],
            $summary['invalid'],
            $summary['timesheets'],
        ));
    }

    /**
     * Get the fields available on the action.
     */
    public function fields(Request $request): array
    {
        return [
            File::make('Timesheets', 'timesheets')->rules('required', 'mimes:txt,log,xls')
                ->help(
                    "<a target='_blank' href='".request()->root()."/files/logs.txt'>Get Sample File</a>"
                ),
        ];
    }
}
