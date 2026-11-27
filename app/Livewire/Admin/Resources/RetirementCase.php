<?php

namespace App\Livewire\Admin\Resources;

use App\Livewire\Admin\Actions\Retirement\ManageRetirementCase;
use App\Livewire\Admin\Actions\Retirement\OpenRetirementCase;
use App\Livewire\Admin\Fields\Badge;
use App\Livewire\Admin\Fields\BelongsTo;
use App\Livewire\Admin\Fields\Date;
use App\Livewire\Admin\Fields\DateTime;
use App\Livewire\Admin\Fields\ID;
use App\Livewire\Admin\Fields\Select;
use App\Livewire\Admin\Fields\Text;
use App\Livewire\Admin\Fields\Textarea;
use App\Livewire\Admin\Resource;
use App\Models\Employee;
use App\Models\RetirementCase as RetirementCaseModel;
use App\Models\RetirementPlan as RetirementPlanModel;
use Illuminate\Http\Request;

class RetirementCase extends Resource
{
    public static string $model = RetirementCaseModel::class;

    public static string $title = 'id';

    public static int $priority = 91;

    public static string $group = 'Retirement';

    public static array $with = ['employee', 'plan'];

    public static array $search = ['status', 'employee.first_name', 'employee.last_name'];

    public function title(): string
    {
        return 'Retirement #'.($this->resource?->getKey() ?? '');
    }

    public function fields(Request $request): array
    {
        return [
            ID::make()->sortable(),
            BelongsTo::make('Employee')
                ->options(Employee::employeeList())
                ->sortable(),
            BelongsTo::make('Plan')
                ->options(RetirementPlanModel::query()->orderBy('name')->pluck('name', 'id')->toArray())
                ->nullable(),
            Select::make('Retirement Type', 'retirement_type')
                ->options(array_combine(RetirementCaseModel::TYPES, RetirementCaseModel::TYPES))
                ->default(RetirementCaseModel::TYPE_OPTIONAL),
            Badge::make('Status')->map([
                RetirementCaseModel::STATUS_DRAFT => 'default',
                RetirementCaseModel::STATUS_ELIGIBILITY_REVIEW => 'info',
                RetirementCaseModel::STATUS_APPLIED => 'warning',
                RetirementCaseModel::STATUS_APPROVED => 'success',
                RetirementCaseModel::STATUS_COMPLETED => 'success',
                RetirementCaseModel::STATUS_CANCELLED => 'danger',
            ])->sortable(),
            Date::make('Proposed Retirement Date', 'proposed_retirement_date')->sortable(),
            Text::make('Eligible', fn () => ($this->eligibility_snapshot['eligible'] ?? false) ? 'Yes' : 'No'),
            DateTime::make('Applied At', 'applied_at')->hideFromIndex(),
            DateTime::make('Approved At', 'approved_at')->hideFromIndex(),
            Textarea::make('Notes')->hideFromIndex(),
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
        return [
            (new OpenRetirementCase)->onlyOnIndexToolbar(),
            (new ManageRetirementCase)->onlyOnTableRow(),
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
}
