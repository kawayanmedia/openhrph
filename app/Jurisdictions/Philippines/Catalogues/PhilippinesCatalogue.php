<?php

namespace App\Jurisdictions\Philippines\Catalogues;

use App\Enums\Sector;
use App\Jurisdictions\Contracts\JurisdictionCatalogue;

final class PhilippinesCatalogue implements JurisdictionCatalogue
{
    public function employmentTypes(?Sector $sector = null): array
    {
        return $this->rows('employment_types', $sector);
    }

    public function compensationElements(?Sector $sector = null): array
    {
        return $this->rows('compensation.elements', $sector);
    }

    public function deductionTemplates(?Sector $sector = null): array
    {
        return $this->rows('deductions', $sector);
    }

    public function loanProgrammes(?Sector $sector = null): array
    {
        return $this->rows('statutory.loans', $sector);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function rows(string $path, ?Sector $sector): array
    {
        $sectors = $sector === null ? Sector::cases() : [$sector];
        $rows = [];

        foreach ($sectors as $case) {
            $profile = $this->profile($case);

            foreach ((array) data_get($profile, $path, []) as $row) {
                if (! is_array($row)) {
                    continue;
                }

                if ($sector === null) {
                    $row['sector'] = $row['sector'] ?? $case->value;
                }

                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    private function profile(Sector $sector): array
    {
        $config = require __DIR__.'/../Config/sector.php';

        return (array) ($config['profiles'][$sector->value] ?? []);
    }
}
