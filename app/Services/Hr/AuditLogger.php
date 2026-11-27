<?php

namespace App\Services\Hr;

use App\Models\AuditEvent;
use App\Support\Tenancy\TenantContext;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Append-only audit writer for HR actions outside payroll runs.
 */
class AuditLogger
{
    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @param  array<string, mixed>  $metadata
     */
    public function record(
        string $action,
        ?Model $subject = null,
        array $before = [],
        array $after = [],
        array $metadata = [],
        ?int $companyId = null,
        ?string $description = null,
        ?DateTimeInterface $occurredAt = null,
    ): ?AuditEvent {
        if (! Schema::hasTable('audit_events')) {
            return null;
        }

        $companyId ??= $subject?->getAttribute('company_id') !== null
            ? (int) $subject->getAttribute('company_id')
            : app(TenantContext::class)->id();

        if ($companyId === null) {
            return null;
        }

        $request = $this->requestPayload();

        return AuditEvent::withoutTenant()->create([
            'company_id' => $companyId,
            'actor_id' => $this->actorId(),
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'description' => $description,
            'before' => $before === [] ? null : $before,
            'after' => $after === [] ? null : $after,
            'metadata' => $metadata === [] ? null : $metadata,
            'request_id' => $request['request_id'],
            'ip_address' => $request['ip_address'],
            'user_agent' => $request['user_agent'],
            'occurred_at' => $occurredAt ?? now(),
        ]);
    }

    protected function actorId(): ?int
    {
        return Auth::id() ?? Auth::guard('nova')->id();
    }

    /**
     * @return array{request_id: string|null, ip_address: string|null, user_agent: string|null}
     */
    protected function requestPayload(): array
    {
        if (! app()->bound('request')) {
            return ['request_id' => null, 'ip_address' => null, 'user_agent' => null];
        }

        $request = request();

        return [
            'request_id' => $request->header('X-Request-Id') ?? ($request->hasSession() ? $request->session()->getId() : (string) Str::uuid()),
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 255, ''),
        ];
    }
}
