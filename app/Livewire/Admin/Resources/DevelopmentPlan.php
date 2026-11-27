<?php

namespace App\Livewire\Admin\Resources;

use App\Livewire\Admin\Fields\BelongsTo;
use App\Livewire\Admin\Fields\Date;
use App\Livewire\Admin\Fields\ID;
use App\Livewire\Admin\Fields\Select;
use App\Livewire\Admin\Fields\Text;
use App\Livewire\Admin\Fields\Textarea;
use App\Livewire\Admin\Resource;
use App\Models\DevelopmentPlan as DevelopmentPlanModel;
use App\Models\Employee;
use App\Models\PerformanceCycle as PerformanceCycleModel;
use Illuminate\Http\Request;

class DevelopmentPlan extends Resource
{
    public static string $model = DevelopmentPlanModel::class;

    public static string $title = 'title';

    public static int $priority = 86;

    public static string $group = 'Talent';

    public static array $with = ['employee', 'cycle'];

    public static array $search = ['title', 'goals'];

    public function title(): string
    {
        return (string) ($this->resource?->title ?? '');
    }

    public function fields(Request $request): array
    {
        return [
            ID::make()->sortable(),
            BelongsTo::make('Employee')
                ->options(Employee::employeeList())
                ->rules('required'),
            BelongsTo::make('Cycle')
                ->options(PerformanceCycleModel::query()->orderByDesc('id')->pluck('name', 'id')->toArray())
                ->nullable(),
            Text::make('Title')->rules('required', 'max:191'),
            Textarea::make('Goals')->hideFromIndex(),
            Select::make('Status')
                ->options([
                    DevelopmentPlanModel::STATUS_DRAFT => 'draft',
                    DevelopmentPlanModel::STATUS_ACTIVE => 'active',
                    DevelopmentPlanModel::STATUS_COMPLETED => 'completed',
                    DevelopmentPlanModel::STATUS_CANCELLED => 'cancelled',
                ])
                ->default(DevelopmentPlanModel::STATUS_DRAFT),
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
