<?php

namespace App\Livewire\Admin\Resources;

use App\Livewire\Admin\Fields\BelongsTo;
use App\Livewire\Admin\Fields\Currency;
use App\Livewire\Admin\Fields\Number;
use App\Livewire\Admin\Resource;
use App\Support\Installation\Installation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Bracket rows for a statutory component.
 *
 * A band charges a flat `base` plus a rate on the amount above `from`. Leave
 * the rates empty for a fixed-band contribution such as a government fund.
 */
class StatutoryComponentBand extends Resource
{
    public static string $model = \App\Models\StatutoryComponentBand::class;

    public static int $priority = 8;

    public static string $title = 'from_centavos';

    public static string $group = 'Payroll';

    public static array $search = ['code'];

    public static function label(): string
    {
        return 'Statutory bracket rows';
    }

    public function title(): string
    {
        return (string) ($this->resource?->from_amount ?? $this->resource?->getKey() ?? '');
    }

    public static function indexQuery(Builder $query): Builder
    {
        return $query
            ->whereHas('component', fn (Builder $query) => $query->where('country_code', Installation::countryCode()))
            ->orderBy('statutory_component_id')
            ->orderBy('from_centavos');
    }

    public function fields(Request $request): array
    {
        return [
            BelongsTo::make('Component', 'component')
                ->resource(StatutoryComponent::class)
                ->rules('required'),

            Currency::make('From', 'from_amount')
                ->currency(currency_symbol())
                ->rules('required'),

            Currency::make('To (leave empty for no ceiling)', 'to_amount')
                ->currency(currency_symbol()),

            Currency::make('Flat base amount', 'base_amount')
                ->currency(currency_symbol()),

            Number::make('Employee rate (%) on the excess', 'employee_rate_percent')
                ->step(0.0001)
                ->rules('numeric', 'min:0', 'max:100'),

            Number::make('Employer rate (%) on the excess', 'employer_rate_percent')
                ->step(0.0001)
                ->rules('numeric', 'min:0', 'max:100'),

            Currency::make('Employee fixed amount', 'employee_amount')
                ->currency(currency_symbol()),

            Currency::make('Employer fixed amount', 'employer_amount')
                ->currency(currency_symbol()),

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

    public static function authorizedToCreate(): ?bool
    {
        return Installation::statutoryDriver() !== 'ph';
    }
}
