<?php

namespace KawayanMedia\Attendance\Console\Commands;

use Illuminate\Console\Command;
use KawayanMedia\Attendance\Contracts\Devices\DeviceRepository;

final class PruneDeviceEventsCommand extends Command
{
    protected $signature = 'attendance:devices:prune {--days= : Retain this many days of device events}';

    protected $description = 'Delete old device protocol and sync events';

    public function handle(DeviceRepository $devices): int
    {
        $days = (int) ($this->option('days') ?: config('attendance-devices.events.retention_days', 30));

        if ($days < 1) {
            $this->error('The retention period must be at least one day.');

            return Command::FAILURE;
        }

        $deleted = $devices->pruneEvents($days);

        $this->info("Deleted {$deleted} device event(s) older than {$days} day(s).");

        return Command::SUCCESS;
    }
}
