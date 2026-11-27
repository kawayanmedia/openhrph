<?php

namespace KawayanMedia\Attendance\Console\Commands;

use Illuminate\Console\Command;
use KawayanMedia\Attendance\Contracts\Devices\DeviceRepository;
use KawayanMedia\Attendance\Devices\DeviceDescriptor;
use KawayanMedia\Attendance\Devices\DeviceSync;
use KawayanMedia\Attendance\Devices\SyncOutcome;

/**
 * Pulls every due device and feeds the punches into the timesheets.
 *
 * Run it from the scheduler on the application server (on-premise installs) or
 * rely on `attendance:agent` when the devices sit behind a customer NAT.
 */
final class SyncDevicesCommand extends Command
{
    protected $signature = 'attendance:devices:sync
        {--company= : Only sync devices of this company id}
        {--device= : Only sync this device id}
        {--force : Ignore the per-device sync interval}
        {--dry-run : Read the devices but do not store the punches}';

    protected $description = 'Pull time logs from registered biometric devices';

    public function handle(DeviceSync $sync, DeviceRepository $devices): int
    {
        $targets = $this->targets($devices);

        if ($targets === null) {
            return Command::FAILURE;
        }

        if ($targets === []) {
            $this->info('No devices are due for sync.');

            return Command::SUCCESS;
        }

        $rows = [];
        $failed = 0;

        foreach ($targets as $device) {
            if (! $this->option('force') && ! $device->isDue()) {
                $rows[] = [$device->name, $device->protocol, 'skipped', 0, 0, 0, 'Not due yet'];

                continue;
            }

            $outcome = $sync->sync($device, (bool) $this->option('dry-run'));

            if ($outcome->status === SyncOutcome::STATUS_FAILED) {
                $failed++;
                $this->error("{$device->name}: {$outcome->message}");
            }

            $rows[] = [
                $device->name,
                $device->protocol,
                $outcome->status,
                $outcome->fetched,
                (int) ($outcome->summary['accepted'] ?? 0),
                (int) ($outcome->summary['duplicates'] ?? 0),
                $outcome->message,
            ];
        }

        $this->table(['Device', 'Protocol', 'Status', 'Read', 'Accepted', 'Duplicates', 'Message'], $rows);

        return $failed > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * @return array<int, DeviceDescriptor>|null
     */
    private function targets(DeviceRepository $devices): ?array
    {
        $deviceId = $this->option('device');

        if ($deviceId !== null && $deviceId !== '') {
            $device = $devices->find((int) $deviceId);

            if ($device === null) {
                $this->error("Device [{$deviceId}] was not found.");

                return null;
            }

            return $device->syncEnabled ? [$device] : [];
        }

        $companyId = $this->option('company');
        $targets = [];

        foreach ($devices->pullable() as $device) {
            if ($companyId !== null && $companyId !== '' && $device->tenantId !== (int) $companyId) {
                continue;
            }

            $targets[] = $device;
        }

        return $targets;
    }
}
