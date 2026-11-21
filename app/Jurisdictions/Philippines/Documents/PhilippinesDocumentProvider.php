<?php

namespace App\Jurisdictions\Philippines\Documents;

use App\Enums\Sector;
use App\Jurisdictions\Contracts\DocumentProvider;

final class PhilippinesDocumentProvider implements DocumentProvider
{
    public function checklist(?Sector $sector = null): array
    {
        if ($sector !== null) {
            return $this->rowsFor($sector);
        }

        $rows = [];
        $seen = [];

        foreach (Sector::cases() as $case) {
            foreach ($this->rowsFor($case) as $row) {
                $key = strtoupper((string) ($row['code'] ?? $row['name'] ?? ''));

                if (isset($seen[$key])) {
                    continue;
                }

                $seen[$key] = true;
                $rows[] = $row;
            }
        }

        return $rows;
    }

    public function types(): array
    {
        $types = [];

        foreach ($this->checklist() as $row) {
            $code = (string) ($row['code'] ?? $row['name'] ?? '');

            if ($code !== '') {
                $types[$code] = (string) ($row['name'] ?? $code);
            }
        }

        return $types;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function rowsFor(Sector $sector): array
    {
        $config = require __DIR__.'/../Config/sector.php';
        $profile = (array) ($config['profiles'][$sector->value] ?? []);

        return array_values((array) ($profile['documents'] ?? []));
    }
}
