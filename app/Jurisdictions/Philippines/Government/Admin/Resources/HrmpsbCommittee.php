<?php

namespace App\Jurisdictions\Philippines\Government\Admin\Resources;

use App\Jurisdictions\Philippines\Government\Models\HrmpsbCommittee as HrmpsbCommitteeModel;
use App\Livewire\Admin\Fields\Boolean;
use App\Livewire\Admin\Fields\Date;
use App\Livewire\Admin\Fields\ID;
use App\Livewire\Admin\Fields\Text;
use App\Livewire\Admin\Resource;
use Illuminate\Http\Request;

class HrmpsbCommittee extends Resource
{
    public static string $model = HrmpsbCommitteeModel::class;

    public static string $title = 'name';

    public static int $priority = 27;

    public static string $group = 'Government';

    public static ?string $sector = 'Government';

    public static ?string $jurisdiction = 'PH';

    public static array $search = ['name', 'reference_number'];

    public function title(): string
    {
        return (string) ($this->resource?->name ?? '');
    }

    public function fields(Request $request): array
    {
        return [
            ID::make()->sortable(),
            Text::make('Name')->rules('required', 'max:128')->sortable(),
            Text::make('Reference Number', 'reference_number')->rules('nullable', 'max:96'),
            Date::make('Effective From', 'effective_from'),
            Date::make('Effective Until', 'effective_until'),
            Boolean::make('Active', 'is_active')->default(true),
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
