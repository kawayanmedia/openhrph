<?php

namespace KawayanMedia\Attendance\Devices;

use KawayanMedia\Attendance\Contracts\Devices\Driver;
use KawayanMedia\Attendance\Exceptions\UnsupportedProtocolException;

/**
 * Resolves a device protocol to its driver.
 */
final class DriverManager
{
    /**
     * @param  array<string, class-string<Driver>|Driver>  $drivers
     */
    public function __construct(private array $drivers = []) {}

    public function has(string $protocol): bool
    {
        return array_key_exists($protocol, $this->drivers);
    }

    public function driverFor(string $protocol): Driver
    {
        if (! $this->has($protocol)) {
            throw UnsupportedProtocolException::forProtocol($protocol);
        }

        $driver = $this->drivers[$protocol];

        if ($driver instanceof Driver) {
            return $driver;
        }

        return $this->drivers[$protocol] = app($driver);
    }

    /**
     * @return array<int, string>
     */
    public function protocols(): array
    {
        return array_keys($this->drivers);
    }

    /**
     * Replace or add a driver at runtime, which keeps tests and applications
     * free to register a vendor driver without touching package config.
     */
    public function extend(string $protocol, Driver|string $driver): void
    {
        $this->drivers[$protocol] = $driver;
    }
}
