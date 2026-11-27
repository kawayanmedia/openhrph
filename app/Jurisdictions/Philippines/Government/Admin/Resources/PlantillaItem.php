<?php

namespace App\Jurisdictions\Philippines\Government\Admin\Resources;

use App\Jurisdictions\Philippines\Government\Models\PlantillaItem as PlantillaItemModel;
use App\Jurisdictions\Philippines\Government\Models\PlantillaVersion as PlantillaVersionModel;
use App\Livewire\Admin\Fields\BelongsTo;
use App\Livewire\Admin\Fields\Boolean;
use App\Livewire\Admin\Fields\Date;
use App\Livewire\Admin\Fields\ID;
use App\Livewire\Admin\Fields\Number;
use App\Livewire\Admin\Fields\Select;
use App\Livewire\Admin\Fields\Text;
use App\Livewire\Admin\Resource;
use App\Models\Office;
use App\Models\OrganizationUnit;
use App\Models\Position;
use Illuminate\Http\Request;

/**
 * DBM plantilla item: the funded position an employee is appointed to.
 */
class PlantillaItem extends Resource
{
    public static string $model = PlantillaItemModel::class;

    public static string $title = 'item_number';

    public static int $priority = 20;

    public static string $group = 'Government';

    public static ?string $sector = 'Government';

    public static ?string $jurisdiction = 'PH';

    public static array $search = ['item_number', 'position_title'];

    public function fields(Request $request): array
    {
        return [
            ID::make()->sortable(),

            Text::make('Item Number', 'item_number')
                ->sortable()
                ->rules('required', 'max:32'),

            Text::make('Position Title', 'position_title')
                ->sortable()
                ->rules('required', 'max:64'),

            BelongsTo::make('Office')
                ->withoutTrashed()
                ->rules('required')
                ->options(Office::orderBy('name')->pluck('name', 'id')->toArray()),

            Number::make('Salary Grade', 'salary_grade')
                ->rules('required', 'integer', 'between:1,33'),

            Number::make('Step', 'step')
                ->rules('required', 'integer', 'between:1,8'),

            Boolean::make('Funded', 'have_budget')
                ->default(true),

            BelongsTo::make('Plantilla Version', 'version')
                ->options(PlantillaVersionModel::query()->orderByDesc('id')->pluck('version', 'id')->toArray())
                ->nullable()
                ->hideFromIndex(),

            BelongsTo::make('Position')
                ->options(Position::query()->orderBy('name')->pluck('name', 'id')->toArray())
                ->nullable()
                ->hideFromIndex(),

            BelongsTo::make('Organization Unit', 'organizationUnit')
                ->options(OrganizationUnit::query()->orderBy('name')->pluck('name', 'id')->toArray())
                ->nullable()
                ->hideFromIndex(),

            Text::make('Funding Source', 'funding_source')
                ->rules('nullable', 'max:64')
                ->hideFromIndex(),

            Select::make('Status')
                ->options(array_combine(PlantillaItemModel::STATUSES, PlantillaItemModel::STATUSES))
                ->default(PlantillaItemModel::STATUS_AUTHORIZED)
                ->sortable(),

            Date::make('Effective From', 'effective_from')->hideFromIndex(),
            Date::make('Effective Until', 'effective_until')->hideFromIndex(),
        ];
    }

    public function title(): string
    {
        return (string) ($this->resource?->item_number ?? '');
    }
}
