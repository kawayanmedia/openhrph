<?php

namespace App\Livewire\Admin\Resources;

use App\Livewire\Admin\Fields\Date;
use App\Livewire\Admin\Fields\Select;
use App\Livewire\Admin\Fields\Text;
use App\Livewire\Admin\Resource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class Holiday extends Resource
{
    public static string $model = \App\Models\Holiday::class;

    public static int $priority = 6;

    public static string $title = 'id';

    public static string $group = 'Attendance';

    public static array $search = ['id'];

    public static function label(): string
    {
        return 'Holidays';
    }

    public function title(): string
    {
        return (string) ($this->resource?->name ?? $this->resource?->getKey() ?? '');
    }

    public static function indexQuery(Builder $query): Builder
    {
        return $query;
    }

    public function fields(Request $request): array
    {
        return [
            Text::make('Name', 'name')
                ->rules('required')
                ->sortable(),

            Select::make('Type', 'holiday_type')
                ->options([
                    'Special Working Holiday' => 'Special Working Holiday',
                    'Special Non-Working Holiday' => 'Special Non-Working Holiday',
                    'Regular Holiday' => 'Regular Holiday',
                    'Double Regular Holiday' => 'Double Regular Holiday',
                    'Regular Holiday, Special Holiday' => 'Regular Holiday, Special Holiday',
                ])
                ->sortable()
                ->rules('required')
                ->filterable(),

            Date::make('Date', 'date')
                ->rules('required')
                ->sortable(),

            // Original Nova closure returned $model->xxxx (no such attribute);
            // kept as an empty computed field.
            Text::make('Affected Offices', function () {
                return '';
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
            new \App\Livewire\Admin\Filters\HolidayYearFilter,
        ];
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
        static::setHolidayCode($request, $model);
    }

    public static function afterUpdate(Request $request, Model $model): void
    {
        static::setHolidayCode($request, $model);
    }

    protected static function setHolidayCode(Request $request, Model $model): void
    {
        if (substr($request->holiday_type, 0, 7) == 'Special') {
            $model->code = 'sh';
        } else {
            $model->code = 'rh';
        }

        if ($request->holiday_type == 'Double Regular Holiday') {
            $model->code = '2rh';
        }

        if ($request->holiday_type == 'Regular Holiday, Special Holiday') {
            $model->code = 'rh-sh';
        }

        $model->save();
    }
}
