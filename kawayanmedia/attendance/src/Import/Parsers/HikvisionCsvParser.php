<?php

namespace KawayanMedia\Attendance\Import\Parsers;

/**
 * Hikvision's attendance export.
 *
 * Headers look like `Employee No., Name, Card No., Door No., Event Time` and
 * the generic alias map already covers most of them; this subclass exists so a
 * Hikvision file is recognised before a looser parser claims it.
 */
final class HikvisionCsvParser extends GenericCsvParser
{
    public function name(): string
    {
        return 'hikvision-csv';
    }

    protected function pinAliases(): array
    {
        return array_merge(parent::pinAliases(), ['employee no']);
    }

    public function supports(string $filename, string $contents): bool
    {
        $header = $this->firstRow($contents);

        if ($header === null) {
            return false;
        }

        $normalized = implode(' ', array_map(fn (string $cell): string => $this->normalizeHeader($cell), $header));

        return str_contains($normalized, 'employee no')
            && (str_contains($normalized, 'door no') || str_contains($normalized, 'major') || str_contains($normalized, 'card no'));
    }
}
