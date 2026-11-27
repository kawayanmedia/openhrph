<?php

namespace App\Jurisdictions\Philippines\Government\Admin\Resources;

use App\Jurisdictions\Philippines\Government\Models\ServiceRecord as ServiceRecordModel;
use App\Livewire\Admin\Fields\BelongsTo;
use App\Livewire\Admin\Fields\ID;
use App\Livewire\Admin\Fields\Text;
use App\Livewire\Admin\Fields\Textarea;
use App\Livewire\Admin\Resource;
use App\Models\Employee as EmployeeModel;
use Illuminate\Http\Request;

/**
 * CSC service record: appointments and designations held by an employee.
 */
class ServiceRecord extends Resource
{
    public static string $model = ServiceRecordModel::class;

    public static string $title = 'designation';

    public static int $priority = 23;

    public static string $group = 'Government';

    public static ?string $sector = 'Government';

    public static ?string $jurisdiction = 'PH';

    public static array $search = ['designation', 'office_entity'];

    public function fields(Request $request): array
    {
        return [
            ID::make()->sortable(),

            BelongsTo::make('Employee', 'employee')
                ->options(EmployeeModel::employeeList())
                ->searchable()
                ->rules('required'),

            Text::make('Date From', 'date_from')->rules('nullable', 'max:32'),
            Text::make('Date To', 'date_to')->rules('nullable', 'max:32'),
            Text::make('Designation', 'designation')->rules('required', 'max:128'),
            Text::make('Status', 'status')->rules('nullable', 'max:32'),
            Text::make('Salary', 'salary')->rules('nullable', 'max:32'),
            Text::make('Office', 'office_entity')->rules('nullable', 'max:128'),
            Text::make('Branch', 'branch')->rules('nullable', 'max:128'),
            Text::make('LWOP', 'lwop')->rules('nullable', 'max:32'),
            Text::make('Separation date', 'separation_date')->rules('nullable'),
            Text::make('Separation cause', 'separation_cause')->rules('nullable', 'max:128'),
            Textarea::make('Remarks', 'remarks')->rules('nullable'),
        ];
    }

    public function title(): string
    {
        return (string) ($this->resource?->designation ?? '');
    }
}
