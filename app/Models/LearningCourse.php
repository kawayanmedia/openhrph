<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class LearningCourse extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use SoftDeletes;

    protected $table = 'training_courses';

    protected $fillable = [
        'company_id',
        'code',
        'title',
        'provider',
        'category',
        'description',
        'duration_hours',
        'cost',
        'is_active',
    ];

    protected $casts = [
        'duration_hours' => 'decimal:2',
        'cost' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    /**
     * @return HasMany<TrainingSession, $this>
     */
    public function sessions(): HasMany
    {
        return $this->hasMany(TrainingSession::class);
    }
}
