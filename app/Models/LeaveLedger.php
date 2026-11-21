<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * One immutable leave movement. The balance table is rebuilt from these rows.
 */
class LeaveLedger extends Model
{
    use BelongsToTenant;

    public const MOVEMENT_ACCRUAL = 'accrual';

    public const MOVEMENT_AVAILMENT = 'availment';

    public const MOVEMENT_ADJUSTMENT = 'adjustment';

    public const MOVEMENT_CARRY_OVER = 'carry_over';

    public const MOVEMENT_EXPIRY = 'expiry';

    public const MOVEMENT_MONETIZATION = 'monetization';

    public const MOVEMENT_REVERSAL = 'reversal';

    protected $table = 'leave_ledger';

    protected $fillable = [
        'company_id',
        'employee_id',
        'leave_type_id',
        'period_year',
        'movement',
        'days',
        'reference',
        'reason',
        'actor_id',
    ];

    protected $casts = [
        'days' => 'float',
        'period_year' => 'integer',
    ];

    public function leaveType()
    {
        return $this->belongsTo(Leave::class, 'leave_type_id');
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
