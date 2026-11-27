<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class DevelopmentPlan extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'company_id',
        'employee_id',
        'performance_cycle_id',
        'title',
        'goals',
        'status',
        'due_date',
    ];

    protected $casts = [
        'due_date' => 'date',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(PerformanceCycle::class, 'performance_cycle_id');
    }
}
