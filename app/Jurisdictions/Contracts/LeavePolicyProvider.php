<?php

namespace App\Jurisdictions\Contracts;

use App\Enums\Sector;

/**
 * Statutory leave entitlements for a country, optionally refined by sector.
 */
interface LeavePolicyProvider
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function types(?Sector $sector = null): array;

    /**
     * @return array<string, mixed>
     */
    public function rules(?Sector $sector = null): array;
}
