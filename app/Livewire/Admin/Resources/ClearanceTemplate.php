<?php

namespace App\Livewire\Admin\Resources;

use App\Livewire\Admin\Fields\Boolean;
use App\Livewire\Admin\Fields\ID;
use App\Livewire\Admin\Fields\Number;
use App\Livewire\Admin\Fields\Text;
use App\Livewire\Admin\Fields\Textarea;
use App\Livewire\Admin\Resource;
use App\Models\ClearanceTemplate as ClearanceTemplateModel;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ClearanceTemplate extends Resource
{
    public static string $model = ClearanceTemplateModel::class;

    public static string $title = 'name';

    public static int $priority = 71;

    public static string $group = 'Separation';

    public static ?string $sector = 'Private';

    public static array $search = ['name'];

    public function title(): string
    {
        return (string) ($this->resource?->name ?? '');
    }

    public function fields(Request $request): array
    {
        return [
            ID::make()->sortable(),
            Text::make('Name')->rules('required', 'max:128'),
            Number::make('Version')->min(1)->default(1),
            Textarea::make('Tasks')
                ->rules('required')
                ->help('[{"unit":"HR","task":"Return records"},{"unit":"IT","task":"Disable access"}]')
                ->resolveUsing(fn ($value) => is_array($value)
                    ? json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
                    : $value)
                ->fillUsing(function ($model, $attribute, $value): void {
                    $tasks = is_string($value) ? json_decode($value, true) : $value;

                    if (! is_array($tasks)) {
                        throw ValidationException::withMessages([
                            'tasks' => 'Tasks must be a JSON array.',
                        ]);
                    }

                    $model->setAttribute($attribute, $tasks);
                }),
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
