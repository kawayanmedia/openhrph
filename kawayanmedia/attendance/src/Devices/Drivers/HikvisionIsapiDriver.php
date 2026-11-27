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
 * Pulls access-control events from a Hikvision device (ISAPI).
 *
 * `POST /ISAPI/AccessControl/AcsEvent` returns a page of `InfoList` entries.
 * Direction is read from `inAndOutType` when the firmware reports it; otherwise
 * the event's `minor` code can be mapped through `devices.config.direction_map`
 * and, failing that, the punch is left undirected for the recorder to sequence.
 */
final class HikvisionIsapiDriver implements Driver
{
    public const PROTOCOL = 'hikvision_isapi';

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

        $batch = max(1, (int) $device->option('batch_size', (int) config('attendance-devices.pull.batch_size', 30)));
        $position = 0;
        $pages = 0;
        $records = [];
        $warnings = [];
        $url = $device->baseUrl().'/ISAPI/AccessControl/AcsEvent?format=json';

        do {
            $payload = $this->request($url, $username, $password, [
                'AcsEventCond' => [
                    'searchID' => (string) $device->option('search_id', '1'),
                    'searchResultPosition' => $position,
                    'maxResults' => $batch,
                    'major' => (int) $device->option('major', 0),
                    'minor' => (int) $device->option('minor', 0),
                    'startTime' => $window->from->format('c'),
                    'endTime' => $window->to->format('c'),
                    'timeReverseOrder' => false,
                ],
            ], $this->verify($device));

            $events = $payload['AcsEvent']['InfoList'] ?? [];
            $status = (string) ($payload['AcsEvent']['responseStatusStrg'] ?? 'OK');

            if ($events === []) {
                break;
            }

            foreach ($events as $event) {
                $record = $this->record($event, $device, $window);

                if ($record !== null) {
                    $records[] = $record;
                }
            }

            $position += count($events);
            $pages++;

            if (count($records) >= $window->limit) {
                $warnings[] = 'Only the first '.count($records).' events were read; the rest arrive on the next run.';
                break;
            }
        } while ($status === 'MORE' && $pages < 100);

        return PullResult::fromRecords($records, $warnings);
    }

    public function probe(DeviceDescriptor $device): DeviceProbe
    {
        [$username, $password] = $this->credentials($device);

        try {
            $response = DigestAuth::send(
                'GET',
                $device->baseUrl().'/ISAPI/System/deviceInfo?format=json',
                [],
                $username,
                $password,
                $this->timeout(),
                $this->verify($device),
            );

            if ($response->status() === 401) {
                return DeviceProbe::failure('Hikvision rejected the username or password.');
            }

            if (! $response->successful()) {
                return DeviceProbe::failure('Hikvision answered HTTP '.$response->status().': '.mb_substr($response->body(), 0, 300));
            }

            $info = $response->json('DeviceInfo', $response->json() ?? []);

            return DeviceProbe::success('Connected to the Hikvision device.', [
                'device_name' => $info['deviceName'] ?? null,
                'model' => $info['model'] ?? null,
                'serial' => $info['serialNumber'] ?? null,
                'firmware' => $info['firmwareVersion'] ?? null,
            ]);
        } catch (Throwable $exception) {
            return DeviceProbe::failure($exception->getMessage());
        }
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    private function request(string $url, string $username, string $password, array $body, bool $verify = true): array
    {
        $response = DigestAuth::send('POST', $url, ['json' => $body], $username, $password, $this->timeout(), $verify);

        if ($response->status() === 401) {
            throw DeviceConnectionException::rejected('invalid username or password');
        }

        if (! $response->successful()) {
            throw new DeviceConnectionException('Hikvision answered HTTP '.$response->status().': '.mb_substr($response->body(), 0, 300));
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            throw new DeviceConnectionException('Hikvision returned an unexpected response.');
        }

        $statusCode = $payload['AcsEvent']['responseStatusStrg'] ?? null;

        if (($payload['statusCode'] ?? 0) !== 0 && ($payload['statusCode'] ?? 0) !== 1) {
            throw new DeviceConnectionException('Hikvision rejected the event query: '.($payload['statusString'] ?? 'unknown error'));
        }

        if ($statusCode === 'NO MATCH') {
            return ['AcsEvent' => ['InfoList' => [], 'responseStatusStrg' => 'OK']];
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function record(array $event, DeviceDescriptor $device, PullWindow $window): ?PunchRecord
    {
        $pin = trim((string) ($event['employeeNoString'] ?? $event['employeeNo'] ?? $event['cardNo'] ?? ''));

        if ($pin === '') {
            return null;
        }

        $moment = $this->moment($event['time'] ?? null, $device->timezone);

        if ($moment === null || ! $window->includes($moment)) {
            return null;
        }

        return new PunchRecord(
            devicePin: $pin,
            punchedAt: $moment,
            direction: $this->direction($event, $device),
            raw: $event,
        );
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function direction(array $event, DeviceDescriptor $device): ?int
    {
        $inAndOut = $event['inAndOutType'] ?? null;

        if (is_numeric($inAndOut)) {
            return match ((int) $inAndOut) {
                1 => 0,
                2 => 1,
                default => null,
            };
        }

        $map = $device->option('direction_map', []);

        if (is_array($map)) {
            $minor = isset($event['minor']) ? (string) $event['minor'] : null;

            if ($minor !== null && isset($map[$minor]) && is_numeric($map[$minor])) {
                return (int) $map[$minor];
            }
        }

        return null;
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
            throw new DeviceConnectionException('Set the Hikvision username and password on the device record.');
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
