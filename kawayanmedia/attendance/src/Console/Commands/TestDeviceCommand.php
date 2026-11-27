<?php

namespace KawayanMedia\Attendance\Console\Commands;

use Illuminate\Console\Command;
use KawayanMedia\Attendance\Contracts\Devices\DeviceRepository;
use KawayanMedia\Attendance\Devices\DriverManager;
use KawayanMedia\Attendance\Devices\PullWindow;

final class TestDeviceCommand extends Command
{
    protected $signature = 'attendance:devices:test
        {device : Device id}
        {--pull : Also read one page of logs without storing them}';

    protected $description = 'Check that the application can reach a biometric device';

    public function handle(DeviceRepository $devices, DriverManager $drivers): int
    {
        $device = $devices->find((int) $this->argument('device'));

        if ($device === null) {
            $this->error('Device not found.');

            return Command::FAILURE;
        }

        $driver = $drivers->driverFor($device->protocol);
        $probe = $driver->probe($device);

        $probe->ok ? $this->info($probe->message) : $this->error($probe->message);

        foreach ($probe->details as $key => $value) {
            $this->line("  {$key}: ".(is_scalar($value) ? (string) $value : json_encode($value)));
        }

        if ($this->option('pull') && $probe->ok && $driver->canPull()) {
            $window = PullWindow::forDevice($device, (array) config('attendance-devices.pull', []));
            $result = $driver->pull($device, $window);

            $this->line('Read '.$result->fetched.' record(s); newest: '.($result->cursor ?? 'none'));

            foreach ($result->warnings as $warning) {
                $this->warn($warning);
            }
        }

        return $probe->ok ? Command::SUCCESS : Command::FAILURE;
    }
}
