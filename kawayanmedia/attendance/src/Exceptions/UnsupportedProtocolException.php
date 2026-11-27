<?php

namespace KawayanMedia\Attendance\Exceptions;

use RuntimeException;

class UnsupportedProtocolException extends RuntimeException
{
    public static function forProtocol(string $protocol): self
    {
        return new self("No biometric device driver is registered for protocol [{$protocol}]. Check config/attendance-devices.php.");
    }
}
