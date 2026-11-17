<?php

namespace App\Livewire\Admin\Resources;

use App\Livewire\Admin\Fields\ID;
use App\Livewire\Admin\Fields\Text;
use App\Livewire\Admin\Resource;
use Illuminate\Http\Request;

class Device extends Resource
{
    public static string $model = \App\Models\Device::class;

    public static int $priority = 6;

    public static string $title = 'id';

    public static string $group = 'Attendance';

    public static array $search = ['id'];

    public static function label(): string
    {
        return 'Devices';
    }

    public function title(): string
    {
        return (string) ($this->resource?->name ?? '');
    }

    public function fields(Request $request): array
    {
        return [
            ID::make()->sortable(),

            Text::make('Name', 'name')
                ->rules('required')
                ->sortable(),

            Text::make('IP Address', 'ip_address')
                ->rules('required')
                ->sortable(),
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
