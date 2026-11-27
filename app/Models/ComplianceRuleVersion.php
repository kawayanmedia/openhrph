<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class ComplianceRuleVersion extends Model
{
    use BelongsToTenant;
    use HasFactory;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_REVIEWED = 'reviewed';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'company_id',
        'compliance_rule_id',
        'version',
        'configuration',
        'status',
        'reviewed_by',
        'reviewed_at',
        'approved_by',
        'approved_at',
        'change_summary',
    ];

    protected $casts = [
        'configuration' => 'array',
        'reviewed_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $version): void {
            if ($version->getOriginal('status') === self::STATUS_APPROVED) {
                throw new LogicException('Approved compliance rule versions are immutable.');
            }
        });

        static::deleting(function (self $version): void {
            if ($version->status === self::STATUS_APPROVED) {
                throw new LogicException('Approved compliance rule versions cannot be deleted.');
            }
        });
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(ComplianceRule::class, 'compliance_rule_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
