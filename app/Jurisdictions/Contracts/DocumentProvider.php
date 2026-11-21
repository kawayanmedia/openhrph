<?php

namespace App\Jurisdictions\Contracts;

use App\Enums\Sector;

/**
 * Country HR document checklists and form types.
 */
interface DocumentProvider
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function checklist(?Sector $sector = null): array;

    /**
     * @return array<string, string>
     */
    public function types(): array;
}
