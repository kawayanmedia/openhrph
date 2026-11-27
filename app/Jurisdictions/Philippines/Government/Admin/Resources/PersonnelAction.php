<?php

namespace App\Jurisdictions\Philippines\Government\Admin\Resources;

use App\Jurisdictions\Philippines\Government\Models\AppointmentRecord as AppointmentRecordModel;
use App\Jurisdictions\Philippines\Government\Models\PersonnelAction as PersonnelActionModel;
use App\Jurisdictions\Philippines\Government\Models\PlantillaItem as PlantillaItemModel;
use App\Livewire\Admin\Actions\Government\AdvancePersonnelAction;
use App\Livewire\Admin\Fields\Badge;
use App\Livewire\Admin\Fields\BelongsTo;
use App\Livewire\Admin\Fields\Date;
use App\Livewire\Admin\Fields\ID;
use App\Livewire\Admin\Fields\Select;
use App\Livewire\Admin\Fields\Textarea;
use App\Livewire\Admin\Resource;
use App\Models\Employee;
use App\Models\Office;
use App\Models\Position;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class PersonnelAction extends Resource
{
    public static string $model = PersonnelActionModel::class;

    public static string $title = 'action_type';

    public static int $priority = 26;

    public static string $group = 'Government';

    public static ?string $sector = 'Government';

    public static ?string $jurisdiction = 'PH';

    public static array $with = ['employee', 'currentAppointment', 'targetPlantillaItem'];

    public static array $search = ['action_type', 'reason'];

    public function title(): string
    {
        return (string) ($this->resource?->action_type ?? '');
    }

    public function fields(Request $request): array
    {
        return [
            ID::make()->sortable(),

            BelongsTo::make('Employee')
                ->options(Employee::employeeList())
                ->rules('required')
                ->sortable(),

            Select::make('Action Type', 'action_type')
                ->options(array_combine(AppointmentRecordModel::TYPES, AppointmentRecordModel::TYPES))
                ->rules('required')
                ->sortable(),

            BelongsTo::make('Current Appointment', 'currentAppointment')
                ->options(AppointmentRecordModel::query()->orderByDesc('id')->limit(200)->pluck('reference_number', 'id')->toArray())
                ->nullable(),

            BelongsTo::make('Target Plantilla Item', 'targetPlantillaItem')
                ->options(PlantillaItemModel::query()->orderBy('item_number')->pluck('item_number', 'id')->toArray())
                ->nullable(),

            BelongsTo::make('Target Position', 'targetPosition')
                ->options(Position::query()->orderBy('name')->pluck('name', 'id')->toArray())
                ->nullable(),

            BelongsTo::make('Target Office', 'targetOffice')
                ->options(Office::query()->orderBy('name')->pluck('name', 'id')->toArray())
                ->nullable()
                ->hideFromIndex(),

            Textarea::make('Reason')->hideFromIndex(),

            Date::make('Proposed Effective Date', 'proposed_effective_date'),
            Date::make('Effective Date', 'effective_date'),

            Badge::make('Status')->map([
                PersonnelActionModel::STATUS_DRAFT => 'default',
                PersonnelActionModel::STATUS_SUBMITTED => 'info',
                PersonnelActionModel::STATUS_UNDER_REVIEW => 'info',
                PersonnelActionModel::STATUS_APPROVED => 'success',
                PersonnelActionModel::STATUS_REJECTED => 'danger',
                PersonnelActionModel::STATUS_COMPLETED => 'success',
                PersonnelActionModel::STATUS_CANCELLED => 'danger',
            ])->sortable(),
        ];
    }

    public static function afterCreate(Request $request, Model $model): void
    {
        $model->forceFill([
            'company_id' => $model->company_id ?? $request->user()->company_id,
            'status' => $model->status ?: PersonnelActionModel::STATUS_DRAFT,
        ])->save();
    }

    public function actions(Request $request): array
    {
        return [new AdvancePersonnelAction];
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
