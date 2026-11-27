<?php

namespace KawayanMedia\Attendance\Devices;

use Carbon\CarbonImmutable;

/**
 * One punch read from a device or an export file.
 *
 * `direction` is 0 for a time-in, 1 for a time-out, or null when the vendor
 * does not report it. A null direction lets the recorder sequence in/out.
 */
final readonly class PunchRecord
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public string $devicePin,
        public CarbonImmutable $punchedAt,
        public ?int $direction = null,
        public ?string $employeeNumber = null,
        public array $raw = [],
    ) {}

    /**
     * The shape `AttendanceLogIngestor::ingest()` accepts.
     *
     * @return array<string, mixed>
     */
    public function toLogArray(?string $appTimezone = null): array
    {
        $moment = $this->punchedAt->setTimezone($appTimezone ?? (string) config('app.timezone', 'Asia/Manila'));

        $log = ['at' => $moment->format('Y-m-d H:i:s')];

        if ($this->devicePin !== '') {
            $log['biometric_id'] = $this->devicePin;
        }

        if ($this->employeeNumber !== null && $this->employeeNumber !== '') {
            $log['employee_number'] = $this->employeeNumber;
        }

        if ($this->direction !== null) {
            $log['type'] = $this->direction;
        }

        return $log;
    }

    public function moment(): CarbonImmutable
    {
        return $this->punchedAt;
    }
}
