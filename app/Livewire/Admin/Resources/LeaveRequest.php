<?php

namespace App\Livewire\Admin\Resources;

use App\Livewire\Admin\Actions\ApproveLeave;
use App\Livewire\Admin\Actions\RejectLeave;
use App\Livewire\Admin\Fields\Badge;
use App\Livewire\Admin\Fields\BelongsTo;
use App\Livewire\Admin\Fields\Boolean;
use App\Livewire\Admin\Fields\Date;
use App\Livewire\Admin\Fields\ID;
use App\Livewire\Admin\Fields\Text;
use App\Livewire\Admin\Resource;
use App\Models\Employee;
use App\Models\Leave;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class LeaveRequest extends Resource
{
    public static string $model = \App\Models\LeaveRequest::class;

    public static int $priority = 6;

    public static string $title = 'id';

    public static string $group = 'Leave Management';

    public static array $with = ['employee'];

    public static array $search = ['id'];

    public static function label(): string
    {
        return 'Leave Requests';
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

            BelongsTo::make('Leave Type', 'leave')
                ->options(Leave::query()->pluck('name', 'id')->toArray())
                ->rules('required'),

            Date::make('Date From', 'date_from')
                ->rules('required'),

            Date::make('Date To', 'date_to')
                ->rules('required'),

            Text::make('Leave Type', function () {
                return $this->leave->name;
            })
                ->sortable(),

            Text::make('Cover Dates', function () {
                return $this->date_from->format('m/d/Y').' to '.$this->date_to->format('m/d/Y');
            })
                ->sortable(),

            Text::make('Days with pay', 'days_with_pay')
                ->rules('nullable', 'integer', 'min:0'),

            Text::make('Days with out pay', 'days_without_pay')
                ->rules('nullable', 'integer', 'min:0'),

            Text::make('Reason', 'reason'),

            Boolean::make('Whole Day', 'is_whole_day'),

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
            new ApproveLeave,
            new RejectLeave,
        ];
    }

    public static function afterCreate(Request $request, Model $model): void
    {
        $model->company_id = $request->user()->company_id;
        $model->save();
    }
}
