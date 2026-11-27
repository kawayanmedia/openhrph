<?php

namespace KawayanMedia\Attendance\Import\Parsers;

use RuntimeException;

/**
 * Picks the parser that recognises the uploaded file.
 */
final class ParserResolver
{
    /**
     * @param  array<int, DeviceLogParser>  $parsers
     */
    public function __construct(private array $parsers = [])
    {
        if ($this->parsers === []) {
            $this->parsers = [
                new ZktecoDatParser,
                new HikvisionCsvParser,
                new SupremaCsvParser,
                new GenericCsvParser,
            ];
        }
    }

    public function resolve(string $filename, string $contents): DeviceLogParser
    {
        foreach ($this->parsers as $parser) {
            if ($parser->supports($filename, $contents)) {
                return $parser;
            }
        }

        throw new RuntimeException(
            'The file was not recognised. Export it as CSV or as the tab-separated ZKTeco DAT file.'
        );
    }

    /**
     * @return array<int, DeviceLogParser>
     */
    public function parsers(): array
    {
        return $this->parsers;
    }
}
