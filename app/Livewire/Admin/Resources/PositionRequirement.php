<?php

namespace App\Livewire\Admin\Resources;

use App\Livewire\Admin\Fields\BelongsTo;
use App\Livewire\Admin\Fields\Boolean;
use App\Livewire\Admin\Fields\ID;
use App\Livewire\Admin\Fields\Select;
use App\Livewire\Admin\Fields\Text;
use App\Livewire\Admin\Resource;
use App\Models\Position;
use App\Models\PositionRequirement as PositionRequirementModel;
use Illuminate\Http\Request;

class PositionRequirement extends Resource
{
    public static string $model = PositionRequirementModel::class;

    public static int $priority = 35;

    public static string $group = 'HR';

    public static array $with = ['position'];

    public static array $search = ['id', 'description', 'reference'];

    public function title(): string
    {
        return (string) ($this->resource?->description ?? '');
    }

    public function fields(Request $request): array
    {
        return [
            ID::make()->sortable(),

            BelongsTo::make('Position')
                ->options(Position::query()->orderBy('name')->pluck('name', 'id')->toArray())
                ->rules('required')
                ->sortable(),

            Select::make('Requirement Type', 'requirement_type')
                ->options(array_combine(PositionRequirementModel::TYPES, PositionRequirementModel::TYPES))
                ->rules('required')
                ->default(PositionRequirementModel::TYPE_OTHER)
                ->sortable(),

            Text::make('Description')->rules('required', 'max:255')->sortable(),
            Text::make('Reference')->rules('nullable', 'max:128'),
            Boolean::make('Required', 'is_required')->default(true),
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
