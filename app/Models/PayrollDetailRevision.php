<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Immutable snapshot of a payroll's details, written before every
 * re-processing so a prior run can always be reproduced.
 */
class PayrollDetailRevision extends Model
{
    public $timestamps = false;

    protected $table = 'payroll_detail_revisions';

    protected $guarded = [];

    protected $casts = [
        'warnings' => 'array',
        'created_at' => 'datetime',
    ];

    public function payroll()
    {
        return $this->belongsTo(Payroll::class);
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
