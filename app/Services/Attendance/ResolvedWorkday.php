<?php

namespace App\Services\Attendance;

/**
 * The shift that applies to one employee on one workday.
 *
 * Segments are paid working spans in minutes-of-the-workday (an overnight
 * shift runs from e.g. 1320 to 1800). Breaks are unpaid spans in the same
 * coordinate space. A rest day keeps a shift length as the overtime threshold
 * but reports `isRestDay()` so lateness and undertime are not raised.
 */
final class ResolvedWorkday
{
    /**
     * @param  list<array{0: int, 1: int}>  $segments
     * @param  list<array{0: int, 1: int}>  $breaks
     */
    public function __construct(
        public readonly string $date,
        public readonly string $name = 'Default schedule',
        public readonly array $segments = [],
        public readonly array $breaks = [],
        public readonly int $graceMinutes = 0,
        public readonly int $nightStartMinutes = 22 * 60,
        public readonly int $nightEndMinutes = 6 * 60,
        public readonly bool $isRestDay = false,
        public readonly bool $isHoliday = false,
        public readonly ?int $shiftId = null,
        public readonly string $source = 'default',
        public readonly int $defaultMinutes = 8 * 60,
    ) {}

    /**
     * Paid minutes in the shift, after unpaid breaks.
     */
    public function scheduledMinutes(): int
    {
        if ($this->segments === []) {
            return max(0, $this->defaultMinutes);
        }

        return max(0, TimeInterval::sum($this->paidSegments()));
    }

    /**
     * @return list<array{0: int, 1: int}>
     */
    public function paidSegments(): array
    {
        return TimeInterval::subtract($this->segments, $this->breaks);
    }

    public function isScheduledWorkday(): bool
    {
        return ! $this->isRestDay && ! $this->isHoliday;
    }

    public function shiftStart(): ?int
    {
        return $this->segments[0][0] ?? null;
    }

    public function shiftEnd(): ?int
    {
        if ($this->segments === []) {
            return null;
        }

        return $this->segments[count($this->segments) - 1][1];
    }

    public function crossesMidnight(): bool
    {
        $end = $this->shiftEnd();

        return $end !== null && $end > 24 * 60;
    }

    /**
     * Night intervals for one workday. A window that ends before it starts
     * (22:00-06:00) spans midnight and is returned as two intervals.
     *
     * @return list<array{0: int, 1: int}>
     */
    public function nightIntervals(): array
    {
        $start = $this->nightStartMinutes;
        $end = $this->nightEndMinutes;

        if ($end > $start) {
            return [[$start, $end]];
        }

        $intervals = [];

        // A window such as 22:00-06:00 repeats across the workday, so include
        // the previous evening and the following morning. That keeps a shift
        // starting at midnight eligible for the 00:00-06:00 portion.
        for ($offset = -1; $offset <= 1; $offset++) {
            $intervals[] = [$offset * 24 * 60 + $start, $offset * 24 * 60 + 24 * 60];
            $intervals[] = [$offset * 24 * 60, $offset * 24 * 60 + $end];
        }

        return $intervals;
    }
}
