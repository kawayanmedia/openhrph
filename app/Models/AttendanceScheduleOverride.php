<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A one-day exception to the assigned shift: a swap, an extra shift or a rest
 * day. An override is intentionally narrow so it cannot silently rewrite a
 * whole period.
 */
class AttendanceScheduleOverride extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'attendance_schedule_overrides';

    protected $fillable = [
        'company_id',
        'employee_id',
        'date',
        'shift_id',
        'is_rest_day',
        'reason',
    ];

    protected $casts = [
        'date' => 'date',
        'is_rest_day' => 'boolean',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(AttendanceShift::class, 'shift_id');
    }
}
