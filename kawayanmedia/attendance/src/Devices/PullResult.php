<?php

namespace KawayanMedia\Attendance\Devices;

use Carbon\CarbonImmutable;

final readonly class PullResult
{
    /**
     * @param  array<int, PunchRecord>  $records
     * @param  array<int, string>  $warnings
     */
    public function __construct(
        public array $records = [],
        public ?string $cursor = null,
        public array $warnings = [],
        public int $fetched = 0,
    ) {}

    public static function empty(array $warnings = []): self
    {
        return new self([], null, $warnings);
    }

    /**
     * Build a result and advance the cursor to the newest punch.
     *
     * @param  array<int, PunchRecord>  $records
     * @param  array<int, string>  $warnings
     */
    public static function fromRecords(array $records, array $warnings = []): self
    {
        $latest = null;

        foreach ($records as $record) {
            $moment = $record->moment();

            if ($latest === null || $moment->greaterThan($latest)) {
                $latest = $moment;
            }
        }

        return new self(
            records: array_values($records),
            cursor: $latest?->utc()->toIso8601String(),
            warnings: $warnings,
            fetched: count($records),
        );
    }

    public function newest(): ?CarbonImmutable
    {
        return $this->cursor === null ? null : CarbonImmutable::parse($this->cursor);
    }
}
