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
use App\Models\Allowance;
use Illuminate\Http\Request;

class EmployeeAllowance extends Resource
{
    public static string $model = \App\Models\EmployeeAllowance::class;

    public static int $priority = 6;

    public static string $title = 'id';

    public static string $group = 'Payroll';

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

            BelongsTo::make('Allowance', 'allowance')
                ->options(Allowance::orderBy('name')->pluck('name', 'id')->toArray())
                ->rules('required')
                ->withoutTrashed()
                ->hideFromIndex()
                ->onlyOnForms(),

            Text::make('Allowance', function () {
                return $this->allowance->name;
            })
                ->onlyOnIndex(),

            Boolean::make('Special Pay Run?', 'is_special')
                ->hideFromIndex()
                ->default(false),

            Date::make('Release date', 'release_date')
                ->readonly()
                ->dependsOn(['is_special'], function (Date $field, Request $request, FormData $formData) {
                    if ($formData->is_special === true) {
                        $field->readonly = false;
                    }
                }),

            Currency::make('Amount')
                ->rules('required')
                ->sortable(),

            ...$this->recurringFields(),

            ...$this->proratedFields(),
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
                ->options(frequency())
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
                ->dependsOn(['is_recurring', 'is_special'], function (Date $field, Request $request, FormData $formData) {
                    if ($formData->is_recurring === true) {
                        $field->readonly = false;
                        $field->rules('required');
                    }

                    if ($formData->is_special === true) {
                        $field->readonly = true;
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

            Boolean::make('Never expires', 'is_never_expires')
                ->readonly()
                ->hideFromIndex()
                ->default(false)
                ->dependsOn(['is_recurring'], function (Boolean $field, Request $request, FormData $formData) {
                    if ($formData->is_recurring === true) {
                        $field->readonly = false;
                    }
                }),
        ];
    }

    protected function proratedFields(): array
    {
        return [
            Boolean::make('Prorated', 'is_prorated')
                ->readonly()
                ->hideFromIndex()
                ->default(false)
                ->dependsOn(['is_recurring'], function (Boolean $field, Request $request, FormData $formData) {
                    if ($formData->is_recurring === true) {
                        $field->readonly = false;
                    }
                }),

            Select::make('Prorated by', 'prorated_by')
                ->readonly()
                ->options(prorated_by())
                ->sortable()
                ->hideFromIndex()
                ->onlyOnForms()
                ->dependsOn(['is_prorated'], function (Select $field, Request $request, FormData $formData) {
                    if ($formData->is_prorated === true) {
                        $field->readonly = false;
                    }
                }),

            Boolean::make('On paid leave', 'on_paid_leave')
                ->hideFromIndex()
                ->default(false)
                ->readonly()
                ->dependsOn(['is_prorated'], function (Boolean $field, Request $request, FormData $formData) {
                    if ($formData->is_prorated === true) {
                        $field->default = false;
                        $field->readonly = false;
                    }
                }),

            Boolean::make('On unpaid leave', 'on_unpaid_leave')
                ->hideFromIndex()
                ->default(false)
                ->readonly()
                ->dependsOn(['is_prorated'], function (Boolean $field, Request $request, FormData $formData) {
                    if ($formData->is_prorated === true) {
                        $field->default = false;
                        $field->readonly = false;
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
