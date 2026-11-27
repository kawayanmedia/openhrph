<?php

namespace App\Jurisdictions\Philippines\Government\Admin\Resources;

use App\Jurisdictions\Philippines\Government\Models\AppointmentRecord as AppointmentRecordModel;
use App\Jurisdictions\Philippines\Government\Models\PlantillaItem as PlantillaItemModel;
use App\Jurisdictions\Philippines\Government\Models\QualificationScreening as QualificationScreeningModel;
use App\Jurisdictions\Philippines\Government\Models\QualificationStandard as QualificationStandardModel;
use App\Jurisdictions\Philippines\Government\Services\PlantillaService;
use App\Livewire\Admin\Actions\Government\RecordAppointmentAssumption;
use App\Livewire\Admin\Actions\Government\RecordCscAction;
use App\Livewire\Admin\Actions\Government\TransitionAppointment;
use App\Livewire\Admin\Fields\Badge;
use App\Livewire\Admin\Fields\BelongsTo;
use App\Livewire\Admin\Fields\Date;
use App\Livewire\Admin\Fields\ID;
use App\Livewire\Admin\Fields\Select;
use App\Livewire\Admin\Fields\Text;
use App\Livewire\Admin\Resource;
use App\Models\Employee;
use App\Models\Office;
use App\Models\Position;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AppointmentRecord extends Resource
{
    public static string $model = AppointmentRecordModel::class;

    public static string $title = 'reference_number';

    public static int $priority = 22;

    public static string $group = 'Government';

    public static ?string $sector = 'Government';

    public static ?string $jurisdiction = 'PH';

    public static array $with = ['employee', 'plantillaItem', 'position', 'office'];

    public static array $search = ['reference_number', 'legal_basis'];

    public function title(): string
    {
        $employee = $this->resource?->employee;

        return trim(($this->resource?->reference_number ?? '').' — '.($employee?->fullname ?? ''), ' —');
    }

    public function fields(Request $request): array
    {
        return [
            ID::make()->sortable(),

            BelongsTo::make('Employee')
                ->options(Employee::employeeList())
                ->rules('required')
                ->sortable(),

            BelongsTo::make('Plantilla Item', 'plantillaItem')
                ->options(PlantillaItemModel::query()->orderBy('item_number')->pluck('item_number', 'id')->toArray())
                ->nullable(),

            BelongsTo::make('Position')
                ->options(Position::query()->orderBy('name')->pluck('name', 'id')->toArray())
                ->nullable(),

            BelongsTo::make('Office')
                ->options(Office::query()->orderBy('name')->pluck('name', 'id')->toArray())
                ->nullable()
                ->hideFromIndex(),

            Select::make('Appointment Type', 'appointment_type')
                ->options(array_combine(AppointmentRecordModel::TYPES, AppointmentRecordModel::TYPES))
                ->default(AppointmentRecordModel::TYPE_ORIGINAL)
                ->sortable(),

            Select::make('Nature', 'nature')
                ->options(array_combine(AppointmentRecordModel::NATURES, AppointmentRecordModel::NATURES))
                ->default(AppointmentRecordModel::NATURE_PERMANENT),

            Text::make('Legal Basis', 'legal_basis')->rules('nullable', 'max:191')->hideFromIndex(),
            Text::make('Reference Number', 'reference_number')->rules('nullable', 'max:96')->sortable(),

            Date::make('Effectivity Date', 'effectivity_date')->rules('required')->sortable(),
            Date::make('Assumption Date', 'assumption_date'),

            Badge::make('Status')->map([
                AppointmentRecordModel::STATUS_DRAFT => 'default',
                AppointmentRecordModel::STATUS_FOR_REVIEW => 'info',
                AppointmentRecordModel::STATUS_APPROVED_FOR_ISSUANCE => 'info',
                AppointmentRecordModel::STATUS_ISSUED => 'warning',
                AppointmentRecordModel::STATUS_SERVED => 'warning',
                AppointmentRecordModel::STATUS_SUBMITTED_TO_CSC => 'info',
                AppointmentRecordModel::STATUS_CSC_ACTION_PENDING => 'warning',
                AppointmentRecordModel::STATUS_VALIDATED => 'success',
                AppointmentRecordModel::STATUS_RETURNED => 'danger',
                AppointmentRecordModel::STATUS_CANCELLED => 'danger',
            ])->sortable(),

            BelongsTo::make('Qualification Standard', 'qualificationStandard')
                ->options(QualificationStandardModel::query()->orderBy('position_title')->pluck('position_title', 'id')->toArray())
                ->nullable()
                ->hideFromIndex(),

            BelongsTo::make('Qualification Screening', 'qualificationScreening')
                ->options(QualificationScreeningModel::query()->orderByDesc('id')->limit(200)->pluck('id', 'id')->toArray())
                ->nullable()
                ->hideFromIndex(),

        ];
    }

    public static function afterCreate(Request $request, Model $model): void
    {
        $item = $model->plantilla_item_id === null
            ? null
            : PlantillaItemModel::query()->find($model->plantilla_item_id);

        try {
            if ($item !== null) {
                app(PlantillaService::class)->assertItemAvailable($item, (int) $model->employee_id, (int) $model->getKey());
            }

            if ($model->qualification_screening_id !== null) {
                $screening = QualificationScreeningModel::query()->find($model->qualification_screening_id);

                if ($screening !== null && ! $screening->isQualified()) {
                    throw ValidationException::withMessages([
                        'qualification_screening_id' => 'The recorded screening is disqualified.',
                    ]);
                }
            }
        } catch (\Throwable $exception) {
            $model->forceDelete();

            throw $exception;
        }

        $model->forceFill([
            'company_id' => $model->company_id ?? $request->user()->company_id,
            'position_id' => $model->position_id ?? $item?->position_id,
            'plantilla_version_id' => $model->plantilla_version_id ?? $item?->plantilla_version_id,
            'office_id' => $model->office_id ?? $item?->office_id,
            'status' => $model->status ?: AppointmentRecordModel::STATUS_DRAFT,
        ])->save();
    }

    public function actions(Request $request): array
    {
        return [
            new TransitionAppointment,
            new RecordAppointmentAssumption,
            new RecordCscAction,
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
