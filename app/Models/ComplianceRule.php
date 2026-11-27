<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class ComplianceRule extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_IN_REVIEW = 'in_review';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_RETIRED = 'retired';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_IN_REVIEW,
        self::STATUS_APPROVED,
        self::STATUS_RETIRED,
    ];

    protected $fillable = [
        'company_id',
        'compliance_source_id',
        'code',
        'name',
        'sector',
        'organization_type',
        'classification_code',
        'version',
        'effective_from',
        'effective_until',
        'status',
    ];

    protected $casts = [
        'effective_from' => 'date',
        'effective_until' => 'date',
    ];

    public function source(): BelongsTo
    {
        return $this->belongsTo(ComplianceSource::class, 'compliance_source_id');
    }

    /**
     * @return HasMany<ComplianceRuleVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(ComplianceRuleVersion::class);
    }

    public function isEffectiveOn(\DateTimeInterface|string $date): bool
    {
        $date = Carbon::parse($date)->toDateString();

        return $this->effective_from?->toDateString() <= $date
            && ($this->effective_until === null || $this->effective_until->toDateString() >= $date);
    }
}
