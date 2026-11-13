<?php

namespace App\Livewire\Admin\Resources\Government\Deductions;

use App\Livewire\Admin\Fields\Currency;
use App\Livewire\Admin\Fields\ID;
use App\Livewire\Admin\Fields\Text;
use App\Livewire\Admin\Resource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class MinimumWageRate extends Resource
{
    public static string $model = \App\Models\MinimumWageRate::class;

    public static int $priority = 6;

    public static string $title = 'region';

    public static string $group = 'Government';

    public static array $search = ['region', 'industry'];

    public static function label(): string
    {
        return 'Minimum Wage Rates';
    }

    public function title(): string
    {
        return (string) ($this->resource?->monthl_basic ?? '');
    }

    public function fields(Request $request): array
    {
        return [
            ID::make()->sortable(),

            Text::make('Region', 'region')->filterable(),
            Text::make('Industry', 'industry'),
            Currency::make('Wage', 'wage_start')->currency(''),
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
