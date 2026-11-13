<?php

namespace App\Livewire\Admin\Resources;

use App\Enums\Timesheet\DayType;
use App\Livewire\Admin\Actions\EditViewTimeSheet;
use App\Livewire\Admin\Actions\GenerateTimeSheet;
use App\Livewire\Admin\Actions\ImportTimeSheet;
use App\Livewire\Admin\Fields\Date;
use App\Livewire\Admin\Fields\ID;
use App\Livewire\Admin\Fields\Select;
use App\Livewire\Admin\Fields\Text;
use App\Livewire\Admin\Filters\DateRangeFrom;
use App\Livewire\Admin\Filters\DateRangeTo;
use App\Livewire\Admin\Filters\FieldsFilter;
use App\Livewire\Admin\Resource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class Timesheet extends Resource
{
    public static string $model = \App\Models\Timesheet::class;

    public static string $title = 'id';

    public static array $with = ['employee'];

    public static string $group = 'Attendance';

    public static array $search = ['employee.first_name', 'employee.last_name'];

    public static function label(): string
    {
        return 'Timesheet';
    }

    public static function indexQuery(Builder $query): Builder
    {
        // Get only timesheets with an employee.
        return $query->has('employee');
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

            Text::make('TimeIn', 'am_login'),

            Text::make('Hours', function () {
                return number_format($this->total_hours, 2);
            }),
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
            (new EditViewTimeSheet($this->resource))->onlyOnTableRow(),
            (new GenerateTimeSheet)->onlyOnIndexToolbar(),
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
