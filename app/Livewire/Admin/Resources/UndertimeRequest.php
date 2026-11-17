<?php

namespace App\Livewire\Admin\Resources;

use App\Livewire\Admin\Actions\ApproveUndertime;
use App\Livewire\Admin\Actions\RejectOvertime;
use App\Livewire\Admin\Fields\Badge;
use App\Livewire\Admin\Fields\BelongsTo;
use App\Livewire\Admin\Fields\Date;
use App\Livewire\Admin\Fields\ID;
use App\Livewire\Admin\Fields\Text;
use App\Livewire\Admin\Resource;
use App\Models\Employee;
use App\Models\OvertimeRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class UndertimeRequest extends Resource
{
    public static string $model = OvertimeRequest::class;

    public static int $priority = 6;

    public static string $title = 'id';

    public static string $group = 'Attendance';

    public static array $with = ['employee'];

    public static array $search = ['id'];

    public static function label(): string
    {
        return 'Undertime Requests';
    }

    public static function indexQuery(Builder $query): Builder
    {
        return $query->where('type', OvertimeRequest::TYPE_UNDERTIME);
    }

    public function title(): string
    {
        return (string) ($this->resource?->name ?? '');
    }

    public function fields(Request $request): array
    {
        return [
            ID::make()->sortable(),

            Text::make('Employee No.', 'employee.employee_number'),

            BelongsTo::make('Employee', 'employee')
                ->options(Employee::employeeList())
                ->showOnIndex(),

            Date::make('Date Filed', 'date_filed')
                ->sortable(),

            Text::make('Cover Date', function () {
                return $this->date->format('m/d/Y');
            })
                ->sortable(),

            Text::make('Hours', function () {
                return $this->hours;
            })
                ->sortable(),

            Text::make('Reason'),

            Text::make('Remarks'),

            Badge::make('Status', 'status')->map(status_badge())->filterable(),
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

    public function actions(Request $request): array
    {
        return [
            (new ApproveUndertime(static::$model))->onlyOnTableRow(),
            new RejectOvertime(static::$model),
        ];
    }

    public static function authorizedToCreate(): ?bool
    {
        return false;
    }

    public static function authorizedToUpdate(Model $model): ?bool
    {
        return true;
    }

    public static function authorizedToDelete(Model $model): ?bool
    {
        return true;
    }
}
