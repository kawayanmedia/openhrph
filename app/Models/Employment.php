<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A private-sector employment period for an employee.
 */
class Employment extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use SoftDeletes;

    public const CLASSIFICATION_REGULAR = 'regular';

    public const CLASSIFICATION_PROBATIONARY = 'probationary';

    public const CLASSIFICATION_FIXED_TERM = 'fixed_term';

    public const CLASSIFICATION_PROJECT = 'project';

    public const CLASSIFICATION_SEASONAL = 'seasonal';

    public const CLASSIFICATION_PART_TIME = 'part_time';

    public const CLASSIFICATION_CONTRACTUAL = 'contractual';

    public const CLASSIFICATIONS = [
        self::CLASSIFICATION_REGULAR,
        self::CLASSIFICATION_PROBATIONARY,
        self::CLASSIFICATION_FIXED_TERM,
        self::CLASSIFICATION_PROJECT,
        self::CLASSIFICATION_SEASONAL,
        self::CLASSIFICATION_PART_TIME,
        self::CLASSIFICATION_CONTRACTUAL,
    ];

    public const STATUS_ACTIVE = 'active';

    public const STATUS_ENDED = 'ended';

    public const STATUS_SUSPENDED = 'suspended';

    protected $fillable = [
        'company_id',
        'employee_id',
        'employment_type_id',
        'classification',
        'start_date',
        'end_date',
        'probation_start',
        'probation_end',
        'regularization_date',
        'status',
        'contract_reference',
        'metadata',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'probation_start' => 'date',
        'probation_end' => 'date',
        'regularization_date' => 'date',
        'metadata' => 'array',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function employmentType(): BelongsTo
    {
        return $this->belongsTo(EmploymentType::class);
    }

    /**
     * @return HasMany<EmploymentContract, $this>
     */
    public function contracts(): HasMany
    {
        return $this->hasMany(EmploymentContract::class)->orderByDesc('version');
    }

    /**
     * @return HasMany<ProbationaryReview, $this>
     */
    public function probationaryReviews(): HasMany
    {
        return $this->hasMany(ProbationaryReview::class)->orderBy('review_date');
    }

    /**
     * @return HasMany<RegularizationDecision, $this>
     */
    public function regularizationDecisions(): HasMany
    {
        return $this->hasMany(RegularizationDecision::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function isProbationary(): bool
    {
        return $this->classification === self::CLASSIFICATION_PROBATIONARY;
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }
}
