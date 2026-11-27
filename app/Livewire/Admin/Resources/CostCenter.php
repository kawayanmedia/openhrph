<?php

namespace App\Livewire\Admin\Resources;

use App\Livewire\Admin\Fields\BelongsTo;
use App\Livewire\Admin\Fields\Boolean;
use App\Livewire\Admin\Fields\ID;
use App\Livewire\Admin\Fields\Text;
use App\Livewire\Admin\Fields\Textarea;
use App\Livewire\Admin\Resource;
use App\Models\CostCenter as CostCenterModel;
use Illuminate\Http\Request;

class CostCenter extends Resource
{
    public static string $model = CostCenterModel::class;

    public static int $priority = 32;

    public static string $group = 'HR';

    public static array $with = ['parent'];

    public static array $search = ['id', 'code', 'name'];

    public function title(): string
    {
        return (string) ($this->resource?->name ?? '');
    }

    public function fields(Request $request): array
    {
        return [
            ID::make()->sortable(),

            Text::make('Name')->rules('required', 'max:128')->sortable(),
            Text::make('Code')->rules('nullable', 'max:32')->sortable(),

            BelongsTo::make('Parent', 'parent')
                ->options(CostCenterModel::query()->orderBy('name')->pluck('name', 'id')->toArray())
                ->nullable()
                ->onlyOnForms(),

            Text::make('Parent', fn () => $this->parent?->name ?? '—')->onlyOnIndex(),

            Textarea::make('Description')->hideFromIndex(),

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
