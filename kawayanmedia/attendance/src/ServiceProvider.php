<?php

namespace KawayanMedia\Attendance;

use Illuminate\Support\ServiceProvider as BaseServiceProvider;
use KawayanMedia\Attendance\Console\Commands\AgentCommand;
use KawayanMedia\Attendance\Console\Commands\PruneDeviceEventsCommand;
use KawayanMedia\Attendance\Console\Commands\SyncDevicesCommand;
use KawayanMedia\Attendance\Console\Commands\TestDeviceCommand;
use KawayanMedia\Attendance\Contracts\Devices\DeviceRepository;
use KawayanMedia\Attendance\Contracts\Devices\PunchSink;
use KawayanMedia\Attendance\Devices\DeviceSync;
use KawayanMedia\Attendance\Devices\DriverManager;
use KawayanMedia\Attendance\Import\DeviceLogImporter;
use KawayanMedia\Attendance\Import\MachineImport;
use KawayanMedia\Attendance\Import\Parsers\ParserResolver;
use RuntimeException;

class ServiceProvider extends BaseServiceProvider
{
    /**
     * Register the configuration and the package services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/attendance-devices.php', 'attendance-devices');

        $this->app->singleton(DriverManager::class, function ($app): DriverManager {
            return new DriverManager((array) config('attendance-devices.drivers', []));
        });

        $this->app->bind(DeviceRepository::class, fn ($app) => $app->make(
            $this->configured('repository'),
        ));

        $this->app->bind(PunchSink::class, fn ($app) => $app->make(
            $this->configured('sink'),
        ));

        $this->app->bind(DeviceSync::class, fn ($app): DeviceSync => new DeviceSync(
            $app->make(DriverManager::class),
            $app->make(PunchSink::class),
            $app->make(DeviceRepository::class),
            (array) config('attendance-devices.pull', []),
        ));

        $this->app->singleton(ParserResolver::class, fn (): ParserResolver => new ParserResolver);

        $this->app->bind(DeviceLogImporter::class, fn ($app): DeviceLogImporter => new DeviceLogImporter(
            $app->make(ParserResolver::class),
            $app->make(PunchSink::class),
            $app->make(DeviceRepository::class),
        ));

        $this->app->bind(MachineImport::class, fn ($app): MachineImport => new MachineImport(
            $app->make(ParserResolver::class),
        ));
    }

    /**
     * Load the ADMS routes, publishable config and console commands.
     */
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/routes/devices.php');

        $this->publishes([
            __DIR__.'/../config/attendance-devices.php' => config_path('attendance-devices.php'),
        ], 'attendance-devices-config');

        if ($this->app->runningInConsole()) {
            $this->commands([
                SyncDevicesCommand::class,
                AgentCommand::class,
                TestDeviceCommand::class,
                PruneDeviceEventsCommand::class,
            ]);
        }
    }

    /**
     * The host class bound to a package contract.
     */
    private function configured(string $key): string
    {
        $class = config("attendance-devices.contracts.{$key}");

        if (! is_string($class) || $class === '') {
            throw new RuntimeException(
                "Attendance devices: bind a class to [attendance-devices.contracts.{$key}] in config/attendance-devices.php."
            );
        }

        return $class;
    }
}
