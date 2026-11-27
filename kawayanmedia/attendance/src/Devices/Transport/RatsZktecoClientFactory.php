<?php

namespace KawayanMedia\Attendance\Devices\Transport;

use KawayanMedia\Attendance\Contracts\Devices\ZktecoClient;
use KawayanMedia\Attendance\Contracts\Devices\ZktecoClientFactory;

final class RatsZktecoClientFactory implements ZktecoClientFactory
{
    public function make(string $host, int $port): ZktecoClient
    {
        return new RatsZktecoClient($host, $port);
    }
}
