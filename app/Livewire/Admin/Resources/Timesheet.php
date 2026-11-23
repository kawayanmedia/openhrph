<?php

namespace App\Livewire\Admin\Resources;

use App\Enums\Timesheet\DayType;
use App\Livewire\Admin\Actions\EditViewTimeSheet;
use App\Livewire\Admin\Actions\ExportCsv;
use App\Livewire\Admin\Actions\GenerateTimeSheet;
use App\Livewire\Admin\Actions\ImportTimeSheet;
use App\Livewire\Admin\Actions\MarkSelectedAbsent;
use App\Livewire\Admin\Actions\MarkTimesheetAbsent;
use App\Livewire\Admin\Actions\RecomputeSelected;
use App\Livewire\Admin\Actions\RecomputeTimeSheet;
use App\Livewire\Admin\Fields\Badge;
use App\Livewire\Admin\Fields\Date;
use App\Livewire\Admin\Fields\ID;
use App\Livewire\Admin\Fields\Select;
use App\Livewire\Admin\Fields\Text;
use App\Livewire\Admin\Filters\DateRangeFrom;
use App\Livewire\Admin\Filters\DateRangeTo;
use App\Livewire\Admin\Filters\FieldsFilter;
use App\Livewire\Admin\Filters\TimesheetEmployee;
use App\Livewire\Admin\Filters\TimesheetException;
use App\Livewire\Admin\Resource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class Timesheet extends Resource
{
    public static string $model = \App\Models\Timesheet::class;

    public static string $title = 'id';

    public static array $with = ['employee', 'schedule'];

    public static string $group = 'Attendance';

    public static array $search = ['employee_number', 'employee.first_name', 'employee.last_name'];

    public static function label(): string
    {
        return 'Timesheet';
    }

    public static function indexQuery(Builder $query): Builder
    {
        // Get only timesheets with an employee, newest day first.
        return $query->has('employee')->orderByDesc('log_date');
    }

    public function fields(Request $request): array
    {
        return [
            ID::make()->sortable(),

            Select::make('Day Type', 'day_type')
                ->options(DayType::options())
                ->sortable()
                ->onlyOnForms()
                ->rules('required'),

            // Ported from Nova's fieldsForIndex() for the Livewire index table.
            Text::make('Employee Number', 'employee_number')->sortable(),

            Text::make('Employee', function () {
                return $this->employee?->fullname;
            })->filterable()->sortable(),

            // NOTE: engine Date fields render as Y-m-d; Nova's 'M d, Y' displayUsing format was dropped.
            Date::make('Date', 'log_date')->sortable(),

            Text::make('Schedule', function () {
                return $this->schedule?->name;
            }),

            Text::make('Day Type', function () {
                return $this->day_type_format;
            })->asHtml(),

            Text::make('Time In', function () {
                return $this->time_in;
            }),

            Text::make('Time Out', function () {
                return $this->time_out;
            }),

            Text::make('Punches', function () {
                return count($this->logs ?? []);
            }),

            Text::make('Worked Hours', function () {
                return number_format((float) $this->worked_hours, 2);
            }),

            Text::make('Regular', function () {
                return number_format((float) $this->actual_regular, 2);
            }),

            Text::make('OT', function () {
                return number_format((float) $this->actual_ot, 2);
            }),

            Text::make('Night', function () {
                return number_format((float) $this->actual_nt, 2);
            }),

            Text::make('Late (min)', function () {
                return (int) ($this->late_minutes ?? 0);
            }),

            Text::make('Undertime (min)', function () {
                return (int) ($this->undertime_minutes ?? 0);
            }),

            Text::make('Flags', 'exception_flags_format'),

            Badge::make('Status', function () {
                if ((bool) $this->is_locked) {
                    return 'Locked';
                }

                if ($this->has_missing_punch) {
                    return 'Missing punch';
                }

                if ((bool) $this->is_absent) {
                    return 'Absent';
                }

                return 'Open';
            })->map([
                'Locked' => 'info',
                'Missing punch' => 'warning',
                'Absent' => 'danger',
                'Open' => 'success',
            ]),
        ];
    }

    public function cards(Request $request): array
    {
        return [];
    }

    public function filters(Request $request): array
    {
        return [
            new DateRangeFrom,
            new DateRangeTo,
            new TimesheetEmployee,
            new TimesheetException,
            new FieldsFilter,
        ];
    }

    public function lenses(Request $request): array
    {
        return [];
    }

    public function actions(Request $request): array
    {
        return [
            (new ImportTimeSheet)->onlyOnIndexToolbar(),
            (new GenerateTimeSheet)->onlyOnIndexToolbar(),
            (new MarkTimesheetAbsent)->onlyOnIndexToolbar(),
            (new ExportCsv)->onlyOnIndexToolbar()->withFormat(fn ($model) => [
                'Employee Number' => $model->employee_number,
                'Employee' => $model->employee?->fullname,
                'Date' => $model->log_date?->toDateString(),
                'Day Type' => $model->day_type,
                'Time In' => $model->time_in,
                'Time Out' => $model->time_out,
                'Worked Hours' => number_format((float) $model->worked_hours, 2),
                'Status' => $model->is_locked ? 'Locked' : ($model->has_missing_punch ? 'Missing punch' : 'Open'),
            ]),
            (new EditViewTimeSheet($this->resource))->onlyOnTableRow(),
            (new RecomputeTimeSheet($this->resource))->onlyOnTableRow(),
            (new RecomputeSelected)->onlyBulk(),
            (new MarkSelectedAbsent)->onlyBulk(),
        ];
    }

    public static function authorizedToCreate(): ?bool
    {
        return false;
    }

    public static function authorizedToView(?Model $model): ?bool
    {
        return true;
    }

    public static function authorizedToUpdate(Model $model): ?bool
    {
        return false;
    }

    public static function authorizedToDelete(Model $model): ?bool
    {
        return false;
    }
}
