<?php

namespace App\Livewire\Admin\Resources;

use App\Livewire\Admin\Fields\ID;
use App\Livewire\Admin\Fields\Text;
use App\Livewire\Admin\Resource;
use App\Models\Employee;
use Illuminate\Http\Request;

class ImmediateHead extends Resource
{
    public static string $model = Employee::class;

    public static string $title = 'id';

    public static string $group = 'Admin';

    public static array $search = ['id', 'last_name', 'first_name', 'middle_name'];

    public function title(): string
    {
        $resource = $this->resource;

        return $resource ? $resource->last_name.', '.$resource->first_name : '';
    }

    public function fields(Request $request): array
    {
        return [
            ID::make()->sortable(),

            Text::make('Employee Number', 'employee_number')->onlyOnIndex(),

            Text::make('Name', function () {
                return $this->fullname;
            })->onlyOnIndex(),
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
}
