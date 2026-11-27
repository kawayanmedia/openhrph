<?php

namespace KawayanMedia\Attendance\Contracts\Devices;

/**
 * The narrow slice of a ZKTeco client the pull driver needs.
 *
 * Declaring the surface instead of using `Rats\Zkteco\Lib\ZKTeco` directly
 * keeps the driver testable without opening a UDP socket.
 */
interface ZktecoClient
{
    public function connect(): bool;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function attendance(): array;

    public function serialNumber(): ?string;

    public function osVersion(): ?string;

    public function disconnect(): void;
}
