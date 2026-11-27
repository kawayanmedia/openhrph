<?php

namespace App\Livewire\Admin\Resources;

use App\Livewire\Admin\Fields\BelongsTo;
use App\Livewire\Admin\Fields\Boolean;
use App\Livewire\Admin\Fields\Date;
use App\Livewire\Admin\Fields\ID;
use App\Livewire\Admin\Fields\Number;
use App\Livewire\Admin\Fields\Text;
use App\Livewire\Admin\Fields\Trix;
use App\Livewire\Admin\Resource;
use App\Models\JobDescription as JobDescriptionModel;
use App\Models\Position;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class JobDescription extends Resource
{
    public static string $model = JobDescriptionModel::class;

    public static int $priority = 38;

    public static string $group = 'HR';

    public static array $with = ['position'];

    public static array $search = ['id', 'title'];

    public function title(): string
    {
        return (string) ($this->resource?->title ?? $this->resource?->position?->name ?? '');
    }

    public function fields(Request $request): array
    {
        return [
            ID::make()->sortable(),

            BelongsTo::make('Position')
                ->options(Position::query()->orderBy('name')->pluck('name', 'id')->toArray())
                ->rules('required')
                ->sortable(),

            Number::make('Version')
                ->min(1)
                ->readonly()
                ->help('Assigned automatically when blank.'),

            Text::make('Title')->rules('nullable', 'max:255'),

            Trix::make('Content')->hideFromIndex(),

            Date::make('Effective From', 'effective_from'),
            Date::make('Effective Until', 'effective_until'),

            Boolean::make('Current', 'is_current')->default(true),
        ];
    }

    public static function afterCreate(Request $request, Model $model): void
    {
        $model->forceFill([
            'company_id' => $model->company_id ?? $request->user()->company_id,
        ])->save();
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
