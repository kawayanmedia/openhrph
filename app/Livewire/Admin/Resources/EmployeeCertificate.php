<?php

namespace App\Livewire\Admin\Resources;

use App\Livewire\Admin\Fields\BelongsTo;
use App\Livewire\Admin\Fields\Boolean;
use App\Livewire\Admin\Fields\Date;
use App\Livewire\Admin\Fields\File;
use App\Livewire\Admin\Fields\ID;
use App\Livewire\Admin\Fields\Text;
use App\Livewire\Admin\Resource;
use App\Models\Employee;
use Illuminate\Http\Request;

class EmployeeCertificate extends Resource
{
    public static string $model = \App\Models\EmployeeCertificate::class;

    public static array $with = ['employee'];

    public static int $priority = 4;

    public static string $title = 'title';

    public static string $group = 'Employee';

    public static array $search = ['id'];

    public function title(): string
    {
        return (string) ($this->resource?->title ?? '');
    }

    public function fields(Request $request): array
    {
        return [
            ID::make()->sortable(),

            Text::make('Employee ID', 'employee.employee_number')
                ->onlyOnIndex(),

            BelongsTo::make('Employee', 'employee')
                ->options(Employee::employeeList())
                ->showOnIndex()
                ->searchable(),

            Text::make('Certificate Title', 'certicate_title')
                ->rules('required'),

            Text::make('Certificate Number', 'certicate_number')
                ->sortable()
                ->rules('required'),

            Text::make('Certificate Title', 'certicate_title')
                ->sortable()
                ->rules('required'),

            Date::make('Date', 'date')->hideFromIndex(),

            File::make('Document')->help('Upload certificate document'),

            Boolean::make('Active', 'is_active')->default(true),
        ];
    }

    public function cards(Request $request): array
    {
        return [];
    }

    public function filters(Request $request): array
    {
        return [];
    }

    public function lenses(Request $request): array
    {
        return [];
    }

    public function actions(Request $request): array
    {
        return [];
    }
}
