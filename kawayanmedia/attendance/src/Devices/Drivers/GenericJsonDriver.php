<?php

namespace KawayanMedia\Attendance\Devices\Drivers;

use KawayanMedia\Attendance\Contracts\Devices\Driver;
use KawayanMedia\Attendance\Devices\DeviceDescriptor;
use KawayanMedia\Attendance\Devices\DeviceProbe;
use KawayanMedia\Attendance\Devices\PullResult;
use KawayanMedia\Attendance\Devices\PullWindow;

/**
 * Devices or middleware that already speak the JSON ingestion API.
 *
 * Nothing to pull: the client posts `{logs: [...]}` to
 * `/api/attendance/logs` with a device or company key.
 */
final class GenericJsonDriver implements Driver
{
    public const PROTOCOL = 'generic_json';

    public function protocol(): string
    {
        return self::PROTOCOL;
    }

    public function canPull(): bool
    {
        return false;
    }

    public function pull(DeviceDescriptor $device, PullWindow $window): PullResult
    {
        return PullResult::empty(['This protocol pushes logs; there is nothing to pull.']);
    }

    public function probe(DeviceDescriptor $device): DeviceProbe
    {
        return DeviceProbe::success(
            'This device pushes JSON logs to the attendance ingestion API; no pull test is available.',
            ['endpoint' => '/api/attendance/logs'],
        );
    }
}
