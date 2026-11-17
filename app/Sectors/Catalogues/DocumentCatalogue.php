<?php

namespace App\Sectors\Catalogues;

use App\Enums\Sector;
use App\Sectors\SectorManager;

/**
 * The document checklist for a sector.
 *
 * This is a read-only catalogue: the application records which identifiers are
 * present today and the checklist tells administrators what to collect next.
 * Store uploaded documents against an employee before marking an item complete.
 */
final class DocumentCatalogue
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function requirements(Sector|string|null $sector = null): array
    {
        return app(SectorManager::class)->for($sector)->documents();
    }

    /**
     * @return array<int, string>
     */
    public static function requiredCodes(Sector|string|null $sector = null): array
    {
        return array_values(array_map(
            static fn (array $row): string => (string) ($row['code'] ?? ''),
            array_filter(
                self::requirements($sector),
                static fn (array $row): bool => (bool) ($row['required'] ?? false),
            ),
        ));
    }
}
