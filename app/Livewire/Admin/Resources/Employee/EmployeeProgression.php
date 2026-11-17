<?php

namespace App\Livewire\Admin\Resources\Employee;

use App\Livewire\Admin\Fields\ID;
use App\Livewire\Admin\Fields\Text;
use App\Livewire\Admin\Resource;
use Illuminate\Http\Request;

class EmployeeProgression extends Resource
{
    public static string $model = \App\Models\Employee::class;

    public static int $priority = 6;

    public static string $title = 'id';

    public static string $group = 'Admin';

    public static array $search = ['id'];

    public function title(): string
    {
        return (string) ($this->resource?->name ?? '');
    }

    public function fields(Request $request): array
    {
        return [
            ID::make()->sortable(),

            Text::make('Code')
                ->sortable(),

            Text::make('Name')
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
}
