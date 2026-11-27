<?php

namespace App\Livewire\Admin\Resources;

use App\Livewire\Admin\Fields\Badge;
use App\Livewire\Admin\Fields\BelongsTo;
use App\Livewire\Admin\Fields\Date;
use App\Livewire\Admin\Fields\ID;
use App\Livewire\Admin\Fields\Select;
use App\Livewire\Admin\Fields\Text;
use App\Livewire\Admin\Resource;
use App\Models\CostCenter;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmploymentType;
use App\Models\Location;
use App\Models\Office;
use App\Models\OrganizationUnit;
use App\Models\Position;
use App\Models\PositionAssignment as PositionAssignmentModel;
use App\Services\Organization\PositionAssignmentService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Read-only history. Assignments are created through the position assignment
 * form so overlap checks, employee sync and employment-change history always
 * run in one transaction.
 */
class PositionAssignment extends Resource
{
    public static string $model = PositionAssignmentModel::class;

    public static int $priority = 36;

    public static string $group = 'HR';

    public static array $with = ['employee', 'position', 'organizationUnit', 'supervisor'];

    public static array $search = ['id'];

    public function title(): string
    {
        $employee = $this->resource?->employee;

        return trim(($employee?->last_name ?? '').', '.($employee?->first_name ?? ''), ', ');
    }

    public function fields(Request $request): array
    {
        return [
            ID::make()->sortable(),

            BelongsTo::make('Employee')
                ->options(Employee::employeeList())
                ->rules('required')
                ->sortable(),

            BelongsTo::make('Position')
                ->options(Position::query()->orderBy('name')->pluck('name', 'id')->toArray())
                ->rules('required'),

            BelongsTo::make('Unit', 'organizationUnit')
                ->options(OrganizationUnit::query()->orderBy('name')->pluck('name', 'id')->toArray())
                ->hideFromIndex(),

            BelongsTo::make('Department')
                ->options(Department::query()->orderBy('name')->pluck('name', 'id')->toArray())
                ->hideFromIndex(),

            BelongsTo::make('Office')
                ->options(Office::query()->orderBy('name')->pluck('name', 'id')->toArray())
                ->hideFromIndex(),

            BelongsTo::make('Location')
                ->options(Location::query()->orderBy('name')->pluck('name', 'id')->toArray())
                ->hideFromIndex(),

            BelongsTo::make('Cost Center', 'costCenter')
                ->options(CostCenter::query()->orderBy('name')->pluck('name', 'id')->toArray())
                ->hideFromIndex(),

            BelongsTo::make('Supervisor')
                ->options(Employee::employeeList())
                ->hideFromIndex(),

            BelongsTo::make('Employment Type', 'employmentType')
                ->options(EmploymentType::query()->orderBy('name')->pluck('name', 'id')->toArray())
                ->hideFromIndex(),

            Select::make('Assignment Type', 'assignment_type')
                ->options(array_combine(PositionAssignmentModel::TYPES, PositionAssignmentModel::TYPES))
                ->sortable(),

            Badge::make('Status', 'status')->map([
                PositionAssignmentModel::STATUS_ACTIVE => 'success',
                PositionAssignmentModel::STATUS_SCHEDULED => 'info',
                PositionAssignmentModel::STATUS_ENDED => 'default',
            ])->sortable(),

            Date::make('Effective From', 'effective_from')->rules('required')->sortable(),
            Date::make('Effective Until', 'effective_until'),

            Text::make('Reference')->hideFromIndex(),
            Text::make('Notes')->hideFromIndex(),
        ];
    }

    public static function canCreateResource(): bool
    {
        return true;
    }

    public static function afterCreate(Request $request, Model $model): void
    {
        app(PositionAssignmentService::class)
            ->applyCreatedAssignment($model, $request->user());
    }

    public static function authorizedToUpdate(?Model $model = null): ?bool
    {
        return false;
    }

    public static function authorizedToDelete(?Model $model = null): ?bool
    {
        return false;
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
