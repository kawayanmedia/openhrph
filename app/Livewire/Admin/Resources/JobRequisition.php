<?php

namespace App\Livewire\Admin\Resources;

use App\Livewire\Admin\Actions\PrivateSector\PublishVacancy;
use App\Livewire\Admin\Actions\PrivateSector\TransitionRequisition;
use App\Livewire\Admin\Fields\Badge;
use App\Livewire\Admin\Fields\BelongsTo;
use App\Livewire\Admin\Fields\Date;
use App\Livewire\Admin\Fields\ID;
use App\Livewire\Admin\Fields\Number;
use App\Livewire\Admin\Fields\Select;
use App\Livewire\Admin\Fields\Textarea;
use App\Livewire\Admin\Resource;
use App\Models\Department;
use App\Models\EmploymentType;
use App\Models\JobRequisition as JobRequisitionModel;
use App\Models\Location;
use App\Models\OrganizationUnit;
use App\Models\Position;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class JobRequisition extends Resource
{
    public static string $model = JobRequisitionModel::class;

    public static string $title = 'id';

    public static int $priority = 50;

    public static string $group = 'Recruitment';

    public static ?string $sector = 'Private';

    public static array $with = ['position', 'organizationUnit', 'department'];

    public static array $search = ['id', 'justification'];

    public function title(): string
    {
        return 'Requisition #'.($this->resource?->getKey() ?? '');
    }

    public function fields(Request $request): array
    {
        return [
            ID::make()->sortable(),

            BelongsTo::make('Position')
                ->options(Position::query()->orderBy('name')->pluck('name', 'id')->toArray())
                ->rules('required'),

            BelongsTo::make('Organization Unit', 'organizationUnit')
                ->options(OrganizationUnit::query()->orderBy('name')->pluck('name', 'id')->toArray())
                ->nullable()
                ->hideFromIndex(),

            BelongsTo::make('Department')
                ->options(Department::query()->orderBy('name')->pluck('name', 'id')->toArray())
                ->nullable()
                ->hideFromIndex(),

            BelongsTo::make('Location')
                ->options(Location::query()->orderBy('name')->pluck('name', 'id')->toArray())
                ->nullable()
                ->hideFromIndex(),

            BelongsTo::make('Employment Type', 'employmentType')
                ->options(EmploymentType::query()->orderBy('name')->pluck('name', 'id')->toArray())
                ->nullable()
                ->hideFromIndex(),

            Select::make('Reason')
                ->options(array_combine(JobRequisitionModel::REASONS, JobRequisitionModel::REASONS))
                ->default(JobRequisitionModel::REASON_NEW_HEADCOUNT),

            Number::make('Headcount')->min(1)->default(1),

            Date::make('Target Date', 'target_date'),
            Number::make('Salary Min', 'salary_min')->min(0)->hideFromIndex(),
            Number::make('Salary Max', 'salary_max')->min(0)->hideFromIndex(),

            Textarea::make('Justification')->hideFromIndex(),

            Badge::make('Status')->map([
                JobRequisitionModel::STATUS_DRAFT => 'default',
                JobRequisitionModel::STATUS_SUBMITTED => 'info',
                JobRequisitionModel::STATUS_UNDER_REVIEW => 'info',
                JobRequisitionModel::STATUS_APPROVED => 'success',
                JobRequisitionModel::STATUS_REJECTED => 'danger',
                JobRequisitionModel::STATUS_ON_HOLD => 'warning',
                JobRequisitionModel::STATUS_CLOSED => 'default',
                JobRequisitionModel::STATUS_CANCELLED => 'danger',
            ])->sortable(),
        ];
    }

    public static function afterCreate(Request $request, Model $model): void
    {
        $model->forceFill([
            'company_id' => $model->company_id ?? $request->user()->company_id,
            'requested_by' => $model->requested_by ?? $request->user()->getKey(),
            'status' => $model->status ?: JobRequisitionModel::STATUS_DRAFT,
        ])->save();
    }

    public function actions(Request $request): array
    {
        return [new TransitionRequisition, new PublishVacancy];
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
