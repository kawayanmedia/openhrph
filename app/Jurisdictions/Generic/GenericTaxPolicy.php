<?php

namespace App\Jurisdictions\Generic;

use App\Jurisdictions\Contracts\TaxPolicy;

/**
 * Tax rules for a table-driven country.
 */
final class GenericTaxPolicy implements TaxPolicy
{
    /**
     * @param  array<string, mixed>  $profile
     */
    public function __construct(private readonly array $profile = []) {}

    public function code(): string
    {
        return 'generic';
    }

    public function benefitExclusionCentavos(): int
    {
        return (int) ($this->profile['tax']['benefit_exclusion_centavos'] ?? 0);
    }

    public function minimumWageExempt(): bool
    {
        return (bool) ($this->profile['tax']['minimum_wage_exempt'] ?? false);
    }

    public function withholding(array $context): array
    {
        return [
            'policy' => $this->code(),
            'components' => array_values(array_filter(
                (array) ($context['components'] ?? []),
                static fn (mixed $component): bool => is_array($component)
                    && strtolower((string) ($component['category'] ?? '')) === 'tax',
            )),
        ];
    }

    public function supportsAnnualAdjustment(): bool
    {
        return (bool) ($this->profile['tax']['annual_adjustment'] ?? true);
    }
}
