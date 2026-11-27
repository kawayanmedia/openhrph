<?php

namespace App\Livewire\Admin\Resources;

use App\Livewire\Admin\Actions\PrivateSector\RecordFinalSettlement;
use App\Livewire\Admin\Actions\PrivateSector\TransitionSeparation;
use App\Livewire\Admin\Fields\Badge;
use App\Livewire\Admin\Fields\BelongsTo;
use App\Livewire\Admin\Fields\Date;
use App\Livewire\Admin\Fields\ID;
use App\Livewire\Admin\Fields\Select;
use App\Livewire\Admin\Fields\Textarea;
use App\Livewire\Admin\Resource;
use App\Models\Employee;
use App\Models\SeparationCase as SeparationCaseModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class SeparationCase extends Resource
{
    public static string $model = SeparationCaseModel::class;

    public static string $title = 'id';

    public static int $priority = 70;

    public static string $group = 'Separation';

    public static ?string $sector = 'Private';

    public static array $with = ['employee'];

    public static array $search = ['id', 'reason'];

    public function title(): string
    {
        return 'Separation #'.($this->resource?->getKey() ?? '');
    }

    public function fields(Request $request): array
    {
        return [
            ID::make()->sortable(),
            BelongsTo::make('Employee')
                ->options(Employee::employeeList())
                ->sortable(),
            Select::make('Separation Type', 'separation_type')
                ->options(array_combine(SeparationCaseModel::TYPES, SeparationCaseModel::TYPES))
                ->rules('required'),
            Date::make('Proposed Effective Date', 'proposed_effective_date'),
            Date::make('Effective Date', 'effective_date'),
            Badge::make('Status')->map([
                SeparationCaseModel::STATUS_INITIATED => 'default',
                SeparationCaseModel::STATUS_REVIEW => 'info',
                SeparationCaseModel::STATUS_CLEARANCE_IN_PROGRESS => 'info',
                SeparationCaseModel::STATUS_FINAL_PAY_REVIEW => 'warning',
                SeparationCaseModel::STATUS_APPROVED => 'success',
                SeparationCaseModel::STATUS_EFFECTIVE => 'success',
                SeparationCaseModel::STATUS_CLOSED => 'default',
                SeparationCaseModel::STATUS_CANCELLED => 'danger',
            ])->sortable(),
            Textarea::make('Reason')->hideFromIndex(),
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
        return [new TransitionSeparation, new RecordFinalSettlement];
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
