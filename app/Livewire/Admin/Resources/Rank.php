<?php

namespace App\Livewire\Admin\Resources;

use App\Livewire\Admin\Fields\ID;
use App\Livewire\Admin\Fields\Text;
use App\Livewire\Admin\Resource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class Rank extends Resource
{
    public static string $model = \App\Models\Rank::class;

    public static int $priority = 6;

    public static string $title = 'id';

    public static string $group = 'Admin';

    public static array $search = ['id', 'name'];

    public function title(): string
    {
        return (string) ($this->resource?->name ?? '');
    }

    public function fields(Request $request): array
    {
        return [
            ID::make()->sortable(),

            Text::make('Rank', 'name')
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

    public static function afterCreate(Request $request, Model $model): void
    {
        $model->company_id = $request->user()->company_id;
        $model->save();
    }
}
