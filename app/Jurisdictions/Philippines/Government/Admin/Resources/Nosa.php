<?php

namespace App\Jurisdictions\Philippines\Government\Admin\Resources;

use App\Livewire\Admin\Fields\BelongsTo;
use App\Livewire\Admin\Fields\Date;
use App\Livewire\Admin\Fields\ID;
use App\Livewire\Admin\Fields\Number;
use App\Livewire\Admin\Fields\Text;
use App\Livewire\Admin\Resource;
use App\Models\Employee as EmployeeModel;
use App\Models\Office;
use Illuminate\Http\Request;
use KawayanMedia\Government\Models\Nosa as NosaModel;
use KawayanMedia\Government\Models\PlantillaItem as PlantillaItemModel;

/**
 * Notice of Salary Adjustment: the salary grade/step an employee moves to.
 */
class Nosa extends Resource
{
    public static string $model = NosaModel::class;

    public static string $title = 'item_number';

    public static int $priority = 22;

    public static string $group = 'Government';

    public static ?string $sector = 'Government';

    public static ?string $jurisdiction = 'PH';

    public static array $search = ['item_number'];

    public function fields(Request $request): array
    {
        return [
            ID::make()->sortable(),

            BelongsTo::make('Employee', 'employee')
                ->options(EmployeeModel::employeeList())
                ->searchable()
                ->rules('required'),

            BelongsTo::make('Office')
                ->withoutTrashed()
                ->options(Office::orderBy('name')->pluck('name', 'id')->toArray()),

            BelongsTo::make('Plantilla Item', 'plantillaItem')
                ->options(PlantillaItemModel::query()->orderBy('item_number')->pluck('item_number', 'id')->toArray()),

            Text::make('Item Number', 'item_number')->rules('nullable', 'max:32'),

            Number::make('From SG', 'from_salary_grade')->rules('nullable', 'integer', 'between:1,33'),
            Number::make('From Step', 'from_step')->rules('nullable', 'integer', 'between:1,8'),
            Text::make('From SG Year', 'from_salary_grade_year')->rules('nullable', 'max:4'),
            Number::make('From Monthly Salary', 'from_monthly_salary')->rules('nullable', 'numeric'),

            Number::make('To SG', 'to_salary_grade')->rules('nullable', 'integer', 'between:1,33'),
            Number::make('To Step', 'to_step')->rules('nullable', 'integer', 'between:1,8'),
            Text::make('To SG Year', 'to_salary_grade_year')->rules('nullable', 'max:4'),
            Number::make('To Monthly Salary', 'to_monthly_salary')->rules('nullable', 'numeric'),

            Date::make('Effective date', 'effective_date')->rules('required'),
        ];
    }

    public function title(): string
    {
        return 'NOSA '.$this->resource?->item_number;
    }
}
