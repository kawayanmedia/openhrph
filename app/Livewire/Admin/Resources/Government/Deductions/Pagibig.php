<?php

namespace App\Livewire\Admin\Resources\Government\Deductions;

use App\Livewire\Admin\Fields\Currency;
use App\Livewire\Admin\Fields\ID;
use App\Livewire\Admin\Fields\Text;
use App\Livewire\Admin\Resource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class Pagibig extends Resource
{
    public static string $model = \App\Models\DeductionPagibig::class;

    public static int $priority = 6;

    public static string $title = 'id';

    public static string $group = 'Government';

    public static array $search = ['id'];

    public static function uriKey(): string
    {
        return 'pagibig';
    }

    public static function label(): string
    {
        return 'Pagibig / HMDF Contribution';
    }

    public function title(): string
    {
        return (string) ($this->resource?->monthl_basic ?? '');
    }

    public function fields(Request $request): array
    {
        return [
            ID::make()->sortable(),

            Currency::make('Basic Salary Start', 'basic_salary_start'),

            Currency::make('Basic Salary End', 'basic_salary_end'),

            Text::make('Contribution Rate', 'premium_rate')->hideFromIndex(),

            Text::make('Contribution Rate', function () {
                return number_format($this->premium_rate, 2).'%';
            })->onlyOnIndex(),
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
