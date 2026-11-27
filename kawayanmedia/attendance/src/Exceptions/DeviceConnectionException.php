<?php

namespace KawayanMedia\Attendance\Exceptions;

use RuntimeException;

class DeviceConnectionException extends RuntimeException
{
    public static function unreachable(string $host, ?int $port, ?string $reason = null): self
    {
        $target = $port === null ? $host : "{$host}:{$port}";
        $suffix = $reason === null || $reason === '' ? '' : " ({$reason})";

        return new self("Could not connect to the device at {$target}{$suffix}.");
    }

    public static function rejected(string $reason): self
    {
        return new self("The device rejected the connection: {$reason}");
    }
}
