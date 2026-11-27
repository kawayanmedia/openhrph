<?php

namespace KawayanMedia\Attendance\Devices;

use KawayanMedia\Attendance\Contracts\Devices\DeviceRepository;
use KawayanMedia\Attendance\Contracts\Devices\PunchSink;
use Throwable;

/**
 * Pulls one device and feeds what it returns into the punch sink.
 *
 * Every outcome is recorded on the device (last sync status and cursor) and as
 * an audit event, so a silent device is visible in the admin panel instead of
 * only in the log file.
 */
final class DeviceSync
{
    /**
     * @param  array<string, mixed>  $pullConfig
     */
    public function __construct(
        private readonly DriverManager $drivers,
        private readonly PunchSink $sink,
        private readonly DeviceRepository $repository,
        private readonly array $pullConfig = [],
    ) {}

    public function sync(DeviceDescriptor $device, bool $dryRun = false): SyncOutcome
    {
        if (! $device->isActive) {
            return SyncOutcome::skipped('The device is disabled.');
        }

        $driver = $this->drivers->driverFor($device->protocol);

        if (! $driver->canPull()) {
            return SyncOutcome::skipped("The {$driver->protocol()} protocol pushes logs; it cannot be pulled.");
        }

        if ($device->host === null || $device->host === '') {
            return $this->fail($device, 'The device has no host or IP address.');
        }

        $window = PullWindow::forDevice($device, $this->pullConfig);

        try {
            $result = $driver->pull($device, $window);
        } catch (Throwable $exception) {
            report($exception);

            return $this->fail($device, $exception->getMessage());
        }

        if ($dryRun) {
            return SyncOutcome::ok($result->fetched, ['dry_run' => true], $result->cursor);
        }

        try {
            $summary = $this->repository->runInTenant(
                $device->tenantId,
                fn (): array => $this->sink->ingest($result->records, 'device', $device),
            );
        } catch (Throwable $exception) {
            report($exception);

            return $this->fail($device, $exception->getMessage());
        }

        $cursor = $result->cursor ?? $device->cursor;
        $message = $result->warnings === [] ? null : implode(' ', $result->warnings);

        $this->repository->recordSync($device->id, SyncOutcome::STATUS_OK, $message, $cursor, $summary);

        $this->repository->recordEvent(DeviceEvent::fromSummary(
            $device,
            DeviceEvent::KIND_PULL,
            ['records' => $result->fetched] + $summary,
            $message,
        ));

        return SyncOutcome::ok($result->fetched, $summary, $cursor);
    }

    private function fail(DeviceDescriptor $device, string $message): SyncOutcome
    {
        $this->repository->recordSync($device->id, SyncOutcome::STATUS_FAILED, $message, $device->cursor);

        $this->repository->recordEvent(new DeviceEvent(
            tenantId: $device->tenantId,
            deviceId: $device->id ?: null,
            kind: DeviceEvent::KIND_ERROR,
            serialNumber: $device->serialNumber,
            protocol: $device->protocol,
            message: $message,
        ));

        return SyncOutcome::failed($message);
    }
}
