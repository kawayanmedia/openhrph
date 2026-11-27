<?php

namespace App\Livewire\Admin\Resources;

use App\Livewire\Admin\Actions\Workforce\DecideAttendanceCorrection;
use App\Livewire\Admin\Fields\Badge;
use App\Livewire\Admin\Fields\BelongsTo;
use App\Livewire\Admin\Fields\Date;
use App\Livewire\Admin\Fields\ID;
use App\Livewire\Admin\Fields\Select;
use App\Livewire\Admin\Fields\Textarea;
use App\Livewire\Admin\Resource;
use App\Models\AttendanceCorrection as AttendanceCorrectionModel;
use App\Models\AttendancePunch;
use App\Models\Employee;
use App\Models\Timesheet;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AttendanceCorrection extends Resource
{
    public static string $model = AttendanceCorrectionModel::class;

    public static string $title = 'id';

    public static int $priority = 80;

    public static string $group = 'Workforce';

    public static array $with = ['employee', 'punch', 'timesheet'];

    public static array $search = ['id', 'reason'];

    public function title(): string
    {
        return 'Correction #'.($this->resource?->getKey() ?? '');
    }

    public function fields(Request $request): array
    {
        return [
            ID::make()->sortable(),
            BelongsTo::make('Employee')
                ->options(Employee::employeeList())
                ->rules('required')
                ->sortable(),
            BelongsTo::make('Punch')
                ->options(AttendancePunch::query()->orderByDesc('punched_at')->limit(300)->pluck('id', 'id')->toArray())
                ->nullable()
                ->hideFromIndex(),
            BelongsTo::make('Timesheet')
                ->options(Timesheet::query()->orderByDesc('log_date')->limit(300)->pluck('id', 'id')->toArray())
                ->nullable()
                ->hideFromIndex(),
            Date::make('Workday Date', 'workday_date')->rules('required')->sortable(),
            Select::make('Correction Type', 'correction_type')
                ->options(array_combine(AttendanceCorrectionModel::TYPES, AttendanceCorrectionModel::TYPES))
                ->default(AttendanceCorrectionModel::TYPE_WRONG_TIME),
            Textarea::make('Requested Values (JSON)', 'requested_values')
                ->rules('required')
                ->help('{"punched_at":"2026-11-27 08:00:00","direction":0}')
                ->resolveUsing(fn ($value) => is_array($value)
                    ? json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
                    : $value)
                ->fillUsing(function ($model, $attribute, $value): void {
                    $decoded = is_string($value) ? json_decode($value, true) : $value;

                    if (! is_array($decoded)) {
                        throw ValidationException::withMessages([
                            'requested_values' => 'Requested values must be valid JSON.',
                        ]);
                    }

                    $model->setAttribute($attribute, $decoded);
                }),
            Textarea::make('Reason')->hideFromIndex(),
            Badge::make('Status')->map([
                AttendanceCorrectionModel::STATUS_SUBMITTED => 'info',
                AttendanceCorrectionModel::STATUS_APPROVED => 'success',
                AttendanceCorrectionModel::STATUS_REJECTED => 'danger',
                AttendanceCorrectionModel::STATUS_APPLIED => 'success',
                AttendanceCorrectionModel::STATUS_CANCELLED => 'default',
            ])->sortable(),
        ];
    }

    public static function afterCreate(Request $request, Model $model): void
    {
        $model->forceFill([
            'company_id' => $model->company_id ?? $request->user()->company_id,
            'status' => $model->status ?: AttendanceCorrectionModel::STATUS_SUBMITTED,
            'requested_by' => $model->requested_by ?? $request->user()->getKey(),
        ])->save();
    }

    public function actions(Request $request): array
    {
        return [new DecideAttendanceCorrection];
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
