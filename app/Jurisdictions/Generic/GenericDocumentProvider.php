<?php

namespace App\Jurisdictions\Generic;

use App\Enums\Sector;
use App\Jurisdictions\Contracts\DocumentProvider;

final class GenericDocumentProvider implements DocumentProvider
{
    /**
     * @param  array<string, mixed>  $profile
     */
    public function __construct(private readonly array $profile = []) {}

    public function checklist(?Sector $sector = null): array
    {
        return array_values(array_filter(
            (array) ($this->profile['documents'] ?? []),
            function (mixed $row) use ($sector): bool {
                if (! is_array($row)) {
                    return false;
                }

                if ($sector === null || empty($row['sector'])) {
                    return true;
                }

                return strcasecmp((string) $row['sector'], $sector->value) === 0;
            },
        ));
    }

    public function types(): array
    {
        $types = [];

        foreach ($this->checklist() as $row) {
            if (! empty($row['type'])) {
                $types[(string) $row['type']] = (string) ($row['name'] ?? $row['type']);
            }
        }

        return $types;
    }
}
