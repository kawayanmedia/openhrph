<?php

namespace App\Livewire\Admin\Resources;

use App\Livewire\Admin\Fields\Badge;
use App\Livewire\Admin\Fields\BelongsTo;
use App\Livewire\Admin\Fields\Date;
use App\Livewire\Admin\Fields\ID;
use App\Livewire\Admin\Fields\Text;
use App\Livewire\Admin\Resource;
use App\Models\Employee;
use App\Models\EmploymentChange as EmploymentChangeModel;
use App\Models\Position;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Read-only history written by the position assignment service.
 */
class EmploymentChange extends Resource
{
    public static string $model = EmploymentChangeModel::class;

    public static int $priority = 39;

    public static string $group = 'HR';

    public static array $with = ['employee', 'fromPosition', 'toPosition'];

    public static array $search = ['id', 'change_type'];

    public function title(): string
    {
        return (string) ($this->resource?->change_type ?? '');
    }

    public function fields(Request $request): array
    {
        return [
            ID::make()->sortable(),

            BelongsTo::make('Employee')
                ->options(Employee::employeeList()),

            Badge::make('Change', 'change_type')->map([
                EmploymentChangeModel::TYPE_HIRE => 'success',
                EmploymentChangeModel::TYPE_PROMOTION => 'success',
                EmploymentChangeModel::TYPE_TRANSFER => 'info',
                EmploymentChangeModel::TYPE_DEMOTION => 'warning',
                EmploymentChangeModel::TYPE_REGULARIZATION => 'success',
                EmploymentChangeModel::TYPE_SEPARATION => 'danger',
                EmploymentChangeModel::TYPE_OTHER => 'default',
            ])->sortable(),

            Date::make('Effective Date', 'effective_date')->sortable(),

            BelongsTo::make('From Position', 'fromPosition')
                ->options(Position::query()->orderBy('name')->pluck('name', 'id')->toArray()),

            BelongsTo::make('To Position', 'toPosition')
                ->options(Position::query()->orderBy('name')->pluck('name', 'id')->toArray()),

            Text::make('Reason')->hideFromIndex(),
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
