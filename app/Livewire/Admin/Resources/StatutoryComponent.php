<?php

namespace App\Livewire\Admin\Resources;

use App\Livewire\Admin\Fields\Boolean;
use App\Livewire\Admin\Fields\Currency;
use App\Livewire\Admin\Fields\Date;
use App\Livewire\Admin\Fields\Number;
use App\Livewire\Admin\Fields\Select;
use App\Livewire\Admin\Fields\Text;
use App\Livewire\Admin\Fields\Textarea;
use App\Livewire\Admin\Resource;
use App\Support\Installation\Installation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Statutory components for the installation's country.
 *
 * A PH installation shows the built-in agencies as read-only reference rows;
 * a generic country edits the rates and bases here.
 */
class StatutoryComponent extends Resource
{
    public static string $model = \App\Models\StatutoryComponent::class;

    public static int $priority = 7;

    public static string $title = 'name';

    public static string $group = 'Payroll';

    public static array $search = ['code', 'name'];

    public static function label(): string
    {
        return 'Statutory components';
    }

    public function title(): string
    {
        return (string) ($this->resource?->name ?? $this->resource?->code ?? '');
    }

    public static function indexQuery(Builder $query): Builder
    {
        return $query
            ->where('country_code', Installation::countryCode())
            ->orderBy('sort_order')
            ->orderBy('code');
    }

    public function fields(Request $request): array
    {
        return [
            Text::make('Code', 'code')
                ->rules('required', 'max:32')
                ->sortable(),

            Text::make('Name', 'name')
                ->rules('required', 'max:128')
                ->sortable(),

            Select::make('Sector', 'sector')
                ->options([
                    'any' => 'Any sector',
                    'government' => 'Government',
                    'private' => 'Private',
                ])
                ->default('any'),

            Select::make('Category', 'category')
                ->options([
                    'contribution' => 'Contribution',
                    'tax' => 'Tax',
                    'levy' => 'Levy',
                    'fund' => 'Fund',
                ])
                ->default('contribution')
                ->rules('required'),

            Select::make('Applies to', 'applies_to')
                ->options([
                    'both' => 'Employee and employer',
                    'employee' => 'Employee only',
                    'employer' => 'Employer only',
                ])
                ->default('both')
                ->rules('required'),

            Select::make('Calculation', 'calculation')
                ->options([
                    'percentage' => 'Percentage of a base',
                    'bracket' => 'Bracket table',
                    'fixed' => 'Fixed amount',
                ])
                ->default('percentage')
                ->rules('required'),

            Select::make('Base', 'base')
                ->options([
                    'monthly_basic' => 'Monthly basic',
                    'monthly_gross' => 'Monthly gross',
                    'period_basic' => 'Period basic',
                    'period_gross' => 'Period gross',
                    'taxable' => 'Taxable income',
                ])
                ->default('monthly_basic')
                ->rules('required'),

            Number::make('Employee rate (%)', 'employee_rate_percent')
                ->step(0.0001)
                ->rules('numeric', 'min:0', 'max:100'),

            Number::make('Employer rate (%)', 'employer_rate_percent')
                ->step(0.0001)
                ->rules('numeric', 'min:0', 'max:100'),

            Currency::make('Employee fixed amount', 'employee_amount')
                ->currency(currency_symbol()),

            Currency::make('Employer fixed amount', 'employer_amount')
                ->currency(currency_symbol()),

            Currency::make('Minimum base', 'min_base')
                ->currency(currency_symbol()),

            Currency::make('Maximum base', 'max_base')
                ->currency(currency_symbol()),

            Currency::make('Maximum employee amount', 'max_employee')
                ->currency(currency_symbol()),

            Currency::make('Maximum employer amount', 'max_employer')
                ->currency(currency_symbol()),

            Select::make('Schedule', 'schedule')
                ->options(payment_schedules())
                ->default('every_pay')
                ->rules('required'),

            Select::make('Rounding', 'rounding')
                ->options([
                    'half_up' => 'Half up',
                    'floor' => 'Floor',
                    'ceil' => 'Ceiling',
                ])
                ->default('half_up'),

            Boolean::make('Reduces taxable income', 'reduces_taxable_income')
                ->default(false),

            Boolean::make('Active', 'is_active')
                ->default(true),

            Number::make('Sort order', 'sort_order')
                ->default(100)
                ->rules('integer', 'min:0'),

            Date::make('Effective from', 'effective_from'),

            Date::make('Effective to', 'effective_to'),

            Text::make('Source URL', 'source_url')
                ->rules('max:512'),

            Textarea::make('Notes', 'notes'),
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
            'company_id' => null,
            'country_code' => Installation::countryCode(),
        ])->save();
    }

    /**
     * The built-in Philippine driver owns its rates, so the reference rows are
     * read-only. A generic country edits its components here.
     */
    public static function authorizedToCreate(): ?bool
    {
        return Installation::statutoryDriver() !== 'ph';
    }

    public static function authorizedToUpdate(Model $model): ?bool
    {
        if (Installation::statutoryDriver() === 'ph') {
            return false;
        }

        return ($model->metadata['managed_by'] ?? null) !== 'ph';
    }

    public static function authorizedToDelete(Model $model): ?bool
    {
        return static::authorizedToUpdate($model);
    }
}
