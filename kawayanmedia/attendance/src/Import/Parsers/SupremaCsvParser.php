<?php

namespace KawayanMedia\Attendance\Import\Parsers;

/**
 * Suprema BioStar's attendance report export (`User ID, Date, Time, Device, ...`).
 */
final class SupremaCsvParser extends GenericCsvParser
{
    public function name(): string
    {
        return 'suprema-csv';
    }

    public function supports(string $filename, string $contents): bool
    {
        $header = $this->firstRow($contents);

        if ($header === null) {
            return false;
        }

        $normalized = implode(' ', array_map(fn (string $cell): string => $this->normalizeHeader($cell), $header));

        return str_contains($normalized, 'user id') && (str_contains($normalized, 'device') || str_contains($normalized, 'tna') || str_contains($normalized, 'user name'));
    }
}
