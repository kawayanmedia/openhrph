<?php

namespace KawayanMedia\Attendance\Import;

use KawayanMedia\Attendance\Contracts\AttendanceImport;
use KawayanMedia\Attendance\Devices\PunchRecord;
use KawayanMedia\Attendance\Import\Parsers\ParserResolver;
use RuntimeException;

/**
 * Reads a machine log export and groups it by employee and day.
 *
 * Unlike `FileImport`, which only understands one ZKTeco layout, this uses the
 * parser resolver, so ZKTeco DAT files, generic CSV files and the Hikvision and
 * Suprema exports all work. Use `DeviceLogImporter` when the punches should be
 * written straight into the timesheets.
 */
final class MachineImport implements AttendanceImport
{
    public ?string $filePath = null;

    public string $timezone = 'Asia/Manila';

    /** @var array<int, PunchRecord> */
    private array $records = [];

    public function __construct(
        private readonly ParserResolver $parsers = new ParserResolver,
    ) {}

    public function handle(): void
    {
        if (is_string($this->filePath) && $this->filePath !== '') {
            $this->process($this->filePath);
        }
    }

    /**
     * @return array<int, PunchRecord>
     */
    public function process(?string $path = null, ?string $timezone = null): array
    {
        $path ??= $this->filePath;

        if (! is_string($path) || ! is_file($path)) {
            throw new RuntimeException('The machine log file could not be found.');
        }

        $contents = (string) file_get_contents($path);
        $parser = $this->parsers->resolve(basename($path), $contents);

        $this->timezone = $timezone ?? $this->timezone;
        $this->records = $parser->parse($contents, $this->timezone);

        return $this->records;
    }

    /**
     * Group the parsed punches in the shape `FileImport::process()` returns.
     *
     * @return array<string, array<string, array<int, array<string, mixed>>>>
     */
    public function getTimesheets(): array
    {
        $timesheets = [];

        foreach ($this->records as $record) {
            $date = $record->punchedAt->toDateString();

            $timesheets[$record->devicePin][$date][] = [
                'employee_id' => $record->devicePin,
                'log_date' => $date,
                'time' => $record->punchedAt->format('H:i:s'),
                'log_type' => $record->direction,
            ];
        }

        return $timesheets;
    }

    /**
     * @return array<int, PunchRecord>
     */
    public function records(): array
    {
        return $this->records;
    }
}
