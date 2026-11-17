<?php

namespace App\Services\Payroll\Statutory;

use App\Models\StatutoryComponent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Resolves the effective statutory components for a company and payroll date.
 *
 * Company-specific rows override country defaults with the same code, matching
 * how the statutory tables accept both global and company rows.
 */
final class ComponentResolver
{
    /**
     * @return Collection<int, StatutoryComponent>
     */
    public static function components(
        int $companyId,
        string $countryCode,
        ?string $sector,
        mixed $date = null,
    ): Collection {
        $date = $date instanceof \DateTimeInterface ? Carbon::instance($date) : Carbon::parse($date ?? now());

        $components = StatutoryComponent::query()
            ->with('bands')
            ->where('country_code', strtoupper($countryCode))
            ->where('is_active', true)
            ->effectiveOn($date)
            ->forSector($sector)
            ->where(fn ($query) => $query->whereNull('company_id')->orWhere('company_id', $companyId))
            ->get();

        return $components
            ->groupBy(fn (StatutoryComponent $component): string => strtolower((string) $component->code))
            ->map(function (Collection $group): StatutoryComponent {
                return $group->firstWhere('company_id', '!=', null) ?? $group->first();
            })
            ->sortBy(fn (StatutoryComponent $component): string => sprintf('%05d-%s', (int) $component->sort_order, $component->code))
            ->values();
    }

    /**
     * The resolver output in the shape the PHP payroll engine consumes.
     *
     * @return list<array<string, mixed>>
     */
    public static function definitions(
        int $companyId,
        string $countryCode,
        ?string $sector,
        mixed $date = null,
    ): array {
        return self::components($companyId, $countryCode, $sector, $date)
            ->map(fn (StatutoryComponent $component): array => [
                'code' => (string) $component->code,
                'name' => (string) $component->name,
                'category' => (string) $component->category,
                'applies_to' => (string) $component->applies_to,
                'calculation' => (string) $component->calculation,
                'base' => (string) $component->base,
                'employee_rate' => (float) ($component->employee_rate ?? 0),
                'employer_rate' => (float) ($component->employer_rate ?? 0),
                'employee_amount' => (int) ($component->employee_amount_centavos ?? 0),
                'employer_amount' => (int) ($component->employer_amount_centavos ?? 0),
                'min_base' => $component->min_base_centavos === null ? null : (int) $component->min_base_centavos,
                'max_base' => $component->max_base_centavos === null ? null : (int) $component->max_base_centavos,
                'max_employee' => $component->max_employee_centavos === null ? null : (int) $component->max_employee_centavos,
                'max_employer' => $component->max_employer_centavos === null ? null : (int) $component->max_employer_centavos,
                'rounding' => (string) ($component->rounding ?: 'half_up'),
                'schedule' => (string) ($component->schedule ?: 'every_pay'),
                'reduces_taxable_income' => (bool) $component->reduces_taxable_income,
                'sort_order' => (int) $component->sort_order,
                'bands' => $component->bands->map(fn ($band): array => [
                    'from' => (int) $band->from_centavos,
                    'to' => $band->to_centavos === null ? null : (int) $band->to_centavos,
                    'base' => (int) $band->base_centavos,
                    'employee_rate' => (float) ($band->employee_rate ?? 0),
                    'employer_rate' => (float) ($band->employer_rate ?? 0),
                    'employee_amount' => (int) $band->employee_amount_centavos,
                    'employer_amount' => (int) $band->employer_amount_centavos,
                ])->values()->all(),
            ])
            ->all();
    }

    public static function hasComponents(
        int $companyId,
        string $countryCode,
        ?string $sector,
        mixed $date = null,
    ): bool {
        return self::components($companyId, $countryCode, $sector, $date)->isNotEmpty();
    }
}
