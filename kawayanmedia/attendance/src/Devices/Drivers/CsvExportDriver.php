<?php

namespace KawayanMedia\Attendance\Devices\Drivers;

use KawayanMedia\Attendance\Contracts\Devices\Driver;
use KawayanMedia\Attendance\Devices\DeviceDescriptor;
use KawayanMedia\Attendance\Devices\DeviceProbe;
use KawayanMedia\Attendance\Devices\PullResult;
use KawayanMedia\Attendance\Devices\PullWindow;

/**
 * Any device whose only integration is exporting a log file.
 */
final class CsvExportDriver implements Driver
{
    public const PROTOCOL = 'csv_export';

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
        return PullResult::empty(['Upload the device export file from the Devices screen instead.']);
    }

    public function probe(DeviceDescriptor $device): DeviceProbe
    {
        return DeviceProbe::success(
            'This device is imported from an export file (CSV or DAT); no network test is available.',
        );
    }
}
