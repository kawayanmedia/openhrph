<?php

namespace KawayanMedia\Attendance\Devices;

/**
 * The result of one device sync attempt.
 */
final readonly class SyncOutcome
{
    public const STATUS_OK = 'ok';

    public const STATUS_FAILED = 'failed';

    public const STATUS_SKIPPED = 'skipped';

    /**
     * @param  array<string, mixed>  $summary
     */
    public function __construct(
        public string $status,
        public string $message = '',
        public int $fetched = 0,
        public array $summary = [],
        public ?string $cursor = null,
    ) {}

    /**
     * @param  array<string, mixed>  $summary
     */
    public static function ok(int $fetched, array $summary, ?string $cursor): self
    {
        return new self(self::STATUS_OK, '', $fetched, $summary, $cursor);
    }

    public static function failed(string $message): self
    {
        return new self(self::STATUS_FAILED, $message);
    }

    public static function skipped(string $message): self
    {
        return new self(self::STATUS_SKIPPED, $message);
    }

    public function succeeded(): bool
    {
        return $this->status === self::STATUS_OK;
    }
}
