<?php

namespace App\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Records HR changes that happen outside a payroll run (step increments,
 * loyalty awards, terminal leave). Payroll audit events require a payroll id,
 * so these employee-level changes get their own trail.
 */
final class HrAudit
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public static function record(
        int $companyId,
        string $event,
        ?int $employeeId = null,
        ?string $reason = null,
        array $payload = [],
    ): void {
        if (! Schema::hasTable('hr_audit_events')) {
            Log::info('hr.audit', [
                'company_id' => $companyId,
                'employee_id' => $employeeId,
                'event' => $event,
                'reason' => $reason,
                'payload' => $payload,
            ]);

            return;
        }

        DB::table('hr_audit_events')->insert([
            'company_id' => $companyId,
            'employee_id' => $employeeId,
            'event' => $event,
            'actor_id' => Auth::id(),
            'reason' => $reason,
            'payload' => $payload === [] ? null : json_encode($payload),
            'created_at' => now(),
        ]);
    }
}
