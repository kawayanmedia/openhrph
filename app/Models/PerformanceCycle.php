<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PerformanceCycle extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_CALIBRATION = 'calibration';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'company_id',
        'name',
        'sector',
        'template_version',
        'period_start',
        'period_end',
        'status',
        'rating_scale',
        'eligibility',
        'created_by',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'rating_scale' => 'array',
        'eligibility' => 'array',
    ];

    /**
     * @return HasMany<PerformanceGoal, $this>
     */
    public function goals(): HasMany
    {
        return $this->hasMany(PerformanceGoal::class, 'performance_cycle_id');
    }

    /**
     * @return HasMany<PerformanceReview, $this>
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(PerformanceReview::class, 'performance_cycle_id');
    }
}
