<?php

namespace App\Services\Attendance\Devices;

use App\Models\AttendanceDeviceEvent;
use App\Models\Device;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Contracts\Encryption\DecryptException;
use KawayanMedia\Attendance\Contracts\Devices\DeviceRepository;
use KawayanMedia\Attendance\Devices\DeviceDescriptor;
use KawayanMedia\Attendance\Devices\DeviceEvent;

/**
 * Eloquent implementation of the package's device registry contract.
 *
 * Queries deliberately ignore the tenant global scope: the serial number on an
 * inbound ADMS request is what identifies the tenant, and the scheduler must
 * reach every company's devices.
 */
final class EloquentDeviceRepository implements DeviceRepository
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function find(int $deviceId): ?DeviceDescriptor
    {
        if ($deviceId <= 0) {
            return null;
        }

        $device = Device::query()->withoutTenant()->find($deviceId);

        return $device === null ? null : $this->descriptorFor($device);
    }

    public function findBySerial(string $serial): ?DeviceDescriptor
    {
        $device = Device::findBySerial($serial);

        return $device === null ? null : $this->descriptorFor($device);
    }

    public function pullable(): iterable
    {
        foreach (Device::query()->withoutTenant()->pullable()->orderBy('id')->cursor() as $device) {
            yield $this->descriptorFor($device);
        }
    }

    public function runInTenant(int $tenantId, Closure $callback): mixed
    {
        return $tenantId > 0
            ? $this->tenant->run($tenantId, $callback)
            : $callback();
    }

    public function markSeen(int $deviceId): void
    {
        Device::query()->withoutTenant()->whereKey($deviceId)->first()?->markSeen();
    }

    /**
     * @param  array<string, mixed>  $summary
     */
    public function recordSync(
        int $deviceId,
        string $status,
        ?string $message = null,
        ?string $cursor = null,
        array $summary = [],
    ): void {
        $device = Device::query()->withoutTenant()->whereKey($deviceId)->first();

        if ($device === null) {
            return;
        }

        if ($status === Device::SYNC_OK) {
            $device->markSynced($cursor, $message);

            return;
        }

        $device->markSyncFailed($message ?? 'The sync failed.');
    }

    public function recordEvent(DeviceEvent $event): void
    {
        $attributes = [
            'company_id' => $event->tenantId,
            'device_id' => $event->deviceId,
            'serial_number' => $event->serialNumber,
            'protocol' => $event->protocol,
            'kind' => $event->kind,
            'endpoint' => $event->endpoint,
            'http_status' => $event->httpStatus,
            'records' => $event->records,
            'accepted' => $event->accepted,
            'duplicates' => $event->duplicates,
            'unmatched' => $event->unmatched,
            'message' => $event->message === null ? null : mb_substr($event->message, 0, 1000),
            'payload' => $event->payload,
            'occurred_at' => now(),
        ];

        $this->runInTenant(
            $event->tenantId,
            fn () => AttendanceDeviceEvent::query()->create($attributes),
        );
    }

    public function pruneEvents(int $olderThanDays): int
    {
        return AttendanceDeviceEvent::query()
            ->withoutTenant()
            ->where('occurred_at', '<', now()->subDays(max(1, $olderThanDays)))
            ->delete();
    }

    public function descriptorFor(Device $device): DeviceDescriptor
    {
        return new DeviceDescriptor(
            id: (int) $device->getKey(),
            tenantId: (int) $device->company_id,
            name: (string) $device->name,
            protocol: $device->protocolEnum()->value,
            serialNumber: $device->serial_number,
            host: $device->ip_address,
            port: $device->resolvedPort(),
            timezone: $device->resolvedTimezone(),
            credentials: $this->credentials($device),
            config: is_array($device->config) ? $device->config : [],
            cursor: $device->last_pull_cursor,
            syncEnabled: (bool) $device->sync_enabled,
            isActive: (bool) $device->is_active,
            pushKeyHash: $device->push_key,
            lastSyncedAt: $device->last_synced_at === null ? null : CarbonImmutable::parse($device->last_synced_at),
            syncIntervalMinutes: max(1, (int) ($device->sync_interval_minutes ?: 5)),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function credentials(Device $device): array
    {
        try {
            $credentials = $device->credentials;
        } catch (DecryptException) {
            // An APP_KEY rotation makes old ciphertext unreadable; the device
            // still works, the operator just has to re-enter the credentials.
            return [];
        }

        return is_array($credentials) ? $credentials : [];
    }
}
