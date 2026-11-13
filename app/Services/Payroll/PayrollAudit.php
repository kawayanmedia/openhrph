<?php

namespace App\Services\Payroll;

use App\Models\Payroll;
use App\Models\PayrollAuditEvent;
use Illuminate\Support\Facades\Schema;

/**
 * Records the payroll control events the audit trail requires. Failures to
 * write an event never block payroll processing.
 */
class PayrollAudit
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public static function record(Payroll $payroll, string $event, ?string $reason = null, array $payload = []): void
    {
        if (! Schema::hasTable('payroll_audit_events')) {
            return;
        }

        PayrollAuditEvent::create([
            'company_id' => $payroll->company_id,
            'payroll_id' => $payroll->getKey(),
            'event' => $event,
            'actor_id' => auth()->id(),
            'reason' => $reason,
            'engine_version' => $payroll->engine_version,
            'gross_amount' => (int) $payroll->getRawOriginal('gross_amount'),
            'net_amount' => (int) $payroll->getRawOriginal('net_amount'),
            'payload' => $payload === [] ? null : $payload,
            'created_at' => now(),
        ]);
    }
}
