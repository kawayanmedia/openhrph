<?php

namespace App\Livewire\Admin\Resources;

use App\Livewire\Admin\Fields\BelongsTo;
use App\Livewire\Admin\Fields\Date;
use App\Livewire\Admin\Fields\ID;
use App\Livewire\Admin\Fields\Select;
use App\Livewire\Admin\Fields\Text;
use App\Livewire\Admin\Fields\Textarea;
use App\Livewire\Admin\Resource;
use App\Models\Document;
use App\Models\Employee;
use App\Models\Employment as EmploymentModel;
use App\Models\ProbationaryReview as ProbationaryReviewModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class ProbationaryReview extends Resource
{
    public static string $model = ProbationaryReviewModel::class;

    public static string $title = 'review_date';

    public static int $priority = 62;

    public static string $group = 'Employment';

    public static ?string $sector = 'Private';

    public static array $with = ['employment', 'evaluator'];

    public static array $search = ['standards_reference', 'outcome'];

    public function title(): string
    {
        return (string) ($this->resource?->review_date?->toDateString() ?? '');
    }

    public function fields(Request $request): array
    {
        return [
            ID::make()->sortable(),
            BelongsTo::make('Employment')
                ->options(EmploymentModel::query()->orderByDesc('id')->limit(300)->pluck('id', 'id')->toArray())
                ->rules('required'),
            Date::make('Review Date', 'review_date')->rules('required')->sortable(),
            Text::make('Standards Reference', 'standards_reference')->rules('nullable', 'max:191'),
            BelongsTo::make('Evaluator')
                ->options(Employee::employeeList())
                ->nullable(),
            Select::make('Outcome')
                ->options([
                    ProbationaryReviewModel::OUTCOME_PASS => 'pass',
                    ProbationaryReviewModel::OUTCOME_EXTEND => 'extend',
                    ProbationaryReviewModel::OUTCOME_FAIL => 'fail',
                ])
                ->nullable(),
            Textarea::make('Supervisor Comments', 'supervisor_comments')->hideFromIndex(),
            Textarea::make('Employee Comments', 'employee_comments')->hideFromIndex(),
            BelongsTo::make('Evidence Document', 'evidenceDocument')
                ->options(Document::query()->orderBy('title')->pluck('title', 'id')->toArray())
                ->nullable()
                ->hideFromIndex(),
        ];
    }

    public static function afterCreate(Request $request, Model $model): void
    {
        $employment = EmploymentModel::query()->find($model->employment_id);

        $model->forceFill([
            'company_id' => $employment?->company_id ?? $request->user()->company_id,
        ])->save();
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
