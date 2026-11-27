<?php

namespace App\Livewire\Admin\Resources;

use App\Livewire\Admin\Fields\BelongsTo;
use App\Livewire\Admin\Fields\Date;
use App\Livewire\Admin\Fields\ID;
use App\Livewire\Admin\Fields\Number;
use App\Livewire\Admin\Fields\Select;
use App\Livewire\Admin\Fields\Text;
use App\Livewire\Admin\Fields\Textarea;
use App\Livewire\Admin\Resource;
use App\Models\Employee;
use App\Models\PerformanceCycle as PerformanceCycleModel;
use App\Models\PerformanceGoal as PerformanceGoalModel;
use Illuminate\Http\Request;

class PerformanceGoal extends Resource
{
    public static string $model = PerformanceGoalModel::class;

    public static string $title = 'title';

    public static int $priority = 83;

    public static string $group = 'Talent';

    public static array $with = ['cycle', 'employee'];

    public static array $search = ['title', 'measures'];

    public function title(): string
    {
        return (string) ($this->resource?->title ?? '');
    }

    public function fields(Request $request): array
    {
        return [
            ID::make()->sortable(),
            BelongsTo::make('Cycle')
                ->options(PerformanceCycleModel::query()->orderByDesc('id')->pluck('name', 'id')->toArray())
                ->rules('required'),
            BelongsTo::make('Employee')
                ->options(Employee::employeeList())
                ->rules('required'),
            BelongsTo::make('Parent Goal', 'parent')
                ->options(PerformanceGoalModel::query()->orderBy('title')->limit(300)->pluck('title', 'id')->toArray())
                ->nullable()
                ->hideFromIndex(),
            Text::make('Title')->rules('required', 'max:191'),
            Textarea::make('Measures')->hideFromIndex(),
            Number::make('Weight')->min(0)->max(100),
            Text::make('Target')->rules('nullable', 'max:191'),
            Select::make('Status')
                ->options([
                    PerformanceGoalModel::STATUS_DRAFT => 'draft',
                    PerformanceGoalModel::STATUS_AGREED => 'agreed',
                    PerformanceGoalModel::STATUS_IN_PROGRESS => 'in_progress',
                    PerformanceGoalModel::STATUS_COMPLETED => 'completed',
                    PerformanceGoalModel::STATUS_CANCELLED => 'cancelled',
                ])
                ->default(PerformanceGoalModel::STATUS_DRAFT),
            Date::make('Due Date', 'due_date'),
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
