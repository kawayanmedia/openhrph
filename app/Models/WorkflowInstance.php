<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class WorkflowInstance extends Model
{
    use BelongsToTenant;
    use HasFactory;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_UNDER_REVIEW = 'under_review';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_RETURNED = 'returned_for_revision';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_COMPLETED = 'completed';

    protected $fillable = [
        'company_id',
        'workflow_definition_id',
        'definition_version',
        'definition_snapshot',
        'subject_type',
        'subject_id',
        'status',
        'current_step',
        'initiated_by',
        'submitted_at',
        'due_at',
        'completed_at',
        'metadata',
    ];

    protected $casts = [
        'definition_version' => 'integer',
        'definition_snapshot' => 'array',
        'metadata' => 'array',
        'submitted_at' => 'datetime',
        'due_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function definition(): BelongsTo
    {
        return $this->belongsTo(WorkflowDefinition::class, 'workflow_definition_id');
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return HasMany<WorkflowTask, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(WorkflowTask::class)->orderBy('step_order');
    }

    /**
     * @return HasMany<WorkflowDecision, $this>
     */
    public function decisions(): HasMany
    {
        return $this->hasMany(WorkflowDecision::class)->orderBy('decided_at');
    }

    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }

    public function isOpen(): bool
    {
        return in_array($this->status, [
            self::STATUS_DRAFT,
            self::STATUS_SUBMITTED,
            self::STATUS_UNDER_REVIEW,
            self::STATUS_RETURNED,
        ], true);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', [
            self::STATUS_DRAFT,
            self::STATUS_SUBMITTED,
            self::STATUS_UNDER_REVIEW,
            self::STATUS_RETURNED,
        ]);
    }
}
