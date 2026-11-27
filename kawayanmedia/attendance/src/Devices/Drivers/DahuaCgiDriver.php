<?php

namespace KawayanMedia\Attendance\Devices\Drivers;

use Carbon\CarbonImmutable;
use KawayanMedia\Attendance\Contracts\Devices\Driver;
use KawayanMedia\Attendance\Devices\DeviceDescriptor;
use KawayanMedia\Attendance\Devices\DeviceProbe;
use KawayanMedia\Attendance\Devices\PullResult;
use KawayanMedia\Attendance\Devices\PullWindow;
use KawayanMedia\Attendance\Devices\PunchRecord;
use KawayanMedia\Attendance\Devices\Support\DigestAuth;
use KawayanMedia\Attendance\Exceptions\DeviceConnectionException;
use Throwable;

/**
 * Pulls access-control records from a Dahua device through its CGI API.
 *
 * `recordFinder.cgi` returns `key=value` lines rather than JSON, so the driver
 * flattens `records[n].Field` keys back into rows before mapping them.
 */
final class DahuaCgiDriver implements Driver
{
    public const PROTOCOL = 'dahua_cgi';

    public function protocol(): string
    {
        return self::PROTOCOL;
    }

    public function canPull(): bool
    {
        return true;
    }

    public function pull(DeviceDescriptor $device, PullWindow $window): PullResult
    {
        [$username, $password] = $this->credentials($device);

        $pageSize = max(1, (int) $device->option('batch_size', (int) config('attendance-devices.pull.batch_size', 100)));
        $offset = 0;
        $pages = 0;
        $records = [];
        $warnings = [];
        $url = $device->baseUrl().'/cgi-bin/recordFinder.cgi?action=find&name=AccessControlCardRec';

        do {
            $response = DigestAuth::send(
                'POST',
                $url,
                ['form_params' => [
                    'startTime' => $window->from->format('Y-m-d H:i:s'),
                    'endTime' => $window->to->format('Y-m-d H:i:s'),
                    'count' => $pageSize,
                    'offset' => $offset,
                ]],
                $username,
                $password,
                $this->timeout(),
                $this->verify($device),
            );

            if ($response->status() === 401) {
                throw DeviceConnectionException::rejected('invalid username or password');
            }

            if (! $response->successful()) {
                throw new DeviceConnectionException('Dahua answered HTTP '.$response->status().': '.mb_substr($response->body(), 0, 300));
            }

            $parsed = self::parseResponse($response->body());
            $rows = $parsed['records'];

            foreach ($rows as $row) {
                $record = $this->record($row, $device, $window);

                if ($record !== null) {
                    $records[] = $record;
                }
            }

            $offset += count($rows);
            $pages++;

            if ($rows === [] || count($records) >= $window->limit) {
                break;
            }
        } while ($offset < $parsed['found'] && $pages < 100);

        if (count($records) >= $window->limit) {
            $warnings[] = 'Only the first '.count($records).' records were read; the rest arrive on the next run.';
        }

        return PullResult::fromRecords($records, $warnings);
    }

    public function probe(DeviceDescriptor $device): DeviceProbe
    {
        [$username, $password] = $this->credentials($device);

        try {
            $base = $device->baseUrl();

            $type = DigestAuth::send('GET', $base.'/cgi-bin/magicBox.cgi?action=getDeviceType', [], $username, $password, $this->timeout(), $this->verify($device));
            $version = DigestAuth::send('GET', $base.'/cgi-bin/magicBox.cgi?action=getSoftwareVersion', [], $username, $password, $this->timeout(), $this->verify($device));

            if ($type->status() === 401 || $version->status() === 401) {
                return DeviceProbe::failure('Dahua rejected the username or password.');
            }

            if (! $type->successful() && ! $version->successful()) {
                return DeviceProbe::failure('Dahua did not answer the CGI probe (HTTP '.$type->status().').');
            }

            return DeviceProbe::success('Connected to the Dahua device.', [
                'device_type' => trim(str_replace('type=', '', $type->body())),
                'version' => trim(str_replace('version=', '', $version->body())),
            ]);
        } catch (Throwable $exception) {
            return DeviceProbe::failure($exception->getMessage());
        }
    }

    /**
     * Flatten `records[n].Field=value` lines into rows.
     *
     * @return array{found: int, records: array<int, array<string, string>>}
     */
    public static function parseResponse(string $body): array
    {
        $found = 0;
        $records = [];

        foreach (preg_split('/\r\n|\r|\n/', $body) ?: [] as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            [$key, $value] = array_pad(explode('=', $line, 2), 2, '');

            if ($key === 'found') {
                $found = (int) $value;

                continue;
            }

            if (! preg_match('/^records\[(\d+)\]\.(.+)$/', $key, $matches)) {
                continue;
            }

            $records[(int) $matches[1]][$matches[2]] = $value;
        }

        ksort($records);

        return ['found' => $found, 'records' => array_values($records)];
    }

    /**
     * @param  array<string, string>  $row
     */
    private function record(array $row, DeviceDescriptor $device, PullWindow $window): ?PunchRecord
    {
        $pin = trim((string) ($row['UserID'] ?? $row['CardNo'] ?? ''));

        if ($pin === '') {
            return null;
        }

        $moment = $this->moment($row['Time'] ?? null, $device->timezone);

        if ($moment === null || ! $window->includes($moment)) {
            return null;
        }

        return new PunchRecord(
            devicePin: $pin,
            punchedAt: $moment,
            direction: $this->direction($row, $device),
            raw: $row,
        );
    }

    /**
     * @param  array<string, string>  $row
     */
    private function direction(array $row, DeviceDescriptor $device): ?int
    {
        $map = $device->option('direction_map', []);

        if (! is_array($map)) {
            return null;
        }

        $field = (string) $device->option('direction_field', 'Type');
        $value = $row[$field] ?? null;

        if ($value === null) {
            return null;
        }

        return isset($map[$value]) && is_numeric($map[$value]) ? (int) $map[$value] : null;
    }

    private function moment(mixed $value, string $timezone): ?CarbonImmutable
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value, $timezone !== '' ? $timezone : 'Asia/Manila');
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function credentials(DeviceDescriptor $device): array
    {
        $username = $device->credential('username');
        $password = $device->credential('password');

        if ($username === null || $password === null) {
            throw new DeviceConnectionException('Set the Dahua username and password on the device record.');
        }

        return [$username, $password];
    }

    private function timeout(): int
    {
        return max(1, (int) config('attendance-devices.pull.timeout', 15));
    }

    private function verify(DeviceDescriptor $device): bool
    {
        return (bool) $device->option('verify_tls', true);
    }
}
