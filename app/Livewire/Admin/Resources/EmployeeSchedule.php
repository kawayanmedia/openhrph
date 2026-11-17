<?php

namespace App\Livewire\Admin\Resources;

use App\Livewire\Admin\Fields\BelongsTo;
use App\Livewire\Admin\Fields\Date;
use App\Livewire\Admin\Fields\ID;
use App\Livewire\Admin\Fields\Text;
use App\Livewire\Admin\Resource;
use App\Models\Employee;
use Illuminate\Http\Request;

class EmployeeSchedule extends Resource
{
    public static string $model = \App\Models\EmployeeSchedule::class;

    public static string $title = 'id';

    public static array $with = ['employee', 'employee.office'];

    public static string $group = 'Attendance';

    public static array $search = [];

    public static function label(): string
    {
        return 'Employee Schedule';
    }

    public function fields(Request $request): array
    {
        return [
            ID::make()->sortable(),

            Text::make('Employee', function () {
                return $this->employee?->fullname;
            })->onlyOnIndex(),

            Text::make('Office', function () {
                if ($this->employee) {
                    return $this->employee->office?->name;
                }
            })->onlyOnIndex(),

            BelongsTo::make('Employee', 'employee')
                ->options(Employee::employeeList())
                ->creationRules('required', 'unique:employees_schedules,employee_id')
                ->updateRules('required', 'unique:employees_schedules,employee_id,{{resourceId}}')
                ->searchable()
                ->withoutTrashed()
                ->hideFromIndex(),

            Date::make('From Date', 'from_date')
                ->help('Leave Blank if regular working hours'),

            Date::make('To Date', 'to_date')
                ->nullable()
                ->help('Leave Blank if regular working hours'),
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
