<?php

namespace App\Enums\Requests;

enum Status: string
{
    const PENDING = 'pending';

    const APPROVED = 'approved';

    const REJECTED = 'rejected';

    const CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::APPROVED => 'Approved',
            self::REJECTED => 'Rejected',
            self::CANCELLED => 'Cancelled',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())

            ->mapWithKeys(fn (self $status) => [
                $status->value => $status->label(),
            ])->toArray();
    }
}
