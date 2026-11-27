<?php

namespace App\Livewire\Admin\Resources;

use App\Livewire\Admin\Fields\Boolean;
use App\Livewire\Admin\Fields\Date;
use App\Livewire\Admin\Fields\ID;
use App\Livewire\Admin\Fields\Number;
use App\Livewire\Admin\Fields\Select;
use App\Livewire\Admin\Fields\Text;
use App\Livewire\Admin\Fields\Textarea;
use App\Livewire\Admin\Resource;
use App\Models\WorkflowDefinition as WorkflowDefinitionModel;
use App\Services\Workflow\WorkflowDefinitionValidator;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class WorkflowDefinition extends Resource
{
    public static string $model = WorkflowDefinitionModel::class;

    public static int $priority = 41;

    public static string $group = 'HR';

    public static array $search = ['id', 'code', 'name'];

    public function title(): string
    {
        return (string) ($this->resource?->name ?? '');
    }

    public function fields(Request $request): array
    {
        return [
            ID::make()->sortable(),

            Text::make('Code')->rules('required', 'max:64')->sortable(),
            Text::make('Name')->rules('required', 'max:128')->sortable(),

            Text::make('Subject Type', 'subject_type')
                ->rules('nullable', 'max:255')
                ->help('Optional morph class, e.g. App\\Models\\EmploymentChange.')
                ->hideFromIndex(),

            Select::make('Sector')
                ->options(['Private' => 'Private', 'Government' => 'Government'])
                ->nullable()
                ->hideFromIndex(),

            Number::make('Version')->min(1)->default(1)->sortable(),

            Textarea::make('Configuration')
                ->rules('required')
                ->help('JSON steps. Example: {"steps":[{"key":"review","name":"Review","mode":"all","due_days":3,"assignees":[{"type":"group","value":"HR"}]}]}')
                ->resolveUsing(fn ($value) => is_array($value)
                    ? json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
                    : $value)
                ->fillUsing(function ($model, $attribute, $value): void {
                    $configuration = is_string($value) ? json_decode($value, true) : $value;

                    if (! is_array($configuration)) {
                        throw ValidationException::withMessages([
                            'configuration' => 'The configuration must be valid JSON.',
                        ]);
                    }

                    app(WorkflowDefinitionValidator::class)->assertValid($configuration);

                    $model->setAttribute($attribute, $configuration);
                }),

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
