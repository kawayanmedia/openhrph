<?php

namespace App\Jurisdictions\Philippines\Government\Admin\Resources;

use App\Jurisdictions\Philippines\Government\Models\LeaveCard as LeaveCardModel;
use App\Livewire\Admin\Fields\BelongsTo;
use App\Livewire\Admin\Fields\ID;
use App\Livewire\Admin\Fields\Text;
use App\Livewire\Admin\Resource;
use App\Models\Employee as EmployeeModel;
use Illuminate\Http\Request;

/**
 * CSC leave card header for an employee.
 */
class LeaveCard extends Resource
{
    public static string $model = LeaveCardModel::class;

    public static string $title = 'name';

    public static int $priority = 24;

    public static string $group = 'Government';

    public static ?string $sector = 'Government';

    public static ?string $jurisdiction = 'PH';

    public static array $search = ['name', 'code', 'account_number'];

    public function fields(Request $request): array
    {
        return [
            ID::make()->sortable(),

            BelongsTo::make('Employee', 'employee')
                ->options(EmployeeModel::employeeList())
                ->searchable()
                ->rules('required'),

            Text::make('Name', 'name')->rules('required', 'max:128'),
            Text::make('Code', 'code')->rules('nullable', 'max:32'),
            Text::make('Account Number', 'account_number')->rules('nullable', 'max:32'),
        ];
    }

    public function title(): string
    {
        return (string) ($this->resource?->name ?? '');
    }
}
