<?php

namespace App\Livewire\Admin\Resources;

use App\Livewire\Admin\Actions\ApproveOfficialBusiness;
use App\Livewire\Admin\Actions\RejectOfficialBusiness;
use App\Livewire\Admin\Fields\Badge;
use App\Livewire\Admin\Fields\BelongsTo;
use App\Livewire\Admin\Fields\Date;
use App\Livewire\Admin\Fields\ID;
use App\Livewire\Admin\Fields\Text;
use App\Livewire\Admin\Resource;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class OfficialBusinessRequest extends Resource
{
    public static string $model = \App\Models\OfficialBusinessRequest::class;

    public static int $priority = 6;

    public static string $title = 'id';

    public static string $group = 'Attendance';

    public static array $with = ['employee'];

    public static array $search = ['id'];

    public static function label(): string
    {
        return 'Official Business Requests';
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

            Text::make('Cover Dates', function () {
                return $this->date_from->format('m/d/Y').' to '.$this->date_to->format('m/d/Y');
            })
                ->sortable(),

            Text::make('Time', function () {
                return $this->time_from->format('h:i A').' - '.$this->time_to->format('h:i A');
            })
                ->sortable(),

            Text::make('Remarks'),

            Text::make('Location'),

            Badge::make('Status', 'status')->map(status_badge())->filterable(),

            Text::make('', function () {
                return "<a class='flex-shrink-0 shadow rounded focus:outline-none ring-primary-200 dark:ring-gray-600 focus:ring bg-primary-500 hover:bg-primary-400 active:bg-primary-600 text-white dark:text-gray-800 inline-flex items-center font-bold px-4 h-9 text-sm' href='".$this->attachment_url."' target='_new'>Attachment</a>";
            })
                ->onlyOnDetail()
                ->asHtml(),
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
            (new ApproveOfficialBusiness(static::$model))->onlyOnTableRow(),
            new RejectOfficialBusiness(static::$model),
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
