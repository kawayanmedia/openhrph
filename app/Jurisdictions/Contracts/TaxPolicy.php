<?php

namespace App\Jurisdictions\Contracts;

/**
 * Country withholding tax and benefit-exclusion rules.
 */
interface TaxPolicy
{
    public function code(): string;

    public function benefitExclusionCentavos(): int;

    public function minimumWageExempt(): bool;

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function withholding(array $context): array;

    public function supportsAnnualAdjustment(): bool;
}
