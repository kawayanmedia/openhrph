<?php

namespace App\Jurisdictions\Generic;

use App\Jurisdictions\Contracts\IdentifierProvider;

final class GenericIdentifierProvider implements IdentifierProvider
{
    /**
     * @param  array<string, mixed>  $profile
     */
    public function __construct(private readonly array $profile = []) {}

    public function employeeFields(): array
    {
        return array_values((array) ($this->profile['identifiers']['employee'] ?? []));
    }

    public function companyFields(): array
    {
        return array_values((array) ($this->profile['identifiers']['company'] ?? []));
    }

    public function required(): array
    {
        return array_values(array_map('strval', (array) ($this->profile['identifiers']['required'] ?? [])));
    }
}
