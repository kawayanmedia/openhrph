<?php

namespace App\Livewire\Admin\Resources;

use App\Livewire\Admin\Actions\ApproveAttendance;
use App\Livewire\Admin\Actions\RejectAttendance;
use App\Livewire\Admin\Fields\Badge;
use App\Livewire\Admin\Fields\BelongsTo;
use App\Livewire\Admin\Fields\Date;
use App\Livewire\Admin\Fields\ID;
use App\Livewire\Admin\Fields\Text;
use App\Livewire\Admin\Resource;
use App\Models\Employee;
use App\Models\TimesheetRequest;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class AttendanceRequest extends Resource
{
    public static string $model = TimesheetRequest::class;

    public static int $priority = 6;

    public static string $title = 'id';

    public static string $group = 'Attendance';

    public static array $with = ['employee'];

    public static array $search = ['id'];

    public static function label(): string
    {
        return 'Attendance Requests';
    }

    public function title(): string
    {
        return (string) ($this->resource?->name ?? $this->resource?->getKey() ?? '');
    }

    public function fields(Request $request): array
    {
        return [
            ID::make()->sortable(),

            BelongsTo::make('Employee', 'employee')
                ->options(Employee::employeeList())
                ->showOnIndex()
                ->searchable(),

            Text::make('Type', 'type')
                ->sortable(),

            Text::make('Reason', 'reason'),

            Date::make('Date', 'log_date')
                ->sortable(),

            Text::make('Remarks', 'remarks'),

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
            new ApproveAttendance,
            new RejectAttendance,
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
