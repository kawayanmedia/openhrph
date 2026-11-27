<?php

namespace App\Livewire\Admin\Resources;

use App\Livewire\Admin\Fields\BelongsTo;
use App\Livewire\Admin\Fields\Date;
use App\Livewire\Admin\Fields\DateTime;
use App\Livewire\Admin\Fields\ID;
use App\Livewire\Admin\Fields\Select;
use App\Livewire\Admin\Fields\Text;
use App\Livewire\Admin\Resource;
use App\Models\OnboardingChecklist as OnboardingChecklistModel;
use App\Models\OnboardingTask as OnboardingTaskModel;
use Illuminate\Http\Request;

class OnboardingTask extends Resource
{
    public static string $model = OnboardingTaskModel::class;

    public static string $title = 'task';

    public static int $priority = 65;

    public static string $group = 'Employment';

    public static ?string $sector = 'Private';

    public static array $with = ['checklist'];

    public static array $search = ['task', 'owner'];

    public function title(): string
    {
        return (string) ($this->resource?->task ?? '');
    }

    public function fields(Request $request): array
    {
        return [
            ID::make()->sortable(),
            BelongsTo::make('Checklist')
                ->options(OnboardingChecklistModel::query()->orderByDesc('id')->limit(300)->pluck('name', 'id')->toArray())
                ->rules('required'),
            Text::make('Task')->rules('required', 'max:191'),
            Text::make('Owner')->rules('nullable', 'max:64'),
            Date::make('Due At', 'due_at'),
            Select::make('Status')
                ->options([
                    OnboardingTaskModel::STATUS_PENDING => 'pending',
                    OnboardingTaskModel::STATUS_COMPLETED => 'completed',
                    OnboardingTaskModel::STATUS_WAIVED => 'waived',
                ])
                ->default(OnboardingTaskModel::STATUS_PENDING),
            DateTime::make('Completed At', 'completed_at')->hideFromIndex(),
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
