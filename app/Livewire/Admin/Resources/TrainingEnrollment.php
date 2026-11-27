<?php

namespace App\Livewire\Admin\Resources;

use App\Livewire\Admin\Actions\Workforce\CompleteTrainingEnrollment;
use App\Livewire\Admin\Fields\Badge;
use App\Livewire\Admin\Fields\BelongsTo;
use App\Livewire\Admin\Fields\Date;
use App\Livewire\Admin\Fields\ID;
use App\Livewire\Admin\Fields\Number;
use App\Livewire\Admin\Fields\Textarea;
use App\Livewire\Admin\Resource;
use App\Models\Employee;
use App\Models\TrainingEnrollment as TrainingEnrollmentModel;
use App\Models\TrainingSession as TrainingSessionModel;
use Illuminate\Http\Request;

class TrainingEnrollment extends Resource
{
    public static string $model = TrainingEnrollmentModel::class;

    public static string $title = 'id';

    public static int $priority = 89;

    public static string $group = 'Talent';

    public static array $with = ['session.course', 'employee'];

    public static array $search = ['status'];

    public function title(): string
    {
        return 'Enrollment #'.($this->resource?->getKey() ?? '');
    }

    public function fields(Request $request): array
    {
        return [
            ID::make()->sortable(),
            BelongsTo::make('Session')
                ->options(TrainingSessionModel::query()->orderByDesc('starts_at')->limit(300)->pluck('id', 'id')->toArray())
                ->rules('required'),
            BelongsTo::make('Employee')
                ->options(Employee::employeeList())
                ->rules('required')
                ->sortable(),
            Badge::make('Status')->map([
                TrainingEnrollmentModel::STATUS_NOMINATED => 'info',
                TrainingEnrollmentModel::STATUS_ENROLLED => 'info',
                TrainingEnrollmentModel::STATUS_ATTENDED => 'warning',
                TrainingEnrollmentModel::STATUS_COMPLETED => 'success',
                TrainingEnrollmentModel::STATUS_FAILED => 'danger',
                TrainingEnrollmentModel::STATUS_WITHDRAWN => 'default',
            ])->sortable(),
            Date::make('Completion Date', 'completion_date'),
            Number::make('Score')->min(0),
            Textarea::make('Evaluation')->hideFromIndex(),
        ];
    }

    public function actions(Request $request): array
    {
        return [new CompleteTrainingEnrollment];
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
