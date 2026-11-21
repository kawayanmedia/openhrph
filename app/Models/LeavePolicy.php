<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * The company's rule for one leave type: entitlement, accrual, carry-over,
 * expiry and eligibility.
 */
class LeavePolicy extends Model
{
    use BelongsToTenant;
    use SoftDeletes;

    protected $table = 'leave_policies';

    protected $fillable = [
        'company_id',
        'leave_type_id',
        'entitlement_days',
        'accrual',
        'prorate_first_year',
        'carry_over_days',
        'expiry_months',
        'half_day_allowed',
        'is_paid',
        'min_service_months',
        'requires_evidence',
        'is_active',
    ];

    protected $casts = [
        'entitlement_days' => 'float',
        'carry_over_days' => 'float',
        'prorate_first_year' => 'boolean',
        'half_day_allowed' => 'boolean',
        'is_paid' => 'boolean',
        'requires_evidence' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function leaveType()
    {
        return $this->belongsTo(Leave::class, 'leave_type_id');
    }
}
