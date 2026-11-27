<?php

namespace KawayanMedia\Attendance\Import\Parsers;

use Carbon\CarbonImmutable;
use KawayanMedia\Attendance\Devices\PunchRecord;
use Throwable;

/**
 * Reads a CSV/TSV export from an attendance terminal.
 *
 * Header names differ between vendors, so columns are matched against an alias
 * list. Without a header the parser falls back to the common positional
 * layouts: `pin, datetime` or `pin, date, time[, direction]`.
 */
class GenericCsvParser implements DeviceLogParser
{
    public function name(): string
    {
        return 'generic-csv';
    }

    /**
     * @return array<int, string>
     */
    protected function pinAliases(): array
    {
        return [
            'employee id', 'employee no', 'employee number', 'emp code', 'employee code',
            'biometric id', 'biometric no', 'user id', 'userid', 'user', 'pin',
            'enroll number', 'enroll no', 'enroll id', 'person id', 'person no',
            'staff id', 'ac no', 'account no', 'card no',
        ];
    }

    /**
     * @return array<int, string>
     */
    protected function dateTimeAliases(): array
    {
        return [
            'datetime', 'date time', 'punch datetime', 'punch date time', 'att datetime',
            'att time', 'punch time', 'check time', 'log time', 'clock time',
            'swipe time', 'event time', 'access time', 'timestamp',
        ];
    }

    /**
     * @return array<int, string>
     */
    protected function dateAliases(): array
    {
        return ['date', 'att date', 'log date', 'punch date', 'event date', 'access date'];
    }

    /**
     * @return array<int, string>
     */
    protected function timeAliases(): array
    {
        return ['time', 'clock time', 'punch time', 'event time', 'access time'];
    }

    /**
     * @return array<int, string>
     */
    protected function directionAliases(): array
    {
        return [
            'type', 'direction', 'state', 'log type', 'in out', 'in/out', 'in out status',
            'status', 'punch type', 'att state', 'mode', 'verify mode',
        ];
    }

    public function supports(string $filename, string $contents): bool
    {
        $header = $this->firstRow($contents);

        if ($header === null) {
            return false;
        }

        if ($this->looksLikeHeader($header)) {
            return $this->resolveIndexes($header) !== null;
        }

        // Headerless export: pin + timestamp (or pin + date + time).
        if (count($header) >= 2 && $this->parseMoment($header[1], 'UTC') !== null) {
            return true;
        }

        return count($header) >= 3 && $this->looksLikeTime($header[2]);
    }

