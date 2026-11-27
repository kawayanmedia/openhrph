<?php

namespace App\Livewire\Admin\Resources;

use App\Livewire\Admin\Fields\Boolean;
use App\Livewire\Admin\Fields\ID;
use App\Livewire\Admin\Fields\Number;
use App\Livewire\Admin\Fields\Text;
use App\Livewire\Admin\Fields\Textarea;
use App\Livewire\Admin\Resource;
use App\Models\LearningCourse as TrainingCourseModel;
use Illuminate\Http\Request;

class TrainingCourse extends Resource
{
    public static string $model = TrainingCourseModel::class;

    public static string $title = 'title';

    public static int $priority = 87;

    public static string $group = 'Talent';

    public static array $search = ['code', 'title', 'provider', 'category'];

    public function title(): string
    {
        return (string) ($this->resource?->title ?? '');
    }

    public function fields(Request $request): array
    {
        return [
            ID::make()->sortable(),
            Text::make('Code')->rules('required', 'max:64')->sortable(),
            Text::make('Title')->rules('required', 'max:191')->sortable(),
            Text::make('Provider')->rules('nullable', 'max:191'),
            Text::make('Category')->rules('nullable', 'max:64'),
            Textarea::make('Description')->hideFromIndex(),
            Number::make('Duration Hours', 'duration_hours')->min(0),
            Number::make('Cost')->min(0),
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
