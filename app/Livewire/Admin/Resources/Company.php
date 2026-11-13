<?php

namespace App\Livewire\Admin\Resources;

use App\Livewire\Admin\Fields\ID;
use App\Livewire\Admin\Fields\Select;
use App\Livewire\Admin\Fields\Text;
use App\Livewire\Admin\Resource;
use Illuminate\Http\Request;

class Company extends Resource
{
    public static string $model = \App\Models\Company::class;

    public static int $priority = 1;

    public static string $title = 'id';

    public static string $group = 'Admin';

    public static array $search = ['id', 'name'];

    public static array $with = ['employees'];

    public static function label(): string
    {
        return 'Company Settings';
    }

    public function title(): string
    {
        return (string) ($this->resource?->name ?? '');
    }

    public function fields(Request $request): array
    {
        return [
            ID::make()->sortable(),

            Text::make('Name')
                ->rules('required')
                ->sortable(),

            Text::make('Employees', function () {
                return $this->employees->count();
            })->sortable(),

            Select::make('Type')
                ->options([
                    'Private' => 'Private',
                    'Government' => 'Government',
                    'Non-Profit' => 'Non-Profit',
                ])
                ->sortable()
                ->rules('required'),

            Text::make('TIN', 'tin')
                ->placeholder('000-000-000-000')
                ->rules('required'),

            Text::make('RDO', 'rdo')
                ->placeholder('049')
                ->rules('required'),

            Text::make('SSS', 'sss')
                ->placeholder('00-0000000-0')
                ->rules('required'),

            Text::make('HDMF', 'hdmf')
                ->placeholder('0000-0000-0000')
                ->rules('required'),

            Text::make('PhilHealth', 'phil_health')
                ->placeholder('00-000000000-0')
                ->rules('required'),
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

    public static function authorizedToCreate(): ?bool
    {
        return false;
    }
}
