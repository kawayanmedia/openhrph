<?php

namespace KawayanMedia\Attendance\Devices\Drivers;

use KawayanMedia\Attendance\Contracts\Devices\Driver;
use KawayanMedia\Attendance\Devices\DeviceDescriptor;
use KawayanMedia\Attendance\Devices\DeviceProbe;
use KawayanMedia\Attendance\Devices\PullResult;
use KawayanMedia\Attendance\Devices\PullWindow;

/**
 * ADMS/iClock devices open the connection themselves.
 *
 * The actual parsing lives in `Adms\AdmsProtocol` and the HTTP surface in
 * `Adms\AdmsController`; this class exists so the registry knows the protocol
 * and so the admin screen can explain that no pull is possible.
 */
final class ZktecoAdmsDriver implements Driver
{
    public const PROTOCOL = 'zkteco_adms';

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
        return PullResult::empty([
            'ADMS devices push their logs; check that the device serial matches and that the device can reach /iclock/cdata.',
        ]);
    }

    public function probe(DeviceDescriptor $device): DeviceProbe
    {
        if ($device->serialNumber === null || $device->serialNumber === '') {
            return DeviceProbe::failure('Set the device serial number; ADMS devices identify themselves with it.');
        }

        return DeviceProbe::success(
            'The device pushes its logs when configured with server '.url('/'.ltrim((string) config('attendance-devices.route_prefix', 'iclock'), '/')).'.',
            ['serial' => $device->serialNumber],
        );
    }
}
