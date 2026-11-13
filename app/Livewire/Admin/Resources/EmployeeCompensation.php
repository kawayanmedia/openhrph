<?php

namespace App\Livewire\Admin\Resources;

use App\Livewire\Admin\Fields\BelongsTo;
use App\Livewire\Admin\Fields\Boolean;
use App\Livewire\Admin\Fields\Currency;
use App\Livewire\Admin\Fields\Date;
use App\Livewire\Admin\Fields\Heading;
use App\Livewire\Admin\Fields\ID;
use App\Livewire\Admin\Fields\Select;
use App\Livewire\Admin\Fields\Text;
use App\Livewire\Admin\Resource;
use App\Livewire\Admin\Support\FormData;
use App\Models\Compensation;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class EmployeeCompensation extends Resource
{
    public static string $model = \App\Models\EmployeeCompensation::class;

    public static int $priority = 6;

    public static string $title = 'id';

    public static array $with = ['employee', 'compensation'];

    public static string $group = 'Payroll';

    public static array $search = ['id'];

    public function fields(Request $request): array
    {
        return array_merge([
            ID::make()->sortable(),

            BelongsTo::make('Employee')
                ->rules('required')
                ->showOnIndex()
                ->searchable()
                ->options(Employee::employeeList()),

            BelongsTo::make('Compensation')
                ->rules('required')
                ->searchable()
                ->hideFromIndex()
                ->onlyOnForms()
                ->options(Compensation::orderBy('name')->pluck('name', 'id')->toArray()),

            Text::make('Compensation', function () {
                return $this->compensation?->name;
            })->onlyOnIndex(),

            Boolean::make('Special Pay Run?', 'is_special')
                ->hideFromIndex()
                ->default(false),

            Text::make('Release Date', function () {
                if ($this->release_date) {
                    return $this->release_date?->format(date_ft());
                }

                return $this->frequency;
            })->onlyOnIndex(),

            Date::make('Release date', 'release_date')
                ->readonly()
                ->onlyOnForms()
                ->dependsOn(['is_special'], function (Date $field, Request $request, FormData $formData) {
                    if ($formData->is_special == true) {
                        $field->readonly = false;
                        $field->rules('required');
                    }
                }),

            Currency::make('Amount')
                ->rules('required')
                ->sortable()
                ->currency(currency_symbol()),

            Boolean::make('Taxable', 'is_taxable')
                ->default(true),

            Boolean::make('Active', 'is_active')
                ->default(true),
        ], [
            Heading::make('Recurring'),
        ], $this->recurringFields(), [
            Heading::make('Prorated'),
        ], $this->proratedFields());
    }

    protected function recurringFields(): array
    {
        return [
            Boolean::make('Recurring', 'is_recurring')
                ->default(false)
                ->dependsOn(['is_special'], function (Boolean $field, Request $request, FormData $formData) {
                    if ($formData->is_special == true) {
                        $field->readonly = true;
                    }
                }),

            Select::make('Frequency', 'frequency')
                ->readonly()
                ->options(payment_schedules())
                ->sortable()
                ->hideFromIndex()
                ->onlyOnForms()
                ->dependsOn(['is_recurring', 'is_special'], function (Select $field, Request $request, FormData $formData) {
                    if ($formData->is_recurring == true) {
                        $field->readonly = false;
                        $field->rules('required');
                    }

                    if ($formData->is_special == true) {
                        $field->readonly = true;
                    }
                }),

            Date::make('Valid from Date', 'valid_date_from')
                ->readonly()
                ->hideFromIndex()
                ->dependsOn(['is_recurring', 'is_special'], function (Date $field, Request $request, FormData $formData) {
                    if ($formData->is_recurring == true) {
                        $field->readonly = false;
                        $field->rules('required');
                    }

                    if ($formData->is_special == true) {
                        $field->readonly = true;
                    }
                }),

            Date::make('Valid until Date', 'valid_date_to')
                ->readonly()
                ->hideFromIndex()
                ->dependsOn(['is_recurring', 'is_never_expires', 'is_special'], function (Date $field, Request $request, FormData $formData) {
                    if ($formData->is_recurring == true) {
                        $field->readonly = false;
                        $field->rules('required');
                    }

                    if ($formData->is_never_expires == true) {
                        $field->readonly = true;
                        $field->rules();
                    }

                    if ($formData->is_special == true) {
                        $field->readonly = true;
                    }
                }),

            Text::make('Validity', function () {
                if ($this->is_never_expires) {
                    return $this->valid_date_from?->format(date_ft()).'(Never Expires)';
                }

                return $this->valid_date_from?->format(date_ft()).' - '.$this->valid_date_to?->format(date_ft());
            })->onlyOnIndex(),

            Boolean::make('Never expires', 'is_never_expires')
                ->readonly()
                ->hideFromIndex()
                ->default(false)
                ->dependsOn(['is_recurring', 'is_special'], function (Boolean $field, Request $request, FormData $formData) {
                    if ($formData->is_recurring == true) {
                        $field->readonly = false;
                    }

                    if ($formData->is_special == true) {
                        $field->readonly = true;
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
                    if ($formData->is_recurring == true) {
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
                    if ($formData->is_prorated == true) {
                        $field->readonly = false;
                    }
                }),

            Boolean::make('On paid leave', 'on_paid_leave')
                ->hideFromIndex()
                ->default(false)
                ->readonly()
                ->dependsOn(['is_prorated'], function (Boolean $field, Request $request, FormData $formData) {
                    if ($formData->is_prorated == true) {
                        $field->default = false;
                        $field->readonly = false;
                    }
                }),

            Boolean::make('On unpaid leave', 'on_unpaid_leave')
                ->hideFromIndex()
                ->default(false)
                ->readonly()
                ->dependsOn(['is_prorated'], function (Boolean $field, Request $request, FormData $formData) {
                    if ($formData->is_prorated == true) {
                        $field->default = false;
                        $field->readonly = false;
                    }
                }),
        ];
    }

    public function cards(Request $request): array
    {
        // NovaBackButton is not applicable to the Livewire admin engine.
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

    public static function afterCreate(Request $request, Model $model): void
    {
        $model->is_taxable = $model->compensation->is_taxable;
        $model->save();
    }

    public static function afterUpdate(Request $request, Model $model): void
    {
        $model->is_taxable = $model->compensation->is_taxable;
        $model->save();
    }
}
