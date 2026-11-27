<?php

namespace App\Livewire\Admin\Resources;

use App\Livewire\Admin\Fields\Boolean;
use App\Livewire\Admin\Fields\Date;
use App\Livewire\Admin\Fields\ID;
use App\Livewire\Admin\Fields\Number;
use App\Livewire\Admin\Fields\Select;
use App\Livewire\Admin\Fields\Text;
use App\Livewire\Admin\Fields\Textarea;
use App\Livewire\Admin\Resource;
use App\Models\RetirementPlan as RetirementPlanModel;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class RetirementPlan extends Resource
{
    public static string $model = RetirementPlanModel::class;

    public static string $title = 'name';

    public static int $priority = 92;

    public static string $group = 'Retirement';

    public static array $search = ['name', 'sector', 'plan_type'];

    public function title(): string
    {
        return (string) ($this->resource?->name ?? '');
    }

    public function fields(Request $request): array
    {
        return [
            ID::make()->sortable(),
            Text::make('Name')->rules('required', 'max:128')->sortable(),
            Select::make('Sector')
                ->options(['Private' => 'Private', 'Government' => 'Government'])
                ->default('Private'),
            Select::make('Plan Type', 'plan_type')
                ->options(array_combine(RetirementPlanModel::TYPES, RetirementPlanModel::TYPES))
                ->default(RetirementPlanModel::TYPE_DEFINED_BENEFIT),
            Number::make('Eligibility Age', 'eligibility_age')->min(0)->max(100),
            Number::make('Minimum Service Years', 'minimum_service_years')->min(0),
            Textarea::make('Benefit Formula (JSON)', 'benefit_formula')
                ->help('{"type":"percentage_of_salary","rate":0.025}')
                ->resolveUsing(fn ($value) => is_array($value)
                    ? json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
                    : $value)
                ->fillUsing(function ($model, $attribute, $value): void {
                    if ($value === null || $value === '') {
                        $model->setAttribute($attribute, null);

                        return;
                    }

                    $decoded = is_string($value) ? json_decode($value, true) : $value;

                    if (! is_array($decoded)) {
                        throw ValidationException::withMessages([
                            'benefit_formula' => 'Benefit formula must be valid JSON.',
                        ]);
                    }

                    $model->setAttribute($attribute, $decoded);
                }),
            Number::make('Employer Rate', 'employer_rate')->min(0)->hideFromIndex(),
            Number::make('Employee Rate', 'employee_rate')->min(0)->hideFromIndex(),
            Date::make('Effective From', 'effective_from'),
            Date::make('Effective Until', 'effective_until'),
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
