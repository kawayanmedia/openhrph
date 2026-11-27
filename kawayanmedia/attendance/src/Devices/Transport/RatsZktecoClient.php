<?php

namespace KawayanMedia\Attendance\Devices\Transport;

use KawayanMedia\Attendance\Contracts\Devices\ZktecoClient;
use Rats\Zkteco\Lib\ZKTeco;
use RuntimeException;

/**
 * Adapter over the `rats/zkteco` UDP client.
 */
final class RatsZktecoClient implements ZktecoClient
{
    private ?ZKTeco $client = null;

    public function __construct(
        private readonly string $host,
        private readonly int $port = 4370,
    ) {}

    public function connect(): bool
    {
        if (! function_exists('socket_create')) {
            throw new RuntimeException('The PHP sockets extension is required to pull ZKTeco devices over UDP.');
        }

        $this->client = new ZKTeco($this->host, $this->port);

        return (bool) $this->client->connect();
    }

    public function attendance(): array
    {
        return $this->requireClient()->getAttendance() ?: [];
    }

    public function serialNumber(): ?string
    {
        $serial = $this->requireClient()->serialNumber();

        return is_string($serial) && $serial !== '' ? $serial : null;
    }

    public function osVersion(): ?string
    {
        $version = $this->requireClient()->osVersion();

        return is_string($version) && $version !== '' ? $version : null;
    }

    public function disconnect(): void
    {
        if ($this->client === null) {
            return;
        }

        $this->client->disconnect();
        $this->client = null;
    }

    private function requireClient(): ZKTeco
    {
        return $this->client ??= new ZKTeco($this->host, $this->port);
    }
}
