<?php

namespace App\Livewire\Admin\Resources;

use App\Livewire\Admin\Actions\Workforce\FinalizePerformanceReview;
use App\Livewire\Admin\Fields\Badge;
use App\Livewire\Admin\Fields\BelongsTo;
use App\Livewire\Admin\Fields\DateTime;
use App\Livewire\Admin\Fields\ID;
use App\Livewire\Admin\Fields\Number;
use App\Livewire\Admin\Fields\Textarea;
use App\Livewire\Admin\Resource;
use App\Models\Employee;
use App\Models\PerformanceCycle as PerformanceCycleModel;
use App\Models\PerformanceReview as PerformanceReviewModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class PerformanceReview extends Resource
{
    public static string $model = PerformanceReviewModel::class;

    public static string $title = 'id';

    public static int $priority = 84;

    public static string $group = 'Talent';

    public static array $with = ['cycle', 'employee', 'reviewer'];

    public static array $search = ['status'];

    public function title(): string
    {
        return 'Review #'.($this->resource?->getKey() ?? '');
    }

    public function fields(Request $request): array
    {
        return [
            ID::make()->sortable(),
            BelongsTo::make('Cycle')
                ->options(PerformanceCycleModel::query()->orderByDesc('id')->pluck('name', 'id')->toArray()),
            BelongsTo::make('Employee')
                ->options(Employee::employeeList())
                ->sortable(),
            BelongsTo::make('Reviewer')
                ->options(Employee::employeeList())
                ->nullable(),
            Badge::make('Status')->map([
                PerformanceReviewModel::STATUS_DRAFT => 'default',
                PerformanceReviewModel::STATUS_SELF_ASSESSMENT => 'info',
                PerformanceReviewModel::STATUS_SUBMITTED => 'info',
                PerformanceReviewModel::STATUS_UNDER_REVIEW => 'warning',
                PerformanceReviewModel::STATUS_CALIBRATED => 'warning',
                PerformanceReviewModel::STATUS_FINALIZED => 'success',
                PerformanceReviewModel::STATUS_ACKNOWLEDGED => 'success',
            ])->sortable(),
            Textarea::make('Self Assessment', 'self_assessment')->hideFromIndex(),
            Textarea::make('Supervisor Comments', 'supervisor_comments')->hideFromIndex(),
            Number::make('Overall Rating', 'overall_rating')->min(0)->readonly(),
            DateTime::make('Finalized At', 'finalized_at')->readonly()->hideFromIndex(),
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
        return ! $model->isFinalized();
    }

    public static function authorizedToDelete(Model $model): ?bool
    {
        return ! $model->isFinalized();
    }

    public function actions(Request $request): array
    {
        return [new FinalizePerformanceReview];
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
}
