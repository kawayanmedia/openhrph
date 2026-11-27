<?php

namespace App\Livewire\Admin\Resources;

use App\Livewire\Admin\Actions\Workforce\ActivatePerformanceCycle;
use App\Livewire\Admin\Actions\Workforce\StartPerformanceReview;
use App\Livewire\Admin\Fields\Badge;
use App\Livewire\Admin\Fields\Date;
use App\Livewire\Admin\Fields\ID;
use App\Livewire\Admin\Fields\Select;
use App\Livewire\Admin\Fields\Text;
use App\Livewire\Admin\Resource;
use App\Models\PerformanceCycle as PerformanceCycleModel;
use Illuminate\Http\Request;

class PerformanceCycle extends Resource
{
    public static string $model = PerformanceCycleModel::class;

    public static string $title = 'name';

    public static int $priority = 82;

    public static string $group = 'Talent';

    public static array $search = ['name', 'template_version'];

    public function title(): string
    {
        return (string) ($this->resource?->name ?? '');
    }

    public function fields(Request $request): array
    {
        return [
            ID::make()->sortable(),
            Text::make('Name')->rules('required', 'max:128')->sortable(),
            Select::make('Sector')
                ->options(['Private' => 'Private', 'Government' => 'Government'])
                ->default('Private'),
            Text::make('Template Version', 'template_version')->rules('nullable', 'max:64'),
            Date::make('Period Start', 'period_start')->rules('required'),
            Date::make('Period End', 'period_end')->rules('required'),
            Badge::make('Status')->map([
                PerformanceCycleModel::STATUS_DRAFT => 'default',
                PerformanceCycleModel::STATUS_ACTIVE => 'success',
                PerformanceCycleModel::STATUS_CALIBRATION => 'warning',
                PerformanceCycleModel::STATUS_COMPLETED => 'success',
                PerformanceCycleModel::STATUS_CANCELLED => 'danger',
            ])->sortable(),
        ];
    }

    public function actions(Request $request): array
    {
        return [new ActivatePerformanceCycle, new StartPerformanceReview];
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
}
