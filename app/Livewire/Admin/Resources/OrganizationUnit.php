<?php

namespace App\Livewire\Admin\Resources;

use App\Livewire\Admin\Fields\BelongsTo;
use App\Livewire\Admin\Fields\Boolean;
use App\Livewire\Admin\Fields\Date;
use App\Livewire\Admin\Fields\ID;
use App\Livewire\Admin\Fields\Select;
use App\Livewire\Admin\Fields\Text;
use App\Livewire\Admin\Resource;
use App\Models\CostCenter;
use App\Models\Employee;
use App\Models\Location;
use App\Models\OrganizationUnit as OrganizationUnitModel;
use Illuminate\Http\Request;

class OrganizationUnit extends Resource
{
    public static string $model = OrganizationUnitModel::class;

    public static int $priority = 30;

    public static string $group = 'HR';

    public static array $with = ['parent', 'location', 'costCenter', 'manager'];

    public static array $search = ['id', 'code', 'name'];

    public function title(): string
    {
        return (string) ($this->resource?->name ?? '');
    }

    public function fields(Request $request): array
    {
        return [
            ID::make()->sortable(),

            Text::make('Name')
                ->rules('required', 'max:128')
                ->sortable(),

            Text::make('Code')
                ->rules('nullable', 'max:32')
                ->sortable(),

            Select::make('Unit Type', 'unit_type')
                ->options(array_combine(OrganizationUnitModel::TYPES, OrganizationUnitModel::TYPES))
                ->rules('required')
                ->default(OrganizationUnitModel::TYPE_UNIT)
                ->sortable(),

            BelongsTo::make('Parent', 'parent')
                ->options(OrganizationUnitModel::query()->orderBy('name')->pluck('name', 'id')->toArray())
                ->nullable()
                ->onlyOnForms(),

            Text::make('Parent', fn () => $this->parent?->name ?? '—')
                ->onlyOnIndex()
                ->sortable(),

            BelongsTo::make('Location', 'location')
                ->options(Location::query()->orderBy('name')->pluck('name', 'id')->toArray())
                ->nullable()
                ->onlyOnForms(),

            BelongsTo::make('Cost Center', 'costCenter')
                ->options(CostCenter::query()->orderBy('name')->pluck('name', 'id')->toArray())
                ->nullable()
                ->onlyOnForms(),

            BelongsTo::make('Manager', 'manager')
                ->options(Employee::employeeList())
                ->nullable()
                ->onlyOnForms(),

            Date::make('Effective From', 'effective_from')
                ->hideFromIndex(),

            Date::make('Effective Until', 'effective_until')
                ->hideFromIndex(),

            Boolean::make('Active', 'is_active')->default(true),

            Text::make('Path')
                ->readonly()
                ->hideFromIndex(),
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
