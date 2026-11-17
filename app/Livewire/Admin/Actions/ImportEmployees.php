<?php

namespace App\Livewire\Admin\Actions;

use App\Imports\EmployeesImport;
use App\Livewire\Admin\Fields\File;
use Illuminate\Bus\Queueable;
use Illuminate\Http\Request;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Facades\Excel;

class ImportEmployees extends Action
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public string $name = 'Import Employees';

    /**
     * Perform the action on the given models.
     */
    public function handle(Request $request, array $fields, Collection $models): mixed
    {
        foreach ($models as $model) {
        }

        $extension = $fields['employees']->extension();

        // Need to check the extension then select what reader to use
        if ($extension == 'xlsx') {
            Excel::import(new EmployeesImport, $fields['employees'], 'local', \Maatwebsite\Excel\Excel::XLSX);
        }

        if ($extension == 'xls') {
            Excel::import(new EmployeesImport, $fields['employees'], 'local', \Maatwebsite\Excel\Excel::XLS);
        }

        if ($extension == 'csv') {
            Excel::import(new EmployeesImport, $fields['employees'], 'local', \Maatwebsite\Excel\Excel::CSV);
        }

        return self::message('Employess has been Imported!');
    }

    /**
     * Get the fields available on the action.
     */
    public function fields(Request $request): array
    {
        return [
            File::make('Employees', 'employees')->rules('required', 'file')
                ->help(
                    "<a target='_blank' href='".request()->root()."/files/EmployeesSample.xlsx'>Get Sample File</a>"
                ),
        ];
    }
}
