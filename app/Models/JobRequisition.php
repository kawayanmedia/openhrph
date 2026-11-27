<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Authorized request for new or replacement headcount.
 */
class JobRequisition extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_UNDER_REVIEW = 'under_review';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_ON_HOLD = 'on_hold';

    public const STATUS_CLOSED = 'closed';

    public const STATUS_CANCELLED = 'cancelled';

    public const TRANSITIONS = [
        self::STATUS_DRAFT => [self::STATUS_SUBMITTED, self::STATUS_CANCELLED],
        self::STATUS_SUBMITTED => [self::STATUS_UNDER_REVIEW, self::STATUS_CANCELLED],
        self::STATUS_UNDER_REVIEW => [self::STATUS_APPROVED, self::STATUS_REJECTED, self::STATUS_ON_HOLD, self::STATUS_CANCELLED],
        self::STATUS_ON_HOLD => [self::STATUS_UNDER_REVIEW, self::STATUS_CANCELLED],
        self::STATUS_APPROVED => [self::STATUS_CLOSED, self::STATUS_CANCELLED],
        self::STATUS_REJECTED => [],
        self::STATUS_CLOSED => [],
        self::STATUS_CANCELLED => [],
    ];

    public const REASON_NEW_HEADCOUNT = 'new_headcount';

    public const REASON_REPLACEMENT = 'replacement';

    public const REASON_EXPANSION = 'expansion';

    public const REASON_PROJECT = 'project';

    public const REASONS = [
        self::REASON_NEW_HEADCOUNT,
        self::REASON_REPLACEMENT,
        self::REASON_EXPANSION,
        self::REASON_PROJECT,
    ];

    protected $fillable = [
        'company_id',
        'position_id',
        'organization_unit_id',
        'department_id',
        'location_id',
        'requested_by',
        'employment_type_id',
        'reason',
        'headcount',
        'target_date',
        'salary_min',
        'salary_max',
        'justification',
        'status',
        'approved_by',
        'approved_at',
        'workflow_instance_id',
        'metadata',
    ];

    protected $casts = [
        'headcount' => 'integer',
        'target_date' => 'date',
        'salary_min' => 'integer',
        'salary_max' => 'integer',
        'approved_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    public function organizationUnit(): BelongsTo
    {
        return $this->belongsTo(OrganizationUnit::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function employmentType(): BelongsTo
    {
        return $this->belongsTo(EmploymentType::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * @return HasMany<JobVacancy, $this>
     */
    public function vacancies(): HasMany
    {
        return $this->hasMany(JobVacancy::class, 'requisition_id');
    }

    public function canTransitionTo(string $status): bool
    {
        return in_array($status, self::TRANSITIONS[$this->status] ?? [], true);
    }
}
