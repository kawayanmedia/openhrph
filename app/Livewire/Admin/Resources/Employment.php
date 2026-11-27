<?php

namespace App\Livewire\Admin\Resources;

use App\Livewire\Admin\Actions\PrivateSector\RecordRegularizationDecision;
use App\Livewire\Admin\Fields\Badge;
use App\Livewire\Admin\Fields\BelongsTo;
use App\Livewire\Admin\Fields\Date;
use App\Livewire\Admin\Fields\ID;
use App\Livewire\Admin\Resource;
use App\Models\Employee;
use App\Models\Employment as EmploymentModel;
use App\Models\EmploymentType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class Employment extends Resource
{
    public static string $model = EmploymentModel::class;

    public static string $title = 'id';

    public static int $priority = 60;

    public static string $group = 'Employment';

    public static ?string $sector = 'Private';

    public static array $with = ['employee', 'employmentType'];

    public static array $search = ['id', 'classification'];

    public function title(): string
    {
        return (string) ($this->resource?->employee?->fullname ?? 'Employment #'.$this->resource?->getKey());
    }

    public function fields(Request $request): array
    {
        return [
            ID::make()->sortable(),
            BelongsTo::make('Employee')
                ->options(Employee::employeeList())
                ->sortable(),
            BelongsTo::make('Employment Type', 'employmentType')
                ->options(EmploymentType::query()->orderBy('name')->pluck('name', 'id')->toArray())
                ->nullable(),

            Badge::make('Classification')->map([
                EmploymentModel::CLASSIFICATION_REGULAR => 'success',
                EmploymentModel::CLASSIFICATION_PROBATIONARY => 'warning',
                EmploymentModel::CLASSIFICATION_FIXED_TERM => 'info',
                EmploymentModel::CLASSIFICATION_PROJECT => 'info',
                EmploymentModel::CLASSIFICATION_SEASONAL => 'info',
                EmploymentModel::CLASSIFICATION_PART_TIME => 'info',
                EmploymentModel::CLASSIFICATION_CONTRACTUAL => 'info',
            ])->sortable(),

            Date::make('Start Date', 'start_date')->sortable(),
            Date::make('End Date', 'end_date'),
            Date::make('Probation Start', 'probation_start')->hideFromIndex(),
            Date::make('Probation End', 'probation_end'),
            Date::make('Regularization Date', 'regularization_date'),

            Badge::make('Status')->map([
                EmploymentModel::STATUS_ACTIVE => 'success',
                EmploymentModel::STATUS_ENDED => 'default',
                EmploymentModel::STATUS_SUSPENDED => 'warning',
            ])->sortable(),
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

    public function actions(Request $request): array
    {
        return [new RecordRegularizationDecision];
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
