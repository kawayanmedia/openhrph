<?php

namespace App\Jurisdictions\Generic;

use App\Enums\Sector;
use App\Jurisdictions\Contracts\LeavePolicyProvider;

final class GenericLeavePolicyProvider implements LeavePolicyProvider
{
    /**
     * @param  array<string, mixed>  $profile
     */
    public function __construct(private readonly array $profile = []) {}

    public function types(?Sector $sector = null): array
    {
        return array_values(array_filter(
            (array) ($this->profile['leave']['types'] ?? []),
            fn (mixed $row): bool => is_array($row) && $this->sectorAllows($row, $sector),
        ));
    }

    public function rules(?Sector $sector = null): array
    {
        return (array) ($this->profile['leave']['rules'] ?? []);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function sectorAllows(array $row, ?Sector $sector): bool
    {
        if ($sector === null || empty($row['sector'])) {
            return true;
        }

        return strcasecmp((string) $row['sector'], $sector->value) === 0;
    }
}
