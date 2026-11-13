<?php

namespace App\Livewire\Admin\Resources\Government\Monthly;

use App\Livewire\Admin\Fields\Boolean;
use App\Livewire\Admin\Fields\ID;
use App\Livewire\Admin\Fields\Text;
use App\Livewire\Admin\Resource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class Bir extends Resource
{
    public static string $model = \App\Models\Leave::class;

    public static int $priority = 6;

    public static string $title = 'id';

    public static string $group = 'Leave Management';

    public static array $search = ['id'];

    public static function label(): string
    {
        return 'BIR 1601C';
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

            Text::make('Code', 'code')
                ->sortable(),

            Boolean::make('Requires leave credit?', 'is_leave_credits')->default(false),

            Boolean::make('Payable?', 'is_payable')->default(false),
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
