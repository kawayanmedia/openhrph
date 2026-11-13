<?php

namespace App\Livewire\Admin\Resources;

use App\Livewire\Admin\Fields\BelongsTo;
use App\Livewire\Admin\Fields\Text;
use App\Livewire\Admin\Resource;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class Signatory extends Resource
{
    public static string $model = \App\Models\Signatory::class;

    public static int $priority = 3;

    public static string $title = 'id';

    public static string $group = 'Admin';

    public static array $with = ['employee'];

    public static array $search = ['id'];

    public function title(): string
    {
        return (string) ($this->resource?->name ?? '');
    }

    public function fields(Request $request): array
    {
        return [
            BelongsTo::make('Employee', 'employee')
                ->options(Employee::employeeList())
                ->showOnIndex()
                ->rules('required')
                ->placeholder('Search employee')
                ->withoutTrashed()
                ->searchable(),

            Text::make('Phone'),

            Text::make('Mobile'),

            Text::make('Email')
                ->rules('nullable', 'email'),
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
