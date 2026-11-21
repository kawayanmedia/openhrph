<?php

namespace App\Jurisdictions\Generic;

use App\Jurisdictions\Contracts\SettingsProvider;

final class GenericSettingsProvider implements SettingsProvider
{
    /**
     * @param  array<string, mixed>  $profile
     */
    public function __construct(private readonly array $profile = []) {}

    public function countryFields(): array
    {
        return array_values((array) ($this->profile['settings']['fields'] ?? []));
    }

    public function defaults(): array
    {
        return (array) ($this->profile['settings']['defaults'] ?? []);
    }
}
