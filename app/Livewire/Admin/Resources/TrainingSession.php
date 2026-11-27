<?php

namespace App\Livewire\Admin\Resources;

use App\Livewire\Admin\Fields\BelongsTo;
use App\Livewire\Admin\Fields\DateTime;
use App\Livewire\Admin\Fields\ID;
use App\Livewire\Admin\Fields\Number;
use App\Livewire\Admin\Fields\Select;
use App\Livewire\Admin\Fields\Text;
use App\Livewire\Admin\Resource;
use App\Models\LearningCourse as TrainingCourseModel;
use App\Models\TrainingSession as TrainingSessionModel;
use Illuminate\Http\Request;

class TrainingSession extends Resource
{
    public static string $model = TrainingSessionModel::class;

    public static string $title = 'id';

    public static int $priority = 88;

    public static string $group = 'Talent';

    public static array $with = ['course'];

    public static array $search = ['location', 'status'];

    public function title(): string
    {
        return (string) ($this->resource?->course?->title ?? 'Session #'.($this->resource?->getKey() ?? ''));
    }

    public function fields(Request $request): array
    {
        return [
            ID::make()->sortable(),
            BelongsTo::make('Course')
                ->options(TrainingCourseModel::query()->orderBy('title')->pluck('title', 'id')->toArray())
                ->rules('required'),
            DateTime::make('Starts At', 'starts_at')->rules('required')->sortable(),
            DateTime::make('Ends At', 'ends_at')->rules('required'),
            Text::make('Location')->rules('nullable', 'max:191'),
            Number::make('Capacity')->min(1),
            Number::make('Cost')->min(0),
            Select::make('Status')
                ->options([
                    TrainingSessionModel::STATUS_SCHEDULED => 'scheduled',
                    TrainingSessionModel::STATUS_COMPLETED => 'completed',
                    TrainingSessionModel::STATUS_CANCELLED => 'cancelled',
                ])
                ->default(TrainingSessionModel::STATUS_SCHEDULED),
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
