<?php

namespace App\Enums\Timesheet;

enum DayType: string
{
    case REGULAR_DAY = 'regular';

    case REST_DAY = 'rd';

    public function label(): string
    {
        return match ($this) {
            self::REGULAR_DAY => 'Regular Day',
            self::REST_DAY => 'Rest Day',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())

            ->mapWithKeys(fn (self $dayType) => [
                $dayType->value => $dayType->label(),
            ])->toArray();
    }
}
