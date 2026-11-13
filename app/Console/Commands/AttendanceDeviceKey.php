<?php

namespace App\Console\Commands;

use App\Models\Device;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class AttendanceDeviceKey extends Command
{
    protected $signature = 'attendance:device-key {device : Device id} {--rotate : Replace an existing key}';

    protected $description = 'Generate a hashed API key for a time clock device';

    public function handle(): int
    {
        $device = Device::withoutTenant()->find($this->argument('device'));

        if ($device === null) {
            $this->error('Device not found.');

            return self::FAILURE;
        }

        if ($device->api_key !== null && ! $this->option('rotate')) {
            $this->warn('This device already has an API key. Use --rotate to replace it.');

            return self::FAILURE;
        }

        $plain = Str::random(48);

        $device->forceFill([
            'api_key' => hash('sha256', $plain),
            'is_active' => true,
        ])->save();

        $this->info("API key for {$device->name} (store it now, it is not shown again):");
        $this->line($plain);

        return self::SUCCESS;
    }
}
