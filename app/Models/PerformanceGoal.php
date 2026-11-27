<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PerformanceGoal extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_AGREED = 'agreed';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'company_id',
        'performance_cycle_id',
        'employee_id',
        'parent_goal_id',
        'title',
        'description',
        'measures',
        'weight',
        'target',
        'status',
        'due_date',
    ];

    protected $casts = [
        'weight' => 'decimal:2',
        'due_date' => 'date',
    ];

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(PerformanceCycle::class, 'performance_cycle_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_goal_id');
    }
}
