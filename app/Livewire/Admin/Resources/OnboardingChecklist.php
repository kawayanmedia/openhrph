<?php

namespace App\Livewire\Admin\Resources;

use App\Livewire\Admin\Fields\Badge;
use App\Livewire\Admin\Fields\BelongsTo;
use App\Livewire\Admin\Fields\ID;
use App\Livewire\Admin\Fields\Text;
use App\Livewire\Admin\Resource;
use App\Models\Employee;
use App\Models\Employment as EmploymentModel;
use App\Models\OnboardingChecklist as OnboardingChecklistModel;
use Illuminate\Http\Request;

class OnboardingChecklist extends Resource
{
    public static string $model = OnboardingChecklistModel::class;

    public static string $title = 'name';

    public static int $priority = 64;

    public static string $group = 'Employment';

    public static ?string $sector = 'Private';

    public static array $with = ['employee'];

    public static array $search = ['name'];

    public function title(): string
    {
        return (string) ($this->resource?->name ?? '');
    }

    public function fields(Request $request): array
    {
        return [
            ID::make()->sortable(),
            BelongsTo::make('Employee')
                ->options(Employee::employeeList())
                ->nullable(),
            BelongsTo::make('Employment')
                ->options(EmploymentModel::query()->orderByDesc('id')->limit(300)->pluck('id', 'id')->toArray())
                ->nullable(),
            Text::make('Name')->rules('required', 'max:128'),
            Badge::make('Status')->map([
                'open' => 'info',
                'completed' => 'success',
                'cancelled' => 'default',
            ])->sortable(),
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
