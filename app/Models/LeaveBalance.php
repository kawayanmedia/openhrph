<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * Rebuildable projection of the leave ledger for one employee, type and year.
 */
class LeaveBalance extends Model
{
    use BelongsToTenant;

    protected $table = 'leave_balances';

    protected $fillable = [
        'company_id',
        'employee_id',
        'leave_type_id',
        'year',
        'accrued',
        'used',
        'carried',
        'expired',
        'adjusted',
        'balance',
    ];

    protected $casts = [
        'year' => 'integer',
        'accrued' => 'float',
        'used' => 'float',
        'carried' => 'float',
        'expired' => 'float',
        'adjusted' => 'float',
        'balance' => 'float',
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
