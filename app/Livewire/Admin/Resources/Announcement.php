<?php

namespace App\Livewire\Admin\Resources;

use App\Livewire\Admin\Fields\Boolean;
use App\Livewire\Admin\Fields\DateTime;
use App\Livewire\Admin\Fields\ID;
use App\Livewire\Admin\Fields\Select;
use App\Livewire\Admin\Fields\Text;
use App\Livewire\Admin\Fields\Trix;
use App\Livewire\Admin\Resource;
use Illuminate\Http\Request;

class Announcement extends Resource
{
    public static string $model = \App\Models\Announcement::class;

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

            Text::make('Title', 'title')
                ->sortable()
                ->rules('required'),

            DateTime::make('Date & Time', 'date_time')->hideFromIndex(),

            Trix::make('Content', 'content')
                ->sortable()
                ->rules('required'),

            Select::make('Priority', 'priority')
                ->options([
                    '4' => 'Low',
                    '3' => 'Medium',
                    '2' => 'High',
                    '1' => 'Urgent',
                ])
                ->sortable()
                ->displayUsingLabels()
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
