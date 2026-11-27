<?php

namespace App\Livewire\Admin\Resources;

use App\Livewire\Admin\Fields\Boolean;
use App\Livewire\Admin\Fields\ID;
use App\Livewire\Admin\Fields\Text;
use App\Livewire\Admin\Resource;
use App\Models\Location as LocationModel;
use Illuminate\Http\Request;

class Location extends Resource
{
    public static string $model = LocationModel::class;

    public static int $priority = 31;

    public static string $group = 'HR';

    public static array $search = ['id', 'code', 'name', 'city'];

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

            Text::make('Address No', 'address_no')->hideFromIndex(),
            Text::make('Street')->hideFromIndex(),
            Text::make('Barangay')->hideFromIndex(),
            Text::make('City')->sortable(),
            Text::make('Province')->sortable(),
            Text::make('ZIP Code', 'zip_code')->hideFromIndex(),

            Text::make('Country')
                ->rules('nullable', 'max:2')
                ->default('PH')
                ->hideFromIndex(),

            Text::make('Timezone')
                ->rules('nullable', 'max:64')
                ->hideFromIndex(),

            Text::make('Phone')->hideFromIndex(),
            Text::make('Email')->hideFromIndex(),

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
