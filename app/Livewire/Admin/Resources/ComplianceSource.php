<?php

namespace App\Livewire\Admin\Resources;

use App\Livewire\Admin\Fields\Date;
use App\Livewire\Admin\Fields\ID;
use App\Livewire\Admin\Fields\Text;
use App\Livewire\Admin\Fields\Textarea;
use App\Livewire\Admin\Resource;
use App\Models\ComplianceSource as ComplianceSourceModel;
use Illuminate\Http\Request;

class ComplianceSource extends Resource
{
    public static string $model = ComplianceSourceModel::class;

    public static string $title = 'title';

    public static int $priority = 95;

    public static string $group = 'Compliance';

    public static array $search = ['authority', 'title', 'issuance_number'];

    public function title(): string
    {
        return (string) ($this->resource?->title ?? '');
    }

    public function fields(Request $request): array
    {
        return [
            ID::make()->sortable(),
            Text::make('Authority')->rules('required', 'max:128')->sortable(),
            Text::make('Title')->rules('required', 'max:191'),
            Text::make('Issuance Number', 'issuance_number')->rules('nullable', 'max:96'),
            Text::make('Official URL', 'official_url')->rules('nullable', 'url', 'max:255')->hideFromIndex(),
            Date::make('Published At', 'published_at'),
            Date::make('Effective From', 'effective_from'),
            Date::make('Effective Until', 'effective_until'),
            Textarea::make('Notes')->hideFromIndex(),
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
