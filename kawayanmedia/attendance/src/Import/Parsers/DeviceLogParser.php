<?php

namespace KawayanMedia\Attendance\Import\Parsers;

use KawayanMedia\Attendance\Devices\PunchRecord;

/**
 * Reads a device export file into punch records.
 */
interface DeviceLogParser
{
    public function name(): string;

    /**
     * Whether this parser recognises the file.
     */
    public function supports(string $filename, string $contents): bool;

    /**
     * @return array<int, PunchRecord>
     */
    public function parse(string $contents, string $timezone = 'Asia/Manila'): array;
}
