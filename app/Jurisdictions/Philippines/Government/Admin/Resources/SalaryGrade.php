<?php

namespace App\Jurisdictions\Philippines\Government\Admin\Resources;

use App\Livewire\Admin\Fields\ID;
use App\Livewire\Admin\Fields\Number;
use App\Livewire\Admin\Fields\Text;
use App\Livewire\Admin\Resource;
use Illuminate\Http\Request;
use KawayanMedia\Government\Models\SalaryGrade as SalaryGradeModel;

/**
 * The DBM salary schedule: one row per salary grade, year and schedule type.
 */
class SalaryGrade extends Resource
{
    public static string $model = SalaryGradeModel::class;

    public static string $title = 'sg';

    public static int $priority = 21;

    public static string $group = 'Government';

    public static ?string $sector = 'Government';

    public static ?string $jurisdiction = 'PH';

    public static array $search = ['sg', 'year', 'salary_grade_type'];

    public function fields(Request $request): array
    {
        return [
            ID::make()->sortable(),

            Number::make('Salary Grade', 'sg')
                ->rules('required', 'integer', 'between:1,33'),

            Text::make('Year', 'year')
                ->rules('required', 'max:4'),

            Text::make('Schedule', 'salary_grade_type')
                ->rules('required', 'max:16'),

            Number::make('Step 1', 'step1')->rules('nullable', 'numeric'),
            Number::make('Step 2', 'step2')->rules('nullable', 'numeric'),
            Number::make('Step 3', 'step3')->rules('nullable', 'numeric'),
            Number::make('Step 4', 'step4')->rules('nullable', 'numeric'),
            Number::make('Step 5', 'step5')->rules('nullable', 'numeric'),
            Number::make('Step 6', 'step6')->rules('nullable', 'numeric'),
            Number::make('Step 7', 'step7')->rules('nullable', 'numeric'),
            Number::make('Step 8', 'step8')->rules('nullable', 'numeric'),
        ];
    }

    public function title(): string
    {
        return 'SG '.$this->resource?->sg.' · '.$this->resource?->year;
    }
}
