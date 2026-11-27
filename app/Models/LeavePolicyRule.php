<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Effective-dated eligibility dimensions layered on a leave policy.
 */
class LeavePolicyRule extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $fillable = [
        'company_id',
        'leave_policy_id',
        'employment_classification',
        'effective_from',
        'effective_until',
        'min_service_months',
        'notice_days',
        'max_consecutive_days',
        'requires_evidence',
        'allow_half_day',
        'is_paid',
        'eligibility',
    ];

    protected $casts = [
        'effective_from' => 'date',
        'effective_until' => 'date',
        'min_service_months' => 'integer',
        'notice_days' => 'integer',
        'max_consecutive_days' => 'integer',
        'requires_evidence' => 'boolean',
        'allow_half_day' => 'boolean',
        'is_paid' => 'boolean',
        'eligibility' => 'array',
    ];

    public function policy(): BelongsTo
    {
        return $this->belongsTo(LeavePolicy::class, 'leave_policy_id');
    }
}
