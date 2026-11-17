<?php

namespace App\Livewire\Admin\Resources;

use App\Livewire\Admin\Fields\ID;
use App\Livewire\Admin\Fields\Text;
use App\Livewire\Admin\Resource;
use Illuminate\Http\Request;

class Disbursement extends Resource
{
    public static string $model = \App\Models\Disbursement::class;

    public static int $priority = 2;

    public static string $title = 'id';

    public static string $group = 'Admin';

    public static array $search = ['id', 'name'];

    public static function label(): string
    {
        return 'Disbursement';
    }

    public function title(): string
    {
        return (string) ($this->resource?->name ?? '');
    }

    public function fields(Request $request): array
    {
        return [
            ID::make()->sortable(),

            Text::make('Code')
                ->sortable(),

            Text::make('Name')
                ->rules('required')
                ->sortable(),

            Text::make('Account Number', 'account_number')
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
