<?php

namespace App\Nova\Actions;

use App\Imports\EmployeesImport;
use Brightspot\Nova\Tools\DetachedActions\DetachedAction;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\File;
use Laravel\Nova\Http\Requests\NovaRequest;
use Maatwebsite\Excel\Facades\Excel;

class ImportEmployees extends Action
{
    use InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Determine if the given resource is authorizable.
     *
     * @return bool
     */
    public static function authorizable()
    {
        return true;
    }

    /**
     * Get the displayable label of the button.
     *
     * @return string
     */
    public function label()
    {
        return __('Import Emloyees');
        // search "flex w-full justify-end items-center" and remove
        // in ./develop/vendor/gobrightspot/nova-detached-actions
    }

    /**
     * Perform the action on the given models.
     *
     * @return mixed
     */
    public function handle(ActionFields $fields, Collection $models)
    {
        foreach ($models as $model) {
        }

        // $path = $fields->employees->store('/', 'public');

        $extension = $fields->employees->extension();

        // Need to check the extension then select what reader to use
        if ($extension == 'xlsx') {
            Excel::import(new EmployeesImport, $fields->employees, 'local', \Maatwebsite\Excel\Excel::XLSX);
        }

        if ($extension == 'xls') {
            Excel::import(new EmployeesImport, $fields->employees, 'local', \Maatwebsite\Excel\Excel::XLS);
        }

        if ($extension == 'csv') {
            Excel::import(new EmployeesImport, $fields->employees, 'local', \Maatwebsite\Excel\Excel::CSV);
        }

        // (new EmployeesImport)->import(storage_path() . '/public/' . $path, 'local', \Maatwebsite\Excel\Excel::XLSX);        

        return Action::message('Employess has been Imported!');
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
            File::make('Employees', 'employees')->rules('required', 'file')
                ->acceptedTypes('.xls,.xlsx,.csv')
                ->help(
                    "<a target='_blank' href='" . request()->root() . "/files/EmployeesSample.xlsx'>Get Sample File</a>"
                ),
        ];
    }
}
