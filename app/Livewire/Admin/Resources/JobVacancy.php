<?php

namespace App\Livewire\Admin\Resources;

use App\Livewire\Admin\Fields\Boolean;
use App\Livewire\Admin\Fields\ID;
use App\Livewire\Admin\Fields\Text;
use App\Livewire\Admin\Fields\Trix;
use App\Livewire\Admin\Resource;
use Illuminate\Http\Request;

class JobVacancy extends Resource
{
    public static string $model = \App\Models\JobVacancy::class;

    public static int $priority = 4;

    public static string $title = 'title';

    public static string $group = 'Company';

    public static array $search = ['id'];

    public function title(): string
    {
        return (string) ($this->resource?->title ?? '');
    }

    public function fields(Request $request): array
    {
        return [
            ID::make()->sortable(),

            Text::make('Job Title', 'title')
                ->rules('required'),

            Text::make('Job Type', 'type')
                ->rules('required'),

            Trix::make('Position Summary', 'summary')
                ->rules('required'),

            Text::make('Salary', 'salary')
                ->rules('required'),

            Text::make('Shift and Schedule', 'shift')
                ->rules('required'),

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
