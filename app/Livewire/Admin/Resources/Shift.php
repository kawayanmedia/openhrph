<?php

namespace App\Livewire\Admin\Resources;

use App\Livewire\Admin\Fields\Number;
use App\Livewire\Admin\Fields\Text;
use App\Livewire\Admin\Resource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class Shift extends Resource
{
    public static string $model = \App\Models\Shift::class;

    public static int $priority = 6;

    public static string $title = 'id';

    public static string $group = 'Attendance';

    public static array $search = [];

    public static function label(): string
    {
        return 'Shifts';
    }

    public function title(): string
    {
        return (string) ($this->resource?->name ?? '');
    }

    public function fields(Request $request): array
    {
        return [
            Text::make('Name', 'name')
                ->rules('required')
                ->sortable(),

            Number::make('Grace Period', 'grace_period')
                ->rules('required')
                ->help('Minutes')
                ->sortable(),

            Number::make('Start Deducting', 'start_deducting')
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

    public static function afterCreate(Request $request, Model $model): void
    {
        $model->company_id = $request->user()->company_id;
        $model->save();
    }
}
