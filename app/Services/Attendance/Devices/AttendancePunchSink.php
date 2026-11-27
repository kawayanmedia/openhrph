<?php

namespace App\Services\Attendance\Devices;

use App\Models\Device;
use App\Services\Attendance\AttendanceLogIngestor;
use KawayanMedia\Attendance\Contracts\Devices\PunchSink;
use KawayanMedia\Attendance\Devices\DeviceDescriptor;
use KawayanMedia\Attendance\Devices\PunchRecord;

/**
 * Forwards device punches to the existing attendance log ingestor.
 *
 * Keeping a single ingestion point means device pulls, ADMS pushes, file
 * imports and the JSON API all share the debounce window, lock checks and the
 * append-only punch ledger.
 */
final class AttendancePunchSink implements PunchSink
{
    public function __construct(private readonly AttendanceLogIngestor $ingestor) {}

    /**
     * @param  array<int, PunchRecord>  $records
     * @return array<string, mixed>
     */
    public function ingest(array $records, string $source, ?DeviceDescriptor $device = null): array
    {
        $logs = array_map(
            fn (PunchRecord $record): array => $record->toLogArray(),
            $records,
        );

        $model = $device === null || $device->id <= 0
            ? null
            : Device::query()->withoutTenant()->find($device->id);

        return $this->ingestor->ingest($logs, $source, $model);
    }
}
