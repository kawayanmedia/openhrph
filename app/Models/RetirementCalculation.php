<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class RetirementCalculation extends Model
{
    use BelongsToTenant;
    use HasFactory;

    public const TYPE_RETIREMENT_BENEFIT = 'retirement_benefit';

    public const TYPE_TERMINAL_LEAVE = 'terminal_leave';

    public const TYPES = [
        self::TYPE_RETIREMENT_BENEFIT,
        self::TYPE_TERMINAL_LEAVE,
    ];

    public const STATUS_DRAFT = 'draft';

    public const STATUS_REVIEWED = 'reviewed';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'company_id',
        'retirement_case_id',
        'calculation_type',
        'rule_version',
        'inputs',
        'outputs',
        'amount',
        'status',
        'computed_by',
        'computed_at',
        'reviewed_by',
        'reviewed_at',
        'reviewer_notes',
    ];

    protected $casts = [
        'inputs' => 'array',
        'outputs' => 'array',
        'amount' => 'decimal:2',
        'computed_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $calculation): void {
            if ($calculation->getOriginal('status') === self::STATUS_APPROVED) {
                throw new LogicException('Approved retirement calculations are immutable.');
            }
        });

        static::deleting(function (self $calculation): void {
            if ($calculation->status === self::STATUS_APPROVED) {
                throw new LogicException('Approved retirement calculations cannot be deleted.');
            }
        });
    }

    public function retirementCase(): BelongsTo
    {
        return $this->belongsTo(RetirementCase::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }
}
