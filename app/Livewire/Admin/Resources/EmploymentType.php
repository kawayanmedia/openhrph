<?php

namespace App\Livewire\Admin\Resources;

use App\Livewire\Admin\Fields\Boolean;
use App\Livewire\Admin\Fields\Text;
use App\Livewire\Admin\Resource;
use Illuminate\Http\Request;

class EmploymentType extends Resource
{
    public static string $model = \App\Models\EmploymentType::class;

    public static int $priority = 6;

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
            Text::make('Code')
                ->creationRules('unique:employment_types,code')
                ->updateRules('required')
                ->sortable(),

            Text::make('Name')
                ->rules('required')
                ->sortable(),

            Boolean::make('Payroll', 'is_payroll')
                ->default(true)
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
