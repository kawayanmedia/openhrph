<?php

namespace App\Livewire\Admin\Resources;

use App\Livewire\Admin\Fields\BelongsTo;
use App\Livewire\Admin\Fields\Boolean;
use App\Livewire\Admin\Fields\Currency;
use App\Livewire\Admin\Fields\Date;
use App\Livewire\Admin\Fields\ID;
use App\Livewire\Admin\Fields\Select;
use App\Livewire\Admin\Fields\Text;
use App\Livewire\Admin\Resource;
use App\Livewire\Admin\Support\FormData;
use App\Models\Deduction;
use Illuminate\Http\Request;

class EmployeeDeduction extends Resource
{
    public static string $model = \App\Models\EmployeeDeduction::class;

    public static int $priority = 6;

    public static string $title = 'id';

    public static string $group = 'Admin';

    public static array $with = ['employee'];

    public static array $search = ['id'];

    public function title(): string
    {
        return (string) ($this->resource?->name ?? '');
    }

    public function fields(Request $request): array
    {
        return [
            ID::make()->sortable(),

            Text::make('Employee', function () {
                return $this->employee?->fullname;
            })->onlyOnIndex(),

            BelongsTo::make('Deduction', 'deduction')
                ->options(Deduction::orderBy('name')->pluck('name', 'id')->toArray())
                ->rules('required')
                ->withoutTrashed()
                ->hideFromIndex()
                ->onlyOnForms(),

            Text::make('Deduction', function () {
                if ($this->deduction == null) {
                    return;
                }

                return $this->deduction->name;
            })
                ->onlyOnIndex(),

            Currency::make('Amount')
                ->rules('required')
                ->sortable(),

            ...$this->recurringFields(),
        ];
    }

    protected function recurringFields(): array
    {
        return [
            Boolean::make('Recurring', 'is_recurring')
                ->default(false)
                ->dependsOn(['is_special'], function (Boolean $field, Request $request, FormData $formData) {
                    if ($formData->is_special === true) {
                        $field->readonly = true;
                    }
                }),

            Select::make('Frequency', 'frequency')
                ->readonly()
                ->options(payment_schemes())
                ->sortable()
                ->hideFromIndex()
                ->onlyOnForms()
                ->dependsOn(['is_recurring'], function (Select $field, Request $request, FormData $formData) {
                    if ($formData->is_recurring === true) {
                        $field->readonly = false;
                        $field->rules('required');
                    }
                }),

            Date::make('Valid from Date', 'valid_date_from')
                ->dependsOn(['is_recurring'], function (Date $field, Request $request, FormData $formData) {
                    if ($formData->is_recurring === true) {
                        $field->readonly = false;
                        $field->rules('required');
                    }
                }),

            Date::make('Valid until Date', 'valid_date_to')
                ->readonly()
                ->dependsOn(['is_recurring'], function (Date $field, Request $request, FormData $formData) {
                    if ($formData->is_recurring === true) {
                        $field->readonly = false;
                        $field->rules('required');
                    }
                }),
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
