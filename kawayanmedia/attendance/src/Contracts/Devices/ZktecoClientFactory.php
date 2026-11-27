<?php

namespace KawayanMedia\Attendance\Contracts\Devices;

interface ZktecoClientFactory
{
    public function make(string $host, int $port): ZktecoClient;
}
