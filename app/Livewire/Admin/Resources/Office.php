<?php

namespace App\Livewire\Admin\Resources;

use App\Livewire\Admin\Fields\HasMany;
use App\Livewire\Admin\Fields\ID;
use App\Livewire\Admin\Fields\Select;
use App\Livewire\Admin\Fields\Text;
use App\Livewire\Admin\Resource;
use Illuminate\Http\Request;

class Office extends Resource
{
    public static string $model = \App\Models\Office::class;

    public static string $title = 'id';

    public static int $priority = 2;

    public static string $group = 'Admin';

    public static array $search = ['id', 'name'];

    public static function label(): string
    {
        return 'Office';
    }

    public static function pluralLabel(): string
    {
        return 'Offices / Locations';
    }

    public function title(): string
    {
        return (string) ($this->resource?->name ?? '');
    }

    public function fields(Request $request): array
    {
        return [
            ID::make()->sortable(),

            Text::make('Code')->sortable(),

            Text::make('Name')->sortable()->rules('required'),

            Select::make('Region', 'region')
                ->options(region_options())
                ->nullable()
                ->help('Used to resolve the regional minimum wage order.'),

            Select::make('Industry', 'industry')
                ->options(industry_options())
                ->nullable()
                ->help('Used to resolve the regional minimum wage order.'),

            HasMany::make('Employees', 'employees')->nullable()->readonly(),
        ];
    }
}
