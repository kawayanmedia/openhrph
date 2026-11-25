<?php

namespace App\Jurisdictions\Philippines\Attendance\Dtr;

use App\Models\Holiday as HolidayModel;
use KawayanMedia\DtrPdf\Contract\HolidayProviderInterface;
use KawayanMedia\DtrPdf\Model\Holiday;

/**
 * Company holidays for the printed DTR.
 *
 * Half-day holidays are recognised from the `type`/`holiday_type` text
 * ("half", "half-am", "halfam", …) because the table has no dedicated flag.
 */
final class HolidayProvider implements HolidayProviderInterface
{
    public function find(string $date): ?Holiday
    {
        $row = HolidayModel::query()->whereDate('date', $date)->first();

        if ($row === null) {
            return null;
        }

        $type = strtolower(trim((string) ($row->type ?? $row->holiday_type ?? '')));

        if (str_contains($type, 'half')) {
            $period = str_contains($type, 'am')
                ? 'am'
                : (str_contains($type, 'pm') ? 'pm' : '');

            return Holiday::halfDay((string) $row->name, $period);
        }

        return Holiday::fullDay((string) $row->name);
    }
}
