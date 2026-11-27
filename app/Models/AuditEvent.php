<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Append-only audit trail for HR actions.
 */
class AuditEvent extends Model
{
    use BelongsToTenant;

    public const UPDATED_AT = null;

    protected $fillable = [
        'company_id',
        'actor_id',
        'action',
        'subject_type',
        'subject_id',
        'description',
        'before',
        'after',
        'metadata',
        'request_id',
        'ip_address',
        'user_agent',
        'occurred_at',
    ];

    protected $casts = [
        'before' => 'array',
        'after' => 'array',
        'metadata' => 'array',
        'occurred_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('Audit events are immutable.');
        });

        static::deleting(function (): never {
            throw new LogicException('Audit events are immutable.');
        });
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function subject()
    {
        return $this->morphTo();
    }
}
