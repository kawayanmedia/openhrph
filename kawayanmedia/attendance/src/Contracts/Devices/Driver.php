<?php

namespace KawayanMedia\Attendance\Contracts\Devices;

use KawayanMedia\Attendance\Devices\DeviceDescriptor;
use KawayanMedia\Attendance\Devices\DeviceProbe;
use KawayanMedia\Attendance\Devices\PullResult;
use KawayanMedia\Attendance\Devices\PullWindow;

/**
 * One time-clock protocol.
 *
 * Push-only protocols (ADMS, the generic JSON API) report `canPull() === false`;
 * their logs arrive through the application's HTTP endpoints instead.
 */
interface Driver
{
    /**
     * The `devices.protocol` value this driver handles.
     */
    public function protocol(): string;

    public function canPull(): bool;

    /**
     * Read the punches recorded on the device inside the window.
     */
    public function pull(DeviceDescriptor $device, PullWindow $window): PullResult;

    /**
     * Check that the device answers and the credentials are valid.
     */
    public function probe(DeviceDescriptor $device): DeviceProbe;
}
