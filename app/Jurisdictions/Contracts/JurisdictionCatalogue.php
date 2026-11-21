<?php

namespace App\Jurisdictions\Contracts;

use App\Enums\Sector;

/**
 * Country catalogues that seed company defaults.
 */
interface JurisdictionCatalogue
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function employmentTypes(?Sector $sector = null): array;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function compensationElements(?Sector $sector = null): array;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function deductionTemplates(?Sector $sector = null): array;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function loanProgrammes(?Sector $sector = null): array;
}
