<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Interview extends Model
{
    use BelongsToTenant;
    use HasFactory;

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_NO_SHOW = 'no_show';

    protected $fillable = [
        'company_id',
        'application_id',
        'scheduled_at',
        'stage',
        'status',
        'mode',
        'location',
        'panel',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'panel' => 'array',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    /**
     * @return HasMany<InterviewEvaluation, $this>
     */
    public function evaluations(): HasMany
    {
        return $this->hasMany(InterviewEvaluation::class);
    }
}
