<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PerformanceReview extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_SELF_ASSESSMENT = 'self_assessment';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_UNDER_REVIEW = 'under_review';

    public const STATUS_CALIBRATED = 'calibrated';

    public const STATUS_FINALIZED = 'finalized';

    public const STATUS_ACKNOWLEDGED = 'acknowledged';

    protected $fillable = [
        'company_id',
        'performance_cycle_id',
        'employee_id',
        'reviewer_employee_id',
        'status',
        'self_assessment',
        'supervisor_comments',
        'overall_rating',
        'finalized_at',
        'acknowledged_at',
    ];

    protected $casts = [
        'overall_rating' => 'decimal:2',
        'finalized_at' => 'datetime',
        'acknowledged_at' => 'datetime',
    ];

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(PerformanceCycle::class, 'performance_cycle_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'reviewer_employee_id');
    }

    /**
     * @return HasMany<PerformanceRating, $this>
     */
    public function ratings(): HasMany
    {
        return $this->hasMany(PerformanceRating::class);
    }

    public function isFinalized(): bool
    {
        return $this->finalized_at !== null;
    }
}
