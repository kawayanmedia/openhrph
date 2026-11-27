<?php

namespace KawayanMedia\Attendance\Adms;

use Carbon\CarbonImmutable;
use KawayanMedia\Attendance\Devices\PunchRecord;

/**
 * The ADMS / iClock push protocol shared by ZKTeco, eSSL, Anviz and the many
 * rebadged devices built on the same firmware.
 *
 * The device posts tab-separated ATTLOG lines and expects a plain-text reply:
 * a handshake option block on first contact and `OK: <count>` afterwards.
 */
final class AdmsProtocol
{
    /**
     * Parse the `ATTLOG` body into punch records.
     *
     * Line format: PIN \t YYYY-MM-DD HH:MM:SS \t status \t verify \t workcode
     *
     * @return array<int, PunchRecord>
     */
    public static function parseAttendance(string $body, string $timezone = 'Asia/Manila'): array
    {
        $records = [];
        $lines = preg_split('/\r\n|\r|\n/', $body) ?: [];

        foreach ($lines as $line) {
            $line = trim($line, " \t");

            if ($line === '') {
                continue;
            }

            $columns = array_map(fn (string $value): string => trim($value), explode("\t", $line));
            $pin = $columns[0] ?? '';
            $timestamp = $columns[1] ?? '';

            if ($pin === '' || $timestamp === '') {
                continue;
            }

            try {
                $moment = CarbonImmutable::parse($timestamp, $timezone);
            } catch (\Throwable) {
                continue;
            }

            $status = isset($columns[2]) && is_numeric($columns[2]) ? (int) $columns[2] : null;

            $records[] = new PunchRecord(
                devicePin: $pin,
                punchedAt: $moment,
                direction: self::directionFor($status),
                raw: [
                    'status' => $status,
                    'verify' => $columns[3] ?? null,
                    'workcode' => $columns[4] ?? null,
                    'line' => $line,
                ],
            );
        }

        return $records;
    }

    /**
     * Map the ADMS status column to the application's in/out direction.
     *
     * 0 check-in, 1 check-out, 2 break-out, 3 break-in, 4 overtime-in,
     * 5 overtime-out. Anything else is left null so the recorder sequences it.
     */
    public static function directionFor(?int $status): ?int
    {
        return match ($status) {
            0, 3, 4 => 0,
            1, 2, 5 => 1,
            default => null,
        };
    }

    /**
     * The plain-text option block a device expects after `options=all`.
     *
     * @param  array<string, mixed>  $options
     */
    public static function handshake(string $serial, string $timezone, array $options = []): string
    {
        $offset = self::offsetHours($timezone, $options['server_tz'] ?? null);

        $lines = [
            'GET OPTION FROM: '.$serial,
            'Stamp=9999',
            'OpStamp=0',
            'ErrorDelay='.(int) ($options['error_delay'] ?? 30),
            'Delay='.(int) ($options['delay'] ?? 10),
            'TransTimes='.(string) ($options['trans_times'] ?? '00:00;12:00'),
            'TransInterval='.(int) ($options['trans_interval'] ?? 1),
            'TransFlag=TransData AttLog OpLog',
            'TimeZone='.$offset,
            'Realtime='.(int) ($options['realtime'] ?? 1),
            'Encrypt='.(int) ($options['encrypt'] ?? 0),
            'ServerTZ='.$offset,
        ];

        return implode("\r\n", $lines)."\r\n";
    }

    public static function accepted(int $count): string
    {
        return 'OK: '.max(0, $count);
    }

    /**
     * UTC offset in hours as a compact string, e.g. `8` or `5.5`.
     */
    public static function offsetHours(string $timezone, mixed $configured = null): string
    {
        if (is_numeric($configured)) {
            return (string) ((int) $configured);
        }

        try {
            $offset = CarbonImmutable::now($timezone)->getOffset() / 3600;
        } catch (\Throwable) {
            $offset = 8;
        }

        $formatted = rtrim(rtrim(number_format((float) $offset, 2, '.', ''), '0'), '.');

        return $formatted === '' || $formatted === '-0' ? '0' : $formatted;
    }

    /**
     * Count the records a body claims, without parsing the timestamps.
     */
    public static function lineCount(string $body): int
    {
        return count(array_filter(
            preg_split('/\r\n|\r|\n/', $body) ?: [],
            fn (string $line): bool => trim($line) !== '',
        ));
    }
}
