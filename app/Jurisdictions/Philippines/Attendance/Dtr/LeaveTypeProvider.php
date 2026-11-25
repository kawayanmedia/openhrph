<?php

namespace App\Jurisdictions\Philippines\Attendance\Dtr;

use Illuminate\Support\Facades\DB;
use KawayanMedia\DtrPdf\Contract\LeaveTypeProviderInterface;

/**
 * Leave names and codes printed on the DTR.
 */
final class LeaveTypeProvider implements LeaveTypeProviderInterface
{
    public function name(int $leaveTypeId): string
    {
        return (string) ($this->row($leaveTypeId)->name ?? '');
    }

    public function code(int $leaveTypeId): string
    {
        return (string) ($this->row($leaveTypeId)->code ?? '');
    }

    private function row(int $leaveTypeId): ?object
    {
        if ($leaveTypeId <= 0) {
            return null;
        }

        return DB::table('leave_types')
            ->where('id', $leaveTypeId)
            ->whereNull('deleted_at')
            ->first();
    }
}
