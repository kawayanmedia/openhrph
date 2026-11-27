<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class RetirementCase extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use SoftDeletes;

    public const TYPE_OPTIONAL = 'optional';

    public const TYPE_COMPULSORY = 'compulsory';

    public const TYPE_EARLY = 'early';

    public const TYPE_MEDICAL = 'medical';

    public const TYPE_DEATH = 'death';

    public const TYPES = [
        self::TYPE_OPTIONAL,
        self::TYPE_COMPULSORY,
        self::TYPE_EARLY,
        self::TYPE_MEDICAL,
        self::TYPE_DEATH,
    ];

    public const STATUS_DRAFT = 'draft';

    public const STATUS_ELIGIBILITY_REVIEW = 'eligibility_review';

    public const STATUS_APPLIED = 'applied';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    public const TRANSITIONS = [
        self::STATUS_DRAFT => [self::STATUS_ELIGIBILITY_REVIEW, self::STATUS_APPLIED, self::STATUS_CANCELLED],
        self::STATUS_ELIGIBILITY_REVIEW => [self::STATUS_APPLIED, self::STATUS_DRAFT, self::STATUS_CANCELLED],
        self::STATUS_APPLIED => [self::STATUS_APPROVED, self::STATUS_CANCELLED],
        self::STATUS_APPROVED => [self::STATUS_COMPLETED],
        self::STATUS_COMPLETED => [],
        self::STATUS_CANCELLED => [],
    ];

    protected $fillable = [
        'company_id',
        'employee_id',
        'separation_case_id',
        'retirement_plan_id',
        'retirement_type',
        'status',
        'proposed_retirement_date',
        'eligibility_snapshot',
        'applied_at',
        'approved_at',
        'approved_by',
        'completed_at',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'proposed_retirement_date' => 'date',
        'eligibility_snapshot' => 'array',
        'applied_at' => 'datetime',
        'approved_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(RetirementPlan::class, 'retirement_plan_id');
    }

    public function separationCase(): BelongsTo
    {
        return $this->belongsTo(SeparationCase::class, 'separation_case_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * @return HasMany<RetirementCalculation, $this>
     */
    public function calculations(): HasMany
    {
        return $this->hasMany(RetirementCalculation::class);
    }

    /**
     * @return HasMany<RetirementDocument, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(RetirementDocument::class);
    }

    /**
     * @return HasMany<ExternalClaimTracking, $this>
     */
    public function externalClaims(): HasMany
    {
        return $this->hasMany(ExternalClaimTracking::class);
    }

    public function canTransitionTo(string $status): bool
    {
        return in_array($status, self::TRANSITIONS[$this->status] ?? [], true);
    }

    public function isClosed(): bool
    {
        return in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_CANCELLED], true);
    }
}
