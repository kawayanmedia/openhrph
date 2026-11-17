<?php

namespace App\Livewire\Admin\Resources;

use App\Livewire\Admin\Fields\BelongsTo;
use App\Livewire\Admin\Fields\Boolean;
use App\Livewire\Admin\Fields\Currency;
use App\Livewire\Admin\Fields\ID;
use App\Livewire\Admin\Fields\Text;
use App\Livewire\Admin\Resource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class Position extends Resource
{
    public static string $model = \App\Models\Position::class;

    public static int $priority = 5;

    public static string $title = 'id';

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

            BelongsTo::make('Department', 'department')
                ->options(\App\Models\Department::orderBy('name')->pluck('name', 'id')->toArray())
                ->rules('required')
                ->withoutTrashed(),

            Text::make('Code')
                ->sortable(),

            Text::make('Name')
                ->rules('required')
                ->sortable(),

            Currency::make('Basic Pay'),

            Boolean::make('Vacant', 'is_vacant')->default(false),
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
