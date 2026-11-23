<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A frozen attendance line for one payroll run.
 */
class PayrollAttendanceSnapshot extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'payroll_attendance_snapshots';

    protected $fillable = [
        'company_id',
        'payroll_id',
        'timesheet_id',
        'employee_id',
        'log_date',
        'shift_id',
        'day_type',
        'day_rate_code',
        'scheduled_minutes',
        'paid_minutes',
        'regular_minutes',
        'overtime_minutes',
        'night_minutes',
        'night_ot_minutes',
        'late_minutes',
        'undertime_minutes',
        'approved_ot_minutes',
        'rule_version',
        'is_absent',
        'is_leave',
        'is_paid_leave',
        'leave_fraction',
        'snapshot_at',
    ];

    protected $casts = [
        'log_date' => 'date',
        'snapshot_at' => 'datetime',
        'is_absent' => 'boolean',
        'is_leave' => 'boolean',
        'is_paid_leave' => 'boolean',
        'leave_fraction' => 'float',
    ];

    public function payroll(): BelongsTo
    {
        return $this->belongsTo(Payroll::class);
    }

    public function timesheet(): BelongsTo
    {
        return $this->belongsTo(Timesheet::class);
    }
}
