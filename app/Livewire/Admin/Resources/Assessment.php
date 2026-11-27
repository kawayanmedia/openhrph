<?php

namespace App\Livewire\Admin\Resources;

use App\Livewire\Admin\Fields\BelongsTo;
use App\Livewire\Admin\Fields\DateTime;
use App\Livewire\Admin\Fields\ID;
use App\Livewire\Admin\Fields\Number;
use App\Livewire\Admin\Fields\Text;
use App\Livewire\Admin\Resource;
use App\Models\Application as ApplicationModel;
use App\Models\Assessment as AssessmentModel;
use Illuminate\Http\Request;

class Assessment extends Resource
{
    public static string $model = AssessmentModel::class;

    public static string $title = 'assessment_type';

    public static int $priority = 56;

    public static string $group = 'Recruitment';

    public static ?string $sector = 'Private';

    public static array $with = ['application'];

    public static array $search = ['assessment_type', 'result'];

    public function title(): string
    {
        return (string) ($this->resource?->assessment_type ?? '');
    }

    public function fields(Request $request): array
    {
        return [
            ID::make()->sortable(),
            BelongsTo::make('Application')
                ->options(ApplicationModel::query()->orderByDesc('id')->limit(300)->pluck('id', 'id')->toArray())
                ->rules('required'),
            Text::make('Assessment Type', 'assessment_type')->rules('required', 'max:64'),
            Number::make('Score')->min(0),
            Text::make('Result')->rules('nullable', 'max:32'),
            Text::make('Evidence')->hideFromIndex(),
            DateTime::make('Assessed At', 'assessed_at')->hideFromIndex(),
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
