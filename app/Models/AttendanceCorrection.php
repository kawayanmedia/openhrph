<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AttendanceCorrection extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use SoftDeletes;

    public const TYPE_MISSING_PUNCH = 'missing_punch';

    public const TYPE_WRONG_TIME = 'wrong_time';

    public const TYPE_LATE = 'late';

    public const TYPE_UNDERTIME = 'undertime';

    public const TYPE_ABSENCE = 'absence';

    public const TYPE_OTHER = 'other';

    public const TYPES = [
        self::TYPE_MISSING_PUNCH,
        self::TYPE_WRONG_TIME,
        self::TYPE_LATE,
        self::TYPE_UNDERTIME,
        self::TYPE_ABSENCE,
        self::TYPE_OTHER,
    ];

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_APPLIED = 'applied';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'company_id',
        'employee_id',
        'attendance_punch_id',
        'timesheet_id',
        'workday_date',
        'correction_type',
        'original_values',
        'requested_values',
        'reason',
        'status',
        'requested_by',
        'reviewed_by',
        'reviewed_at',
        'applied_at',
        'workflow_instance_id',
    ];

    protected $casts = [
        'workday_date' => 'date',
        'original_values' => 'array',
        'requested_values' => 'array',
        'reviewed_at' => 'datetime',
        'applied_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function punch(): BelongsTo
    {
        return $this->belongsTo(AttendancePunch::class, 'attendance_punch_id');
    }

    public function timesheet(): BelongsTo
    {
        return $this->belongsTo(Timesheet::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * @return HasMany<AttendanceCorrectionDecision, $this>
     */
    public function decisions(): HasMany
    {
        return $this->hasMany(AttendanceCorrectionDecision::class)->orderBy('decided_at');
    }

    public function isOpen(): bool
    {
        return in_array($this->status, [self::STATUS_SUBMITTED, self::STATUS_APPROVED], true);
    }
}
