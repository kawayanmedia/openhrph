<?php

namespace KawayanMedia\Attendance\Contracts\Devices;

use KawayanMedia\Attendance\Devices\DeviceDescriptor;
use KawayanMedia\Attendance\Devices\PunchRecord;

/**
 * Receives punches from any ingestion path.
 *
 * The application implementation forwards to the attendance log ingestor, so
 * de-duplication, lock checks and timesheet mirroring stay in one place.
 */
interface PunchSink
{
    /**
     * @param  array<int, PunchRecord>  $records
     * @return array<string, mixed>
     */
    public function ingest(array $records, string $source, ?DeviceDescriptor $device = null): array;
}
