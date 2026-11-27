<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An effective-dated placement of an employee into a position and unit.
 */
class PositionAssignment extends Model
{
    use BelongsToTenant;
    use HasFactory;

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_ENDED = 'ended';

    public const TYPE_REGULAR = 'regular';

    public const TYPE_PROBATIONARY = 'probationary';

    public const TYPE_CONTRACTUAL = 'contractual';

    public const TYPE_CASUAL = 'casual';

    public const TYPE_JOB_ORDER = 'job_order';

    public const TYPE_DESIGNATION = 'designation';

    public const TYPE_ACTING = 'acting';

    public const TYPE_OIC = 'officer_in_charge';

    public const TYPE_DETAIL = 'detail';

    public const TYPES = [
        self::TYPE_REGULAR,
        self::TYPE_PROBATIONARY,
        self::TYPE_CONTRACTUAL,
        self::TYPE_CASUAL,
        self::TYPE_JOB_ORDER,
        self::TYPE_DESIGNATION,
        self::TYPE_ACTING,
        self::TYPE_OIC,
        self::TYPE_DETAIL,
    ];

    protected $fillable = [
        'company_id',
        'employee_id',
        'position_id',
        'organization_unit_id',
        'department_id',
        'office_id',
        'location_id',
        'cost_center_id',
        'supervisor_employee_id',
        'employment_type_id',
        'assignment_type',
        'status',
        'effective_from',
        'effective_until',
        'approved_by',
        'approved_at',
        'reference',
        'notes',
    ];

    protected $casts = [
        'effective_from' => 'date',
        'effective_until' => 'date',
        'approved_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (PositionAssignment $assignment): void {
            $assignment->status = match (true) {
                $assignment->effective_until !== null && $assignment->effective_until->lt(now()->startOfDay()) => self::STATUS_ENDED,
                $assignment->effective_from !== null && $assignment->effective_from->isFuture() => self::STATUS_SCHEDULED,
                default => self::STATUS_ACTIVE,
            };
        });
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

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

    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class);
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'supervisor_employee_id');
    }

    public function employmentType(): BelongsTo
    {
        return $this->belongsTo(EmploymentType::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function scopeCurrent(Builder $query): Builder
    {
        return $query
            ->where('effective_from', '<=', now()->toDateString())
            ->where(fn (Builder $query) => $query->whereNull('effective_until')
                ->orWhere('effective_until', '>=', now()->toDateString()));
    }

    /**
     * Approximate overlap detection used by the service and UI previews.
     */
    public function overlapsDateRange(string $from, ?string $until): bool
    {
        $until ??= '9999-12-31';
        $existingUntil = $this->effective_until?->toDateString() ?? '9999-12-31';

        return $this->effective_from->toDateString() <= $until
            && $existingUntil >= $from;
    }
}
