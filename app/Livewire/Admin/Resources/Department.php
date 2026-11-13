<?php

namespace App\Livewire\Admin\Resources;

use App\Livewire\Admin\Fields\HasMany;
use App\Livewire\Admin\Fields\ID;
use App\Livewire\Admin\Fields\Select;
use App\Livewire\Admin\Fields\Text;
use App\Livewire\Admin\Resource;
use App\Models\Employee;
use Illuminate\Http\Request;

class Department extends Resource
{
    public static string $model = \App\Models\Department::class;

    public static string $title = 'id';

    public static int $priority = 4;

    public static string $group = 'Admin';

    public static array $search = ['id', 'name'];

    public function title(): string
    {
        return (string) ($this->resource?->name ?? '');
    }

    public function fields(Request $request): array
    {
        return [
            ID::make()->sortable(),

            Text::make('Code')->sortable(),

            Text::make('Name')->sortable()->rules('required'),

            Text::make('Address')->sortable(),

            Select::make('Attendance Approval', 'attendance_approval_employee_id')
                ->options(Employee::employeeList())
                ->sortable()
                ->searchable()
                ->displayUsingLabels()
                ->hideFromIndex(),

            Select::make('Overtime Approval', 'overtime_approval_employee_id')
                ->options(Employee::employeeList())
                ->sortable()
                ->searchable()
                ->displayUsingLabels()
                ->hideFromIndex(),

            Select::make('Leave Approval', 'leave_approval_employee_id')
                ->options(Employee::employeeList())
                ->sortable()
                ->searchable()
                ->displayUsingLabels()
                ->hideFromIndex(),

            HasMany::make('Employees', 'employees')->nullable()->readonly(),
        ];
    }
}
