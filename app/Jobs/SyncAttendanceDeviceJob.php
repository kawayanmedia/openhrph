<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use KawayanMedia\Attendance\Contracts\Devices\DeviceRepository;
use KawayanMedia\Attendance\Devices\DeviceSync;

/**
 * Pulls one device in the background, used by the "Sync now" admin action.
 */
class SyncAttendanceDeviceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(public int $deviceId) {}

    public function handle(DeviceSync $sync, DeviceRepository $devices): void
    {
        $device = $devices->find($this->deviceId);

        if ($device !== null) {
            $sync->sync($device);
        }
    }
}
