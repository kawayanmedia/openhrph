<?php

namespace App\Jurisdictions\Generic;

use App\Enums\Sector;
use App\Jurisdictions\Contracts\JurisdictionCatalogue;

final class GenericCatalogue implements JurisdictionCatalogue
{
    /**
     * @param  array<string, mixed>  $profile
     */
    public function __construct(private readonly array $profile = []) {}

    public function employmentTypes(?Sector $sector = null): array
    {
        return $this->rows((array) ($this->profile['employment_types'] ?? []), $sector);
    }

    public function compensationElements(?Sector $sector = null): array
    {
        return $this->rows((array) ($this->profile['compensation']['elements'] ?? []), $sector);
    }

    public function deductionTemplates(?Sector $sector = null): array
    {
        return $this->rows((array) ($this->profile['deductions'] ?? []), $sector);
    }

    public function loanProgrammes(?Sector $sector = null): array
    {
        return $this->rows((array) ($this->profile['loans'] ?? []), $sector);
    }

    /**
     * @param  array<int, mixed>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function rows(array $rows, ?Sector $sector): array
    {
        return array_values(array_filter($rows, function (mixed $row) use ($sector): bool {
            if (! is_array($row)) {
                return false;
            }

            if ($sector === null || empty($row['sector'])) {
                return true;
            }

            return strcasecmp((string) $row['sector'], $sector->value) === 0;
        }));
    }
}
