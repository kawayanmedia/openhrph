<?php

namespace KawayanMedia\Attendance\Devices\Drivers;

use Carbon\CarbonImmutable;
use KawayanMedia\Attendance\Contracts\Devices\Driver;
use KawayanMedia\Attendance\Contracts\Devices\ZktecoClientFactory;
use KawayanMedia\Attendance\Devices\DeviceDescriptor;
use KawayanMedia\Attendance\Devices\DeviceProbe;
use KawayanMedia\Attendance\Devices\PullResult;
use KawayanMedia\Attendance\Devices\PullWindow;
use KawayanMedia\Attendance\Devices\PunchRecord;
use KawayanMedia\Attendance\Devices\Transport\RatsZktecoClientFactory;
use KawayanMedia\Attendance\Exceptions\DeviceConnectionException;
use Throwable;

/**
 * Pulls punches over the ZKTeco binary protocol on UDP 4370.
 *
 * Works for ZKTeco and eSSL devices reachable from the machine running the
 * sync (the application server on-premise, or the on-premise agent).
 */
final class ZktecoUdpDriver implements Driver
{
    public const PROTOCOL = 'zkteco_udp';

    public function __construct(
        private readonly ?ZktecoClientFactory $clients = null,
    ) {}

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
        $client = $this->clientFactory()->make((string) $device->host, $device->port ?? 4370);

        try {
            if (! $client->connect()) {
                throw DeviceConnectionException::unreachable((string) $device->host, $device->port ?? 4370);
            }

            $records = [];

            foreach ($client->attendance() as $row) {
                $pin = trim((string) ($row['id'] ?? $row['uid'] ?? ''));

                if ($pin === '') {
                    continue;
                }

                $moment = $this->moment($row['timestamp'] ?? null, $device->timezone);

                if ($moment === null || ! $window->includes($moment)) {
                    continue;
                }

                $type = isset($row['type']) && is_numeric($row['type']) ? (int) $row['type'] : null;

                $records[] = new PunchRecord(
                    devicePin: $pin,
                    punchedAt: $moment,
                    direction: self::directionFor($type),
                    raw: $row,
                );
            }

            return PullResult::fromRecords($records);
        } finally {
            $client->disconnect();
        }
    }

    public function probe(DeviceDescriptor $device): DeviceProbe
    {
        $client = $this->clientFactory()->make((string) $device->host, $device->port ?? 4370);

        try {
            if (! $client->connect()) {
                return DeviceProbe::failure('The device did not answer on UDP port '.($device->port ?? 4370).'.');
            }

            return DeviceProbe::success('Connected to the device over UDP.', [
                'serial' => $client->serialNumber(),
                'firmware' => $client->osVersion(),
            ]);
        } catch (Throwable $exception) {
            return DeviceProbe::failure($exception->getMessage());
        } finally {
            $client->disconnect();
        }
    }

    /**
     * ZKTeco attendance state: 0 in, 1 out, 2 break-out, 3 break-in,
     * 4 overtime-in, 5 overtime-out.
     */
    public static function directionFor(?int $type): ?int
    {
        return match ($type) {
            0, 3, 4 => 0,
            1, 2, 5 => 1,
            default => null,
        };
    }

    private function clientFactory(): ZktecoClientFactory
    {
        if ($this->clients !== null) {
            return $this->clients;
        }

        $configured = config('attendance-devices.contracts.zkteco_client_factory');

        return $configured === null
            ? new RatsZktecoClientFactory
            : app($configured);
    }

    private function moment(mixed $timestamp, string $timezone): ?CarbonImmutable
    {
        if (! is_string($timestamp) || trim($timestamp) === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($timestamp, $timezone !== '' ? $timezone : 'Asia/Manila');
        } catch (Throwable) {
            return null;
        }
    }
}
