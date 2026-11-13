<?php

namespace App\Livewire\Admin\Resources\Government\Deductions;

use App\Livewire\Admin\Fields\Currency;
use App\Livewire\Admin\Fields\ID;
use App\Livewire\Admin\Fields\Number;
use App\Livewire\Admin\Fields\Text;
use App\Livewire\Admin\Resource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class PhilHealth extends Resource
{
    public static string $model = \App\Models\DeductionPhilHealth::class;

    public static int $priority = 6;

    public static string $title = 'id';

    public static string $group = 'Government';

    public static array $search = ['id'];

    public static function uriKey(): string
    {
        return 'philhealth';
    }

    public static function label(): string
    {
        return 'PhilHealth Contribution';
    }

    public function title(): string
    {
        return (string) ($this->resource?->monthl_basic ?? '');
    }

    public function fields(Request $request): array
    {
        return [
            ID::make()->sortable(),

            Text::make('Name', 'monthly_basic')
                ->sortable(),

            Currency::make('Salary Start', 'basic_salary_start')
                ->sortable(),

            Currency::make('Salary End', 'basic_salary_end')
                ->sortable(),

            Number::make('Premium Rate', 'premium_rate')
                ->rules('required')
                ->sortable(),

            Currency::make('Employee Share', 'employee_share')
                ->rules('required')
                ->sortable(),

            Currency::make('Employer Share', 'employer_share')
                ->rules('required')
                ->sortable(),
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

    public static function afterCreate(Request $request, Model $model): void
    {
        $model->company_id = $request->user()->company_id;
        $model->save();
    }
}
