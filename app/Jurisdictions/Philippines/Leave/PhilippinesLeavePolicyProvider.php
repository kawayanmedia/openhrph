<?php

namespace App\Jurisdictions\Philippines\Leave;

use App\Enums\Sector;
use App\Jurisdictions\Contracts\LeavePolicyProvider;

/**
 * Philippine statutory leave: Labor Code for private, CSC rules for government.
 */
final class PhilippinesLeavePolicyProvider implements LeavePolicyProvider
{
    public function types(?Sector $sector = null): array
    {
        if ($sector !== null) {
            return $this->rowsFor($sector)['types'];
        }

        $rows = [];
        $seen = [];

        foreach (Sector::cases() as $case) {
            foreach ($this->rowsFor($case)['types'] as $row) {
                $key = strtoupper((string) ($row['name'] ?? $row['code'] ?? ''));

                if (isset($seen[$key])) {
                    continue;
                }

                $seen[$key] = true;
                $rows[] = $row;
            }
        }

        return $rows;
    }

    public function rules(?Sector $sector = null): array
    {
        if ($sector !== null) {
            return $this->rowsFor($sector)['rules'];
        }

        return $this->rowsFor(Sector::Private)['rules'];
    }

    /**
     * @return array{types: array<int, array<string, mixed>>, rules: array<string, mixed>}
     */
    private function rowsFor(Sector $sector): array
    {
        $config = require __DIR__.'/../Config/sector.php';
        $profile = (array) ($config['profiles'][$sector->value] ?? []);

        return [
            'types' => array_values((array) ($profile['leave']['types'] ?? [])),
            'rules' => (array) ($profile['leave']['rules'] ?? []),
        ];
    }
}
