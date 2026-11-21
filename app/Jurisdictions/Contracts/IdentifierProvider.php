<?php

namespace App\Jurisdictions\Contracts;

/**
 * Country-specific employee and company identifiers.
 */
interface IdentifierProvider
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function employeeFields(): array;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function companyFields(): array;

    /**
     * @return array<int, string>
     */
    public function required(): array;
}
