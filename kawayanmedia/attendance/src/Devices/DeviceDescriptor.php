<?php

namespace KawayanMedia\Attendance\Devices;

use Carbon\CarbonImmutable;

/**
 * Everything a driver needs to reach a device, independent of Eloquent.
 */
final readonly class DeviceDescriptor
{
    /**
     * @param  array<string, mixed>  $credentials  Decrypted username/password/login_id values.
     * @param  array<string, mixed>  $config  Driver-specific options (paths, field maps, direction maps).
     */
    public function __construct(
        public int $id,
        public int $tenantId,
        public string $name,
        public string $protocol,
        public ?string $serialNumber = null,
        public ?string $host = null,
        public ?int $port = null,
        public string $timezone = 'Asia/Manila',
        public array $credentials = [],
        public array $config = [],
        public ?string $cursor = null,
        public bool $syncEnabled = true,
        public bool $isActive = true,
        public ?string $pushKeyHash = null,
        public ?CarbonImmutable $lastSyncedAt = null,
        public int $syncIntervalMinutes = 5,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (int) ($data['id'] ?? 0),
            tenantId: (int) ($data['tenant_id'] ?? $data['company_id'] ?? 0),
            name: (string) ($data['name'] ?? ''),
            protocol: (string) ($data['protocol'] ?? 'generic_json'),
            serialNumber: isset($data['serial_number']) ? (string) $data['serial_number'] : null,
            host: isset($data['host']) ? (string) $data['host'] : (isset($data['ip_address']) ? (string) $data['ip_address'] : null),
            port: isset($data['port']) ? (int) $data['port'] : null,
            timezone: (string) ($data['timezone'] ?? 'Asia/Manila'),
            credentials: is_array($data['credentials'] ?? null) ? $data['credentials'] : [],
            config: is_array($data['config'] ?? null) ? $data['config'] : [],
            cursor: isset($data['cursor']) ? (string) $data['cursor'] : (isset($data['last_pull_cursor']) ? (string) $data['last_pull_cursor'] : null),
            syncEnabled: (bool) ($data['sync_enabled'] ?? true),
            isActive: (bool) ($data['is_active'] ?? true),
            pushKeyHash: isset($data['push_key']) ? (string) $data['push_key'] : null,
            lastSyncedAt: isset($data['last_synced_at']) ? CarbonImmutable::parse((string) $data['last_synced_at']) : null,
            syncIntervalMinutes: (int) ($data['sync_interval_minutes'] ?? 5),
        );
    }

    public function withCursor(?string $cursor): self
    {
        return new self(
            id: $this->id,
            tenantId: $this->tenantId,
            name: $this->name,
            protocol: $this->protocol,
            serialNumber: $this->serialNumber,
            host: $this->host,
            port: $this->port,
            timezone: $this->timezone,
            credentials: $this->credentials,
            config: $this->config,
            cursor: $cursor,
            syncEnabled: $this->syncEnabled,
            isActive: $this->isActive,
            pushKeyHash: $this->pushKeyHash,
            lastSyncedAt: $this->lastSyncedAt,
            syncIntervalMinutes: $this->syncIntervalMinutes,
        );
    }

    /**
     * Whether the device should be pulled on this run.
     */
    public function isDue(?int $defaultInterval = null): bool
    {
        if ($this->lastSyncedAt === null) {
            return true;
        }

        $interval = max(1, $defaultInterval ?? $this->syncIntervalMinutes);

        return $this->lastSyncedAt->lessThanOrEqualTo(CarbonImmutable::now()->subMinutes($interval));
    }

    public function credential(string $key, ?string $default = null): ?string
    {
        $value = $this->credentials[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : $default;
    }

    public function option(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }

    /**
     * Base URL with the protocol's default port applied.
     */
    public function baseUrl(bool $https = false): string
    {
        $scheme = $https ? 'https' : 'http';
        $host = (string) $this->host;
        $port = $this->port;

        if ($port === null || ($https && $port === 443) || (! $https && $port === 80)) {
            return "{$scheme}://{$host}";
        }

        return "{$scheme}://{$host}:{$port}";
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->tenantId,
            'name' => $this->name,
            'protocol' => $this->protocol,
            'serial_number' => $this->serialNumber,
            'host' => $this->host,
            'port' => $this->port,
            'timezone' => $this->timezone,
            'credentials' => $this->credentials,
            'config' => $this->config,
            'cursor' => $this->cursor,
            'sync_enabled' => $this->syncEnabled,
            'is_active' => $this->isActive,
            'last_synced_at' => $this->lastSyncedAt?->toDateTimeString(),
            'sync_interval_minutes' => $this->syncIntervalMinutes,
        ];
    }
}
