<?php

namespace App\Livewire\Admin\Resources;

use App\Livewire\Admin\Fields\BelongsTo;
use App\Livewire\Admin\Fields\DateTime;
use App\Livewire\Admin\Fields\ID;
use App\Livewire\Admin\Fields\Select;
use App\Livewire\Admin\Fields\Textarea;
use App\Livewire\Admin\Resource;
use App\Models\Interview as InterviewModel;
use App\Models\InterviewEvaluation as InterviewEvaluationModel;
use App\Models\User;
use Illuminate\Http\Request;

class InterviewEvaluation extends Resource
{
    public static string $model = InterviewEvaluationModel::class;

    public static string $title = 'id';

    public static int $priority = 55;

    public static string $group = 'Recruitment';

    public static ?string $sector = 'Private';

    public static array $with = ['interview', 'reviewer'];

    public static array $search = ['id'];

    public function title(): string
    {
        return 'Evaluation #'.($this->resource?->getKey() ?? '');
    }

    public function fields(Request $request): array
    {
        return [
            ID::make()->sortable(),
            BelongsTo::make('Interview')
                ->options(InterviewModel::query()->orderByDesc('id')->limit(300)->pluck('id', 'id')->toArray())
                ->rules('required'),
            BelongsTo::make('Reviewer')
                ->options(User::query()->orderBy('name')->pluck('name', 'id')->toArray())
                ->nullable(),
            Select::make('Recommendation')
                ->options(['recommend' => 'recommend', 'hold' => 'hold', 'reject' => 'reject'])
                ->nullable(),
            Textarea::make('Comments')->hideFromIndex(),
            DateTime::make('Submitted At', 'submitted_at')->hideFromIndex(),
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
