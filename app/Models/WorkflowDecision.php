<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Append-only approval decision.
 */
class WorkflowDecision extends Model
{
    use BelongsToTenant;

    public const UPDATED_AT = null;

    public const DECISION_APPROVE = 'approve';

    public const DECISION_REJECT = 'reject';

    public const DECISION_RETURN = 'return';

    public const DECISION_DELEGATE = 'delegate';

    public const DECISION_CANCEL = 'cancel';

    public const DECISION_COMMENT = 'comment';

    protected $fillable = [
        'company_id',
        'workflow_instance_id',
        'workflow_task_id',
        'actor_id',
        'decision',
        'comments',
        'delegated_to_id',
        'decided_at',
        'metadata',
    ];

    protected $casts = [
        'decided_at' => 'datetime',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('Workflow decisions are immutable.');
        });

        static::deleting(function (): never {
            throw new LogicException('Workflow decisions are immutable.');
        });
    }

    public function instance(): BelongsTo
    {
        return $this->belongsTo(WorkflowInstance::class, 'workflow_instance_id');
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(WorkflowTask::class, 'workflow_task_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
