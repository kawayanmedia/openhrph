<?php

namespace App\Jurisdictions\Philippines\Tax;

use App\Jurisdictions\Contracts\TaxPolicy;

/**
 * BIR withholding and benefit rules.
 */
final class PhilippinesTaxPolicy implements TaxPolicy
{
    /**
     * RA 10963: the combined 13th-month pay and other benefits exclusion.
     */
    public const OTHER_BENEFITS_EXCLUSION_CENTAVOS = 9_000_000;

    public function code(): string
    {
        return 'ph';
    }

    public function benefitExclusionCentavos(): int
    {
        return self::OTHER_BENEFITS_EXCLUSION_CENTAVOS;
    }

    public function minimumWageExempt(): bool
    {
        return true;
    }

    public function withholding(array $context): array
    {
        return [
            'policy' => $this->code(),
            'frequency' => (string) ($context['frequency'] ?? 'monthly'),
            'taxable_income' => (int) ($context['taxable_income'] ?? 0),
            'tax_brackets' => array_values((array) ($context['tax_brackets'] ?? [])),
        ];
    }

    public function supportsAnnualAdjustment(): bool
    {
        return true;
    }
}
