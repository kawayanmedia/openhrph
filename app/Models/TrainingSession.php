<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrainingSession extends Model
{
    use BelongsToTenant;
    use HasFactory;

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'company_id',
        'training_course_id',
        'starts_at',
        'ends_at',
        'location',
        'capacity',
        'cost',
        'status',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'capacity' => 'integer',
        'cost' => 'decimal:2',
    ];

    public function course(): BelongsTo
    {
        return $this->belongsTo(LearningCourse::class, 'training_course_id');
    }

    /**
     * @return HasMany<TrainingEnrollment, $this>
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(TrainingEnrollment::class);
    }
}
