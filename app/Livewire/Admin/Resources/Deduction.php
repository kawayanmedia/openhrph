<?php

namespace App\Livewire\Admin\Resources;

use App\Livewire\Admin\Fields\Boolean;
use App\Livewire\Admin\Fields\Currency;
use App\Livewire\Admin\Fields\ID;
use App\Livewire\Admin\Fields\Number;
use App\Livewire\Admin\Fields\Select;
use App\Livewire\Admin\Fields\Text;
use App\Livewire\Admin\Resource;
use App\Livewire\Admin\Support\FormData;
use App\Support\Installation\Installation;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class Deduction extends Resource
{
    public static string $model = \App\Models\Deduction::class;

    public static int $priority = 5;

    public static string $title = 'id';

    public static string $group = 'Payroll';

    public static array $search = ['id', 'name', 'code'];

    public static function indexQuery(Builder $query): Builder
    {
        $tenantId = app(TenantContext::class)->id();

        return $tenantId === null ? $query : $query->where('company_id', $tenantId);
    }

    public function title(): string
    {
        return (string) ($this->resource?->name ?? '');
    }

    public function fields(Request $request): array
    {
        return [
            ID::make()->sortable(),

            Text::make('Code', 'code')
                ->rules('max:32')
                ->sortable(),

            Text::make('Name', 'name')
                ->rules('required')
                ->sortable(),

            Select::make('Type', 'type')
                ->options([
                    'voluntary' => 'Voluntary',
                    'statutory' => 'Statutory',
                    'loan' => 'Loan',
                ])
                ->default('voluntary')
                ->rules('required'),

            Select::make('Calculation', 'calculation')
                ->options([
                    'fixed' => 'Fixed amount',
                    'percentage' => 'Percentage of a base',
                ])
                ->help('A percentage deduction ignores the amount on the employee assignment and uses the rate and base here.')
                ->default('fixed')
                ->rules('required'),

            Currency::make('Default amount', 'amount')
                ->currency(currency_symbol()),

            Number::make('Rate (%)', 'rate_percent')
                ->step(0.0001)
                ->help('Only used by percentage deductions.')
                ->rules('nullable', 'numeric', 'min:0', 'max:100')
                ->dependsOn(['calculation'], function (Number $field, Request $request, FormData $formData) {
                    if ($formData->calculation === 'percentage') {
                        $field->rules('required', 'numeric', 'min:0', 'max:100');
                    }
                }),

            Select::make('Base', 'base')
                ->options([
                    'period_basic' => 'Period basic',
                    'monthly_basic' => 'Monthly basic',
                    'period_gross' => 'Period gross',
                    'monthly_gross' => 'Monthly gross',
                    'taxable' => 'Taxable income',
                ])
                ->dependsOn(['calculation'], function (Select $field, Request $request, FormData $formData) {
                    if ($formData->calculation === 'percentage') {
                        $field->rules('required');
                    }
                }),

            Boolean::make('Reduces taxable income', 'reduces_taxable_income')
                ->default(false),

            Boolean::make('Active', 'is_active')
                ->default(true),

            Number::make('Sort order', 'sort_order')
                ->default(0)
                ->rules('integer', 'min:0'),
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

    public static function afterCreate(Request $request, Model $model): void
    {
        $model->forceFill([
            'company_id' => $request->user()->company_id,
            'country_code' => Installation::countryCode(),
        ])->save();
    }
}
