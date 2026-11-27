<?php

namespace KawayanMedia\Attendance\Devices;

use Carbon\CarbonImmutable;

/**
 * The time span a pull should read.
 *
 * Without a cursor the window starts `history_days` back, so a brand-new
 * device registration still picks up the recent backlog.
 */
final readonly class PullWindow
{
    public function __construct(
        public CarbonImmutable $from,
        public CarbonImmutable $to,
        public ?string $cursor = null,
        public int $limit = 5000,
    ) {}

    /**
     * @param  array<string, mixed>  $config
     */
    public static function forDevice(DeviceDescriptor $device, array $config = []): self
    {
        $timezone = $device->timezone !== '' ? $device->timezone : 'Asia/Manila';
        $timeout = max(1, (int) ($config['history_days'] ?? 7));
        $overlap = max(0, (int) ($config['overlap_minutes'] ?? 10));
        $limit = max(1, (int) ($config['max_records_per_run'] ?? 5000));

        $to = CarbonImmutable::now($timezone);
        $from = self::cursorMoment($device->cursor, $timezone)
            ?? $to->subDays($timeout);

        $from = $from->subMinutes($overlap);

        if ($from->greaterThan($to)) {
            $from = $to->subMinutes($overlap);
        }

        return new self($from, $to, $device->cursor, $limit);
    }

    private static function cursorMoment(?string $cursor, string $timezone): ?CarbonImmutable
    {
        $cursor = trim((string) $cursor);

        if ($cursor === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($cursor, $timezone);
        } catch (\Throwable) {
            return null;
        }
    }

    public function includes(CarbonImmutable $moment): bool
    {
        return $moment->betweenIncluded($this->from, $this->to);
    }
}
