<?php

namespace KawayanMedia\Attendance\Devices\Drivers;

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use KawayanMedia\Attendance\Contracts\Devices\Driver;
use KawayanMedia\Attendance\Devices\DeviceDescriptor;
use KawayanMedia\Attendance\Devices\DeviceProbe;
use KawayanMedia\Attendance\Devices\PullResult;
use KawayanMedia\Attendance\Devices\PullWindow;
use KawayanMedia\Attendance\Devices\PunchRecord;
use KawayanMedia\Attendance\Exceptions\DeviceConnectionException;
use Throwable;

/**
 * Pulls events from Suprema BioStar 2 over its REST API.
 *
 * BioStar 2 exposes an authenticated session (`bs-session-id`) and a paged
 * event collection. Field names and the event path differ between 2.x builds,
 * so the defaults below can be overridden per device in `devices.config`:
 * `events_path`, `rows_path`, `pin_path`, `time_path`, `direction_path`
 * and `direction_map`.
 */
final class SupremaBiostarDriver implements Driver
{
    public const PROTOCOL = 'suprema_biostar';

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
        $session = $this->login($device);
        $limit = max(1, (int) $device->option('batch_size', (int) config('attendance-devices.pull.batch_size', 100)));
        $offset = 0;
        $pages = 0;
        $records = [];
        $warnings = [];
        $rows = [];

        try {
            do {
                $response = $this->sessionRequest($session, $device)
                    ->get($this->eventsUrl($device), [
                        (string) $device->option('limit_param', 'limit') => $limit,
                        (string) $device->option('offset_param', 'offset') => $offset,
                    ]);

                $this->ensureAuthorised($response, 'BioStar 2');

                $rows = data_get($response->json(), (string) $device->option('rows_path', 'EventCollection.rows'), []);
                $rows = is_array($rows) ? $rows : [];

                if ($rows === []) {
                    break;
                }

                foreach ($rows as $row) {
                    if (! is_array($row)) {
                        continue;
                    }

                    $record = $this->record($row, $device, $window);

                    if ($record !== null) {
                        $records[] = $record;
                    }
                }

                $offset += count($rows);
                $pages++;
            } while (count($rows) === $limit && $pages < 100 && count($records) < $window->limit);
        } finally {
            $this->logout($session, $device);
        }

        if (count($records) >= $window->limit) {
            $warnings[] = 'Only the first '.count($records).' events were read; the rest arrive on the next run.';
        }

        return PullResult::fromRecords($records, $warnings);
    }

    public function probe(DeviceDescriptor $device): DeviceProbe
    {
        try {
            $session = $this->login($device);

            return DeviceProbe::success('Signed in to the BioStar 2 server.', [
                'session' => $session !== '' ? 'acquired' : 'missing',
                'endpoint' => $this->eventsUrl($device),
            ]);
        } catch (Throwable $exception) {
            return DeviceProbe::failure($exception->getMessage());
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function record(array $row, DeviceDescriptor $device, PullWindow $window): ?PunchRecord
    {
        $pin = data_get($row, (string) $device->option('pin_path', 'user_id.user_id'));

        if (is_array($pin)) {
            $pin = reset($pin);
        }

        $pin = trim((string) $pin);

        if ($pin === '') {
            return null;
        }

        $time = data_get($row, (string) $device->option('time_path', 'datetime'));

        if (! is_scalar($time)) {
            return null;
        }

        $moment = $this->moment((string) $time, $device->timezone);

        if ($moment === null || ! $window->includes($moment)) {
            return null;
        }

        $code = data_get($row, (string) $device->option('direction_path', 'event_type_id.code'));
        $map = $device->option('direction_map', []);

        $direction = null;

        if (is_scalar($code) && is_array($map) && isset($map[(string) $code]) && is_numeric($map[(string) $code])) {
            $direction = (int) $map[(string) $code];
        }

        return new PunchRecord(
            devicePin: $pin,
            punchedAt: $moment,
            direction: $direction,
            raw: $row,
        );
    }

    private function login(DeviceDescriptor $device): string
    {
        $loginId = $device->credential('login_id', $device->credential('username'));
        $password = $device->credential('password');

        if ($loginId === null || $password === null) {
            throw new DeviceConnectionException('Set the BioStar 2 login id and password on the device record.');
        }

        $response = Http::timeout($this->timeout())
            ->withOptions(['verify' => $this->verify($device)])
            ->acceptJson()
            ->post($this->base($device).'/api/login', [
                'User' => [
                    'login_id' => $loginId,
                    'password' => $password,
                ],
            ]);

        if ($response->status() === 401) {
            throw DeviceConnectionException::rejected('invalid login id or password');
        }

        if (! $response->successful()) {
            throw new DeviceConnectionException('BioStar 2 answered HTTP '.$response->status().': '.mb_substr($response->body(), 0, 300));
        }

        $session = (string) ($response->header('bs-session-id') ?: $response->json('Response.bs_session_id', ''));

        if ($session === '') {
            throw new DeviceConnectionException('BioStar 2 did not return a session id.');
        }

        return $session;
    }

    private function logout(string $session, DeviceDescriptor $device): void
    {
        try {
            $this->sessionRequest($session, $device)->post($this->base($device).'/api/logout');
        } catch (Throwable) {
            // A lingering session expires on its own; never fail a pull over it.
        }
    }

    private function sessionRequest(string $session, DeviceDescriptor $device): PendingRequest
    {
        return Http::timeout($this->timeout())
            ->withOptions(['verify' => $this->verify($device)])
            ->acceptJson()
            ->withHeaders(['bs-session-id' => $session]);
    }

    private function ensureAuthorised(Response $response, string $vendor): void
    {
        if ($response->status() === 401) {
            throw DeviceConnectionException::rejected('the session expired or the credentials are invalid');
        }

        if (! $response->successful()) {
            throw new DeviceConnectionException($vendor.' answered HTTP '.$response->status().': '.mb_substr($response->body(), 0, 300));
        }
    }

    private function eventsUrl(DeviceDescriptor $device): string
    {
        return $this->base($device).'/'.ltrim((string) $device->option('events_path', '/api/events'), '/');
    }

    private function base(DeviceDescriptor $device): string
    {
        $scheme = (bool) $device->option('https', ($device->port === 443));

        return rtrim($device->baseUrl($scheme), '/');
    }

    private function moment(string $value, string $timezone): ?CarbonImmutable
    {
        try {
            return CarbonImmutable::parse($value, $timezone !== '' ? $timezone : 'Asia/Manila');
        } catch (Throwable) {
            return null;
        }
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
