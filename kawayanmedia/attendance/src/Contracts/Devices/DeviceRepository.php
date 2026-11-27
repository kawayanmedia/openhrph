<?php

namespace KawayanMedia\Attendance\Contracts\Devices;

use Closure;
use KawayanMedia\Attendance\Devices\DeviceDescriptor;
use KawayanMedia\Attendance\Devices\DeviceEvent;

/**
 * The host application's device registry.
 *
 * Implemented by the application because device records, tenant scoping and
 * sync columns are Eloquent concerns; the package only needs this surface.
 */
interface DeviceRepository
{
    public function find(int $deviceId): ?DeviceDescriptor;

    /**
     * Resolve the device that reports the given serial over ADMS.
     */
    public function findBySerial(string $serial): ?DeviceDescriptor;

    /**
     * Every device the sync command may pull from, across all tenants.
     *
     * @return iterable<int, DeviceDescriptor>
     */
    public function pullable(): iterable;

    /**
     * Run a callback inside the tenant that owns the device.
     *
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    public function runInTenant(int $tenantId, Closure $callback): mixed;

    public function markSeen(int $deviceId): void;

    /**
     * @param  array<string, mixed>  $summary
     */
    public function recordSync(
        int $deviceId,
        string $status,
        ?string $message = null,
        ?string $cursor = null,
        array $summary = [],
    ): void;

    public function recordEvent(DeviceEvent $event): void;

    /**
     * Delete protocol events older than the given number of days.
     */
    public function pruneEvents(int $olderThanDays): int;
}
