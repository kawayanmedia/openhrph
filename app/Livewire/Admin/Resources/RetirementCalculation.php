<?php

namespace App\Livewire\Admin\Resources;

use App\Livewire\Admin\Actions\Retirement\ReviewRetirementCalculation;
use App\Livewire\Admin\Fields\Badge;
use App\Livewire\Admin\Fields\BelongsTo;
use App\Livewire\Admin\Fields\DateTime;
use App\Livewire\Admin\Fields\ID;
use App\Livewire\Admin\Fields\Number;
use App\Livewire\Admin\Fields\Select;
use App\Livewire\Admin\Fields\Text;
use App\Livewire\Admin\Fields\Textarea;
use App\Livewire\Admin\Resource;
use App\Models\RetirementCalculation as RetirementCalculationModel;
use App\Models\RetirementCase as RetirementCaseModel;
use Illuminate\Http\Request;

class RetirementCalculation extends Resource
{
    public static string $model = RetirementCalculationModel::class;

    public static string $title = 'id';

    public static int $priority = 93;

    public static string $group = 'Retirement';

    public static array $with = ['retirementCase.employee', 'reviewer'];

    public static array $search = ['rule_version', 'status'];

    public function title(): string
    {
        return 'Calculation #'.($this->resource?->getKey() ?? '');
    }

    public function fields(Request $request): array
    {
        return [
            ID::make()->sortable(),
            BelongsTo::make('Retirement Case', 'retirementCase')
                ->options(RetirementCaseModel::query()->orderByDesc('id')->limit(300)->pluck('id', 'id')->toArray()),
            Select::make('Calculation Type', 'calculation_type')
                ->options(array_combine(RetirementCalculationModel::TYPES, RetirementCalculationModel::TYPES)),
            Text::make('Rule Version', 'rule_version'),
            Number::make('Amount')->min(0)->sortable(),
            Badge::make('Status')->map([
                RetirementCalculationModel::STATUS_DRAFT => 'default',
                RetirementCalculationModel::STATUS_REVIEWED => 'warning',
                RetirementCalculationModel::STATUS_APPROVED => 'success',
                RetirementCalculationModel::STATUS_REJECTED => 'danger',
            ])->sortable(),
            DateTime::make('Computed At', 'computed_at')->hideFromIndex(),
            DateTime::make('Reviewed At', 'reviewed_at')->hideFromIndex(),
            Textarea::make('Reviewer Notes', 'reviewer_notes')->hideFromIndex(),
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

    public function actions(Request $request): array
    {
        return [(new ReviewRetirementCalculation)->onlyOnTableRow()];
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
