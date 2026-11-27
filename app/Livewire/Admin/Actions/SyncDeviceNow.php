<?php

namespace App\Livewire\Admin\Actions;

use App\Models\Device;
use App\Services\Attendance\Devices\EloquentDeviceRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use KawayanMedia\Attendance\Devices\DeviceSync;

/**
 * Pulls the selected device immediately instead of waiting for the schedule.
 */
class SyncDeviceNow extends Action
{
    public string $name = 'Sync Now';

    public string $confirmText = 'Read the latest time logs from this device now?';

    public string $confirmButtonText = 'Sync';

    public function handle(Request $request, array $fields, Collection $models): mixed
    {
        $device = $models->first();

        if (! $device instanceof Device) {
            return self::message('Select a device first.');
        }

        if (! $device->isPullable()) {
            return self::message('This protocol pushes logs to the app; there is nothing to pull.');
        }

        if ($device->ip_address === null || $device->ip_address === '') {
            return self::message('Set the device IP address before syncing.');
        }

        $descriptor = app(EloquentDeviceRepository::class)->descriptorFor($device);
        $outcome = app(DeviceSync::class)->sync($descriptor);

        if ($outcome->status === 'failed') {
            return self::message("Sync failed: {$outcome->message}");
        }

        if ($outcome->status === 'skipped') {
            return self::message($outcome->message);
        }

        $accepted = (int) ($outcome->summary['accepted'] ?? 0);
        $duplicates = (int) ($outcome->summary['duplicates'] ?? 0);
        $unmatched = (int) ($outcome->summary['unmatched'] ?? 0);

        return self::message(
            "Read {$outcome->fetched} punch(es): {$accepted} accepted, {$duplicates} duplicate(s), {$unmatched} unmatched."
        );
    }
}
