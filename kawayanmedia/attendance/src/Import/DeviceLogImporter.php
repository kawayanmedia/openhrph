<?php

namespace KawayanMedia\Attendance\Import;

use KawayanMedia\Attendance\Contracts\Devices\DeviceRepository;
use KawayanMedia\Attendance\Contracts\Devices\PunchSink;
use KawayanMedia\Attendance\Devices\DeviceDescriptor;
use KawayanMedia\Attendance\Devices\DeviceEvent;
use KawayanMedia\Attendance\Import\Parsers\ParserResolver;

/**
 * Imports an uploaded device export through the same sink as live devices.
 */
final class DeviceLogImporter
{
    public function __construct(
        private readonly ParserResolver $parsers,
        private readonly PunchSink $sink,
        private readonly DeviceRepository $repository,
    ) {}

    /**
     * @return array{records: int, parser: string, accepted: int, duplicates: int, unmatched: int, invalid: int, rejected: int, results: array<int, mixed>}
     */
    public function import(DeviceDescriptor $device, string $contents, string $filename): array
    {
        $parser = $this->parsers->resolve($filename, $contents);
        $records = $parser->parse($contents, $device->timezone);

        $summary = $this->repository->runInTenant(
            $device->tenantId,
            fn (): array => $this->sink->ingest($records, 'device_import', $device),
        );

        $summary['records'] = count($records);
        $summary['parser'] = $parser->name();

        $this->repository->recordEvent(DeviceEvent::fromSummary(
            $device,
            DeviceEvent::KIND_IMPORT,
            $summary,
            'Imported '.$filename.' with the '.$parser->name().' parser.',
        ));

        return $summary;
    }
}
