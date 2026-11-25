<?php

namespace App\Jurisdictions\Philippines\Government\Admin\Resources;

use App\Jurisdictions\Philippines\Government\Models\PlantillaItem as PlantillaItemModel;
use App\Livewire\Admin\Fields\BelongsTo;
use App\Livewire\Admin\Fields\Boolean;
use App\Livewire\Admin\Fields\ID;
use App\Livewire\Admin\Fields\Number;
use App\Livewire\Admin\Fields\Text;
use App\Livewire\Admin\Resource;
use App\Models\Office;
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
        ];
    }

    public function title(): string
    {
        return (string) ($this->resource?->item_number ?? '');
    }
}
