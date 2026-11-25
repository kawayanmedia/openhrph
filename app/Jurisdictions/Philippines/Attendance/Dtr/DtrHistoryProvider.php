<?php

namespace App\Jurisdictions\Philippines\Attendance\Dtr;

use App\Models\Timesheet;
use Carbon\CarbonImmutable;
use KawayanMedia\DtrPdf\Contract\DtrHistoryProviderInterface;
use KawayanMedia\DtrPdf\Model\RawDtrDay;

/**
 * Next day's log, used by overnight and 24-hour shifts.
 */
final class DtrHistoryProvider implements DtrHistoryProviderInterface
{
    public function nextDay(string $employeeId, string $date): ?RawDtrDay
    {
        $next = CarbonImmutable::parse($date)->addDay()->toDateString();

        $row = Timesheet::query()
            ->where('employee_id', $employeeId)
            ->whereDate('log_date', $next)
            ->first();

        if ($row === null) {
            return null;
        }

        return new RawDtrDay(
            date: (string) $row->log_date?->toDateString(),
            amIn: (string) $row->am_login,
            amOut: (string) $row->am_logout,
            pmIn: (string) $row->pm_login,
            pmOut: (string) $row->pm_logout,
            otIn: (string) $row->ot_login,
            otOut: (string) $row->ot_logout,
        );
    }
}
