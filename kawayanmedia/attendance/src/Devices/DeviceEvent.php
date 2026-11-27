<?php

namespace KawayanMedia\Attendance\Devices;

/**
 * A protocol or sync event to persist in the host audit trail.
 */
final readonly class DeviceEvent
{
    public const KIND_PUSH = 'push';

    public const KIND_PULL = 'pull';

    public const KIND_IMPORT = 'import';

    public const KIND_TEST = 'test';

    public const KIND_ERROR = 'error';

    /**
     * @param  array<string, mixed>|null  $payload
     */
    public function __construct(
        public int $tenantId,
        public ?int $deviceId,
        public string $kind,
        public ?string $serialNumber = null,
        public ?string $protocol = null,
        public ?string $endpoint = null,
        public ?int $httpStatus = null,
        public ?int $records = null,
        public ?int $accepted = null,
        public ?int $duplicates = null,
        public ?int $unmatched = null,
        public ?string $message = null,
        public ?array $payload = null,
    ) {}

    /**
     * @param  array<string, mixed>  $summary
     */
    public static function fromSummary(
        DeviceDescriptor $device,
        string $kind,
        array $summary,
        ?string $message = null,
        ?int $httpStatus = null,
        ?string $endpoint = null,
    ): self {
        return new self(
            tenantId: $device->tenantId,
            deviceId: $device->id ?: null,
            kind: $kind,
            serialNumber: $device->serialNumber,
            protocol: $device->protocol,
            endpoint: $endpoint,
            httpStatus: $httpStatus,
            records: isset($summary['records']) ? (int) $summary['records'] : null,
            accepted: isset($summary['accepted']) ? (int) $summary['accepted'] : null,
            duplicates: isset($summary['duplicates']) ? (int) $summary['duplicates'] : null,
            unmatched: isset($summary['unmatched']) ? (int) $summary['unmatched'] : null,
            message: $message,
        );
    }
}
