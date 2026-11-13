<?php

namespace App\Livewire\Admin\Resources;

use App\Livewire\Admin\Fields\Boolean;
use App\Livewire\Admin\Fields\Date;
use App\Livewire\Admin\Fields\HasMany;
use App\Livewire\Admin\Fields\ID;
use App\Livewire\Admin\Fields\Text;
use App\Livewire\Admin\Resource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class Schedule extends Resource
{
    public static string $model = \App\Models\Schedule::class;

    public static string $title = 'name';

    public static string $group = 'Attendance';

    public static array $search = ['id', 'name'];

    public function fields(Request $request): array
    {
        return [
            ID::make()->sortable(),

            Text::make('Name')
                ->rules('required')
                ->sortable(),

            // Read-only formatted display (index + detail).
            Text::make('Time In', 'time_in_format')
                ->hideWhenCreating()
                ->hideWhenUpdating()
                ->sortable(),

            Text::make('Break Time', 'break_time')
                ->onlyOnIndex(),

            Date::make('Effective Date Start', 'effective_date_start')->hideFromIndex(),

            Date::make('Effective Date End', 'effective_date_end')->hideFromIndex(),

            Boolean::make('Default Schedule', 'is_default')
                ->default(false)
                ->sortable(),

            Boolean::make('Active', 'is_active')
                ->default(true)
                ->sortable(),

            // Editable time fields (forms only).
            Text::make('Time In', 'time_in')
                ->rules('required')
                ->onlyOnForms()
                ->fillUsing(function ($model, $attribute) {
                    unset($model->{$attribute});
                })
                ->withMeta(['extraAttributes' => [
                    'placeholder' => '08:00',
                    'type' => 'time',
                ]]),

            Text::make('Break Time Start', 'break_time_start')
                ->rules('date_format:H:i', 'nullable')
                ->onlyOnForms()
                ->withMeta(['extraAttributes' => [
                    'placeholder' => '12:00',
                    'type' => 'time',
                ]]),

            Text::make('Break Time End', 'break_time_end')
                ->rules('date_format:H:i', 'nullable')
                ->onlyOnForms()
                ->withMeta(['extraAttributes' => [
                    'placeholder' => '12:00',
                    'type' => 'time',
                ]]),

            Text::make('Time out', 'time_out')
                ->rules('required')
                ->onlyOnForms()
                ->fillUsing(function ($model, $attribute) {
                    unset($model->{$attribute});
                })
                ->withMeta(['extraAttributes' => [
                    'placeholder' => '12:00',
                    'type' => 'time',
                ]]),

            HasMany::make('Employees', 'employeeSchedules')->nullable()->readonly(),
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
        return [];
    }

    public static function afterCreate(Request $request, Model $model): void
    {
        $model->updateTime($request, $model);
    }

    public static function afterUpdate(Request $request, Model $model): void
    {
        $model->updateTime($request, $model);
    }
}
