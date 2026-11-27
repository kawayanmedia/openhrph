<?php

namespace KawayanMedia\Attendance\Import\Parsers;

use Carbon\CarbonImmutable;
use KawayanMedia\Attendance\Devices\PunchRecord;
use Throwable;

/**
 * The tab-separated export written by ZKTeco/eSSL devices and their rebrands.
 *
 * Usual shape: `PIN \t YYYY-MM-DD HH:MM:SS \t status \t verify \t workcode \t type`.
 * Shorter dumps (three or four columns) are accepted too.
 */
final class ZktecoDatParser implements DeviceLogParser
{
    public function name(): string
    {
        return 'zkteco-dat';
    }

    public function supports(string $filename, string $contents): bool
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if (in_array($extension, ['dat', 'txt', 'log'], true)) {
            return true;
        }

        $first = $this->firstDataLine($contents);

        return $first !== null && count(explode("\t", $first)) >= 3 && $this->looksLikeDateTime(explode("\t", $first)[1] ?? '');
    }

    public function parse(string $contents, string $timezone = 'Asia/Manila'): array
    {
        $records = [];

        foreach (preg_split('/\r\n|\r|\n/', $contents) ?: [] as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $columns = array_map(fn (string $value): string => trim($value), explode("\t", $line));

            if (count($columns) < 2) {
                continue;
            }

            [$pin, $timestamp] = $columns;

            if ($pin === '' || ! is_numeric($pin) || ! $this->looksLikeDateTime($timestamp)) {
                continue;
            }

            try {
                $moment = CarbonImmutable::parse($timestamp, $timezone);
            } catch (Throwable) {
                continue;
            }

            $records[] = new PunchRecord(
                devicePin: $pin,
                punchedAt: $moment,
                direction: $this->direction($columns),
                raw: ['line' => $line, 'columns' => $columns],
            );
        }

        return $records;
    }

    /**
     * Column 6 is the punch type on full exports; shorter dumps carry the
     * device state in column 3.
     *
     * @param  array<int, string>  $columns
     */
    private function direction(array $columns): ?int
    {
        if (count($columns) >= 6 && is_numeric($columns[5])) {
            return self::directionForType((int) $columns[5]);
        }

        if (count($columns) >= 3 && is_numeric($columns[2])) {
            return self::directionForType((int) $columns[2]);
        }

        return null;
    }

    private static function directionForType(int $type): ?int
    {
        return match ($type) {
            0, 3, 4 => 0,
            1, 2, 5 => 1,
            default => null,
        };
    }

    private function looksLikeDateTime(string $value): bool
    {
        return preg_match('~^\d{4}[-/]\d{2}[-/]\d{2}[ T]\d{2}:\d{2}(:\d{2})?$~', trim($value)) === 1;
    }

    private function firstDataLine(string $contents): ?string
    {
        foreach (preg_split('/\r\n|\r|\n/', $contents) ?: [] as $line) {
            if (trim($line) !== '') {
                return trim($line);
            }
        }

        return null;
    }
}
