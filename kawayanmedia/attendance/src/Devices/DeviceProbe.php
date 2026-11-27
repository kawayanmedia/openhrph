<?php

namespace KawayanMedia\Attendance\Devices;

final readonly class DeviceProbe
{
    /**
     * @param  array<string, mixed>  $details
     */
    public function __construct(
        public bool $ok,
        public string $message,
        public array $details = [],
    ) {}

    /**
     * @param  array<string, mixed>  $details
     */
    public static function success(string $message, array $details = []): self
    {
        return new self(true, $message, $details);
    }

    /**
     * @param  array<string, mixed>  $details
     */
    public static function failure(string $message, array $details = []): self
    {
        return new self(false, $message, $details);
    }
}
