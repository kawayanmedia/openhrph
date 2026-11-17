<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Append-only payroll audit trail: who processed, reopened or approved a run,
 * with the engine version and the totals at the time.
 */
class PayrollAuditEvent extends Model
{
    public $timestamps = false;

    protected $table = 'payroll_audit_events';

    protected $fillable = [
        'company_id',
        'payroll_id',
        'event',
        'actor_id',
        'reason',
        'engine_version',
        'gross_amount',
        'net_amount',
        'payload',
        'created_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'created_at' => 'datetime',
    ];

    public function payroll()
    {
        return $this->belongsTo(Payroll::class);
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
