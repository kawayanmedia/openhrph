<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class SeparationCase extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use SoftDeletes;

    public const TYPE_RESIGNATION = 'resignation';

    public const TYPE_TERMINATION = 'termination';

    public const TYPE_REDUNDANCY = 'redundancy';

    public const TYPE_END_OF_CONTRACT = 'end_of_contract';

    public const TYPE_RETIREMENT = 'retirement';

    public const TYPE_DEATH = 'death';

    public const TYPE_OTHER = 'other';

    public const TYPES = [
        self::TYPE_RESIGNATION,
        self::TYPE_TERMINATION,
        self::TYPE_REDUNDANCY,
        self::TYPE_END_OF_CONTRACT,
        self::TYPE_RETIREMENT,
        self::TYPE_DEATH,
        self::TYPE_OTHER,
    ];

    public const STATUS_INITIATED = 'initiated';

    public const STATUS_REVIEW = 'review';

    public const STATUS_CLEARANCE_IN_PROGRESS = 'clearance_in_progress';

    public const STATUS_FINAL_PAY_REVIEW = 'final_pay_review';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_EFFECTIVE = 'effective';

    public const STATUS_CLOSED = 'closed';

    public const STATUS_CANCELLED = 'cancelled';

    public const TRANSITIONS = [
        self::STATUS_INITIATED => [self::STATUS_REVIEW, self::STATUS_CANCELLED],
        self::STATUS_REVIEW => [self::STATUS_CLEARANCE_IN_PROGRESS, self::STATUS_CANCELLED],
        self::STATUS_CLEARANCE_IN_PROGRESS => [self::STATUS_FINAL_PAY_REVIEW, self::STATUS_CANCELLED],
        self::STATUS_FINAL_PAY_REVIEW => [self::STATUS_APPROVED, self::STATUS_CANCELLED],
        self::STATUS_APPROVED => [self::STATUS_EFFECTIVE, self::STATUS_CANCELLED],
        self::STATUS_EFFECTIVE => [self::STATUS_CLOSED],
        self::STATUS_CLOSED => [],
        self::STATUS_CANCELLED => [],
    ];

    protected $fillable = [
        'company_id',
        'employee_id',
        'separation_type',
        'initiated_by',
        'proposed_effective_date',
        'effective_date',
        'notice_served_at',
        'status',
        'reason',
        'legal_review_reference',
        'workflow_instance_id',
        'approved_by',
        'approved_at',
        'metadata',
    ];

    protected $casts = [
        'proposed_effective_date' => 'date',
        'effective_date' => 'date',
        'notice_served_at' => 'datetime',
        'approved_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * @return HasMany<ClearanceTask, $this>
     */
    public function clearanceTasks(): HasMany
    {
        return $this->hasMany(ClearanceTask::class)->orderBy('unit');
    }

    public function finalSettlement(): HasOne
    {
        return $this->hasOne(FinalSettlement::class);
    }

    public function canTransitionTo(string $status): bool
    {
        return in_array($status, self::TRANSITIONS[$this->status] ?? [], true);
    }
}
