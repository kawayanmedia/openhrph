<?php

namespace App\Livewire\Admin\Resources;

use App\Livewire\Admin\Fields\Badge;
use App\Livewire\Admin\Fields\BelongsTo;
use App\Livewire\Admin\Fields\DateTime;
use App\Livewire\Admin\Fields\ID;
use App\Livewire\Admin\Fields\Text;
use App\Livewire\Admin\Fields\Textarea;
use App\Livewire\Admin\Resource;
use App\Models\Application as ApplicationModel;
use App\Models\ScreeningResult as ScreeningResultModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class ScreeningResult extends Resource
{
    public static string $model = ScreeningResultModel::class;

    public static string $title = 'result';

    public static int $priority = 53;

    public static string $group = 'Recruitment';

    public static ?string $sector = 'Private';

    public static array $with = ['application', 'reviewer'];

    public static array $search = ['result', 'notes'];

    public function title(): string
    {
        return (string) ($this->resource?->result ?? '');
    }

    public function fields(Request $request): array
    {
        return [
            ID::make()->sortable(),
            BelongsTo::make('Application')
                ->options(ApplicationModel::query()->orderByDesc('id')->limit(300)->pluck('id', 'id')->toArray()),
            Text::make('Criteria Version', 'criteria_version')->hideFromIndex(),
            Badge::make('Result')->map([
                ScreeningResultModel::RESULT_QUALIFIED => 'success',
                ScreeningResultModel::RESULT_DISQUALIFIED => 'danger',
            ])->sortable(),
            DateTime::make('Reviewed At', 'reviewed_at')->sortable(),
            Textarea::make('Notes')->hideFromIndex(),
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

    public static function authorizedToUpdate(?Model $model = null): ?bool
    {
        return false;
    }

    public static function authorizedToDelete(?Model $model = null): ?bool
    {
        return false;
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
