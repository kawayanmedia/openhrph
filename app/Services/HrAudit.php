<?php

namespace App\Services;

use App\Models\Employee;
use App\Services\Hr\AuditLogger;
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
        } else {
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

        app(AuditLogger::class)->record(
            action: 'hr.'.$event,
            subject: $employeeId === null ? null : Employee::withoutTenant()->find($employeeId),
            after: $payload,
            metadata: ['reason' => $reason, 'legacy_event' => $event],
            companyId: $companyId,
            description: $reason,
        );
    }
}