    public function parse(string $contents, string $timezone = 'Asia/Manila'): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $contents) ?: [];
        $delimiter = $this->detectDelimiter($contents);
        $indexes = null;
        $records = [];

        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }

            $cells = array_map(fn (?string $value): string => trim((string) $value), str_getcsv($line, $delimiter, '"', '\\'));

            if ($indexes === null && $this->looksLikeHeader($cells)) {
                $indexes = $this->resolveIndexes($cells);

                continue;
            }

            $record = $indexes === null
                ? $this->fromPositionalRow($cells, $timezone)
                : $this->fromMappedRow($cells, $indexes, $timezone);

            if ($record !== null) {
                $records[] = $record;
            }
        }

        return $records;
    }

    /**
     * @param  array<int, string>  $cells
     * @param  array<string, int|null>  $indexes
     */
    private function fromMappedRow(array $cells, array $indexes, string $timezone): ?PunchRecord
    {
        $pinIndex = $indexes['pin'] ?? null;

        if ($pinIndex === null) {
            return null;
        }

        $pin = trim((string) ($cells[$pinIndex] ?? ''));

        if ($pin === '') {
            return null;
        }

        $moment = $this->momentFromCells($cells, $indexes, $timezone);

        if ($moment === null) {
            return null;
        }

        $directionIndex = $indexes['direction'] ?? null;
        $direction = $directionIndex === null ? null : $this->direction($cells[$directionIndex] ?? null);

        return new PunchRecord($pin, $moment, $direction, null, ['cells' => $cells]);
    }

    /**
     * @param  array<int, string>  $cells
     */
    private function fromPositionalRow(array $cells, string $timezone): ?PunchRecord
    {
        if (count($cells) < 2) {
            return null;
        }

        $pin = trim($cells[0]);

        if ($pin === '') {
            return null;
        }

        $moment = count($cells) >= 3 && $this->looksLikeTime($cells[2]) && ! $this->looksLikeTime($cells[1])
            ? $this->combine($cells[1], $cells[2], $timezone)
            : $this->parseMoment($cells[1], $timezone);

        if ($moment === null) {
            return null;
        }

        $direction = count($cells) >= 3 && ! $this->looksLikeTime($cells[2])
            ? $this->direction($cells[2])
            : (count($cells) >= 4 ? $this->direction($cells[3]) : null);

        return new PunchRecord($pin, $moment, $direction, null, ['cells' => $cells]);
    }

    /**
     * @param  array<int, string>  $cells
     * @param  array<string, int|null>  $indexes
     */
    private function momentFromCells(array $cells, array $indexes, string $timezone): ?CarbonImmutable
    {
        $dateTimeIndex = $indexes['datetime'] ?? null;

        if ($dateTimeIndex !== null) {
            return $this->parseMoment($cells[$dateTimeIndex] ?? null, $timezone);
        }

        $dateIndex = $indexes['date'] ?? null;
        $timeIndex = $indexes['time'] ?? null;

        if ($dateIndex === null || $timeIndex === null) {
            return null;
        }

        return $this->combine($cells[$dateIndex] ?? null, $cells[$timeIndex] ?? null, $timezone);
    }

    protected function parseMoment(mixed $value, string $timezone): ?CarbonImmutable
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse(trim($value), $timezone);
        } catch (Throwable) {
            return null;
        }
    }

    private function combine(mixed $date, mixed $time, string $timezone): ?CarbonImmutable
    {
        if (! is_string($date) || ! is_string($time) || trim($date) === '' || trim($time) === '') {
            return null;
        }

        return $this->parseMoment(trim($date).' '.trim($time), $timezone);
    }

    private function direction(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }

        $value = strtolower(trim((string) $value));

        if ($value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return match ((int) $value) {
                0 => 0,
                1 => 1,
                default => null,
            };
        }

        return match (preg_replace('/[^a-z]/', '', $value) ?? '') {
            'in', 'i', 'login', 'checkin', 'timein', 'clockin', 'entry' => 0,
            'out', 'o', 'logout', 'checkout', 'timeout', 'clockout', 'exit' => 1,
            default => null,
        };
    }

    /**
     * @param  array<int, string>  $header
     * @return array<string, int|null>|null
     */
    private function resolveIndexes(array $header): ?array
    {
        $normalized = array_map(fn (string $cell): string => $this->normalizeHeader($cell), $header);

        $pin = $this->columnIndex($normalized, $this->pinAliases());
        $dateTime = $this->columnIndex($normalized, $this->dateTimeAliases());

        if ($pin === null || ($dateTime === null && $this->columnIndex($normalized, $this->dateAliases()) === null)) {
            return null;
        }

        return [
            'pin' => $pin,
            'datetime' => $dateTime,
            'date' => $this->columnIndex($normalized, $this->dateAliases()),
            'time' => $this->columnIndex($normalized, $this->timeAliases()),
            'direction' => $this->columnIndex($normalized, $this->directionAliases()),
        ];
    }

    /**
     * @param  array<int, string>  $normalized
     * @param  array<int, string>  $aliases
     */
    private function columnIndex(array $normalized, array $aliases): ?int
    {
        $aliases = array_map(fn (string $alias): string => $this->normalizeHeader($alias), $aliases);

        foreach ($normalized as $index => $cell) {
            if ($cell !== '' && in_array($cell, $aliases, true)) {
                return $index;
            }
        }

        return null;
    }

    protected function normalizeHeader(string $value): string
    {
        $value = strtolower(trim($value));
        $value = str_replace(['_', '-', '.'], ' ', $value);

        return trim(preg_replace('/\s+/', ' ', $value) ?? $value);
    }

    /**
     * @param  array<int, string>  $cells
     */
    private function looksLikeHeader(array $cells): bool
    {
        foreach ($cells as $cell) {
            if ($cell === '') {
                continue;
            }

            if ($this->parseMoment($cell, 'UTC') !== null) {
                return false;
            }
        }

        $normalized = array_map(fn (string $cell): string => $this->normalizeHeader($cell), $cells);

        return $this->columnIndex($normalized, $this->pinAliases()) !== null;
    }

    /**
     * @return array<int, string>|null
     */
    protected function firstRow(string $contents): ?array
    {
        foreach (preg_split('/\r\n|\r|\n/', $contents) ?: [] as $line) {
            if (trim($line) === '') {
                continue;
            }

            $delimiter = $this->detectDelimiter($contents);

            return array_map(
                fn (?string $value): string => trim((string) $value),
                str_getcsv($line, $delimiter, '"', '\\'),
            );
        }

        return null;
    }

    private function detectDelimiter(string $contents): string
    {
        $sample = substr($contents, 0, 4096);
        $candidates = [',' => substr_count($sample, ','), "\t" => substr_count($sample, "\t"), ';' => substr_count($sample, ';')];

        arsort($candidates);

        $delimiter = array_key_first($candidates);

        return ($candidates[$delimiter] ?? 0) > 0 ? (string) $delimiter : ',';
    }

    protected function looksLikeTime(string $value): bool
    {
        return preg_match('/^\d{1,2}:\d{2}(:\d{2})?(\s*[AaPp][Mm])?$/', trim($value)) === 1;
    }
}
