<?php

namespace App\Jurisdictions\Philippines\Attendance\Dtr;

use Illuminate\Support\Facades\DB;
use KawayanMedia\DtrPdf\Contract\ManualLogProviderInterface;

/**
 * Notes of the manual log entry a DTR day points at.
 */
final class ManualLogProvider implements ManualLogProviderInterface
{
    public function notes(?string $manualLogId): string
    {
        if ($manualLogId === null || $manualLogId === '') {
            return '';
        }

        return (string) DB::table('manual_log')
            ->where('id', $manualLogId)
            ->value('notes');
    }
}
