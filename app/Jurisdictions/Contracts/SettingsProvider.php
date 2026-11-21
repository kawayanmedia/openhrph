<?php

namespace App\Jurisdictions\Contracts;

/**
 * Country-specific settings fields and defaults.
 */
interface SettingsProvider
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function countryFields(): array;

    /**
     * @return array<string, mixed>
     */
    public function defaults(): array;
}
