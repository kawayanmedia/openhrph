<?php

namespace App\Livewire\Admin\Resources;

use App\Livewire\Admin\Fields\BelongsTo;
use App\Livewire\Admin\Fields\ID;
use App\Livewire\Admin\Fields\Number;
use App\Livewire\Admin\Fields\Text;
use App\Livewire\Admin\Resource;
use App\Models\PerformanceRating as PerformanceRatingModel;
use App\Models\PerformanceReview as PerformanceReviewModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class PerformanceRating extends Resource
{
    public static string $model = PerformanceRatingModel::class;

    public static string $title = 'criterion';

    public static int $priority = 85;

    public static string $group = 'Talent';

    public static array $with = ['review'];

    public static array $search = ['criterion'];

    public function title(): string
    {
        return (string) ($this->resource?->criterion ?? '');
    }

    public function fields(Request $request): array
    {
        return [
            ID::make()->sortable(),
            BelongsTo::make('Review')
                ->options(PerformanceReviewModel::query()->orderByDesc('id')->limit(300)->pluck('id', 'id')->toArray()),
            Text::make('Criterion')->rules('required', 'max:191'),
            Number::make('Score')->min(0)->sortable(),
            Number::make('Weight')->min(0)->max(100),
            Text::make('Comments')->hideFromIndex(),
        ];
    }

    public static function canCreateResource(): bool
    {
        return false;
    }

    public static function authorizedToCreate(): ?bool
    {
        return false;
    }

    public static function authorizedToUpdate(Model $model): ?bool
    {
        return ! $model->review?->isFinalized();
    }

    public static function authorizedToDelete(Model $model): ?bool
    {
        return ! $model->review?->isFinalized();
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
