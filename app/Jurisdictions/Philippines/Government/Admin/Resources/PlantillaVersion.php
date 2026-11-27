<?php

namespace App\Jurisdictions\Philippines\Government\Admin\Resources;

use App\Jurisdictions\Philippines\Government\Models\PlantillaVersion as PlantillaVersionModel;
use App\Livewire\Admin\Actions\Government\ApprovePlantillaVersion;
use App\Livewire\Admin\Fields\Badge;
use App\Livewire\Admin\Fields\BelongsTo;
use App\Livewire\Admin\Fields\Date;
use App\Livewire\Admin\Fields\ID;
use App\Livewire\Admin\Fields\Text;
use App\Livewire\Admin\Resource;
use App\Models\Document;
use Illuminate\Http\Request;

class PlantillaVersion extends Resource
{
    public static string $model = PlantillaVersionModel::class;

    public static string $title = 'version';

    public static int $priority = 19;

    public static string $group = 'Government';

    public static ?string $sector = 'Government';

    public static ?string $jurisdiction = 'PH';

    public static array $with = ['approver', 'document'];

    public static array $search = ['version', 'name', 'approval_reference'];

    public function title(): string
    {
        return (string) ($this->resource?->version ?? '');
    }

    public function fields(Request $request): array
    {
        return [
            ID::make()->sortable(),

            Text::make('Version')->rules('required', 'max:32')->sortable(),
            Text::make('Name')->rules('nullable', 'max:128')->sortable(),

            Text::make('Approval Reference', 'approval_reference')
                ->rules('nullable', 'max:128'),

            Date::make('Effective From', 'effective_from'),
            Date::make('Effective Until', 'effective_until'),

            Badge::make('Status')->map([
                PlantillaVersionModel::STATUS_DRAFT => 'warning',
                PlantillaVersionModel::STATUS_APPROVED => 'success',
                PlantillaVersionModel::STATUS_RETIRED => 'default',
            ])->sortable(),

            BelongsTo::make('Approval Document', 'document')
                ->options(Document::query()->orderBy('title')->pluck('title', 'id')->toArray())
                ->nullable()
                ->hideFromIndex(),

        ];
    }

    public function actions(Request $request): array
    {
        return [new ApprovePlantillaVersion];
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
}
