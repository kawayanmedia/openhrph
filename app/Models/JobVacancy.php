<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use KawayanMedia\Government\Models\JobVacancy as BaseJobVacancy;

/**
 * Recruitment vacancy with requisition, position, publication and pipeline
 * details. Extends the package model so existing screens keep working.
 */
class JobVacancy extends BaseJobVacancy
{
    use BelongsToTenant;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_OPEN = 'open';

    public const STATUS_ON_HOLD = 'on_hold';

    public const STATUS_CLOSED = 'closed';

    public const STATUS_FILLED = 'filled';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'company_id',
        'plantilla_item_id',
        'requisition_id',
        'position_id',
        'employment_type_id',
        'location_id',
        'hiring_manager_employee_id',
        'title',
        'type',
        'summary',
        'description',
        'requirements',
        'salary',
        'salary_min',
        'salary_max',
        'shift',
        'is_active',
        'date',
        'published_at',
        'closes_at',
        'status',
        'channels',
        'criteria',
    ];

    protected $casts = [
        'date' => 'date',
        'published_at' => 'datetime',
        'closes_at' => 'date',
        'salary_min' => 'integer',
        'salary_max' => 'integer',
        'is_active' => 'boolean',
        'channels' => 'array',
        'criteria' => 'array',
    ];

    public function requisition(): BelongsTo
    {
        return $this->belongsTo(JobRequisition::class, 'requisition_id');
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    public function employmentType(): BelongsTo
    {
        return $this->belongsTo(EmploymentType::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function hiringManager(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'hiring_manager_employee_id');
    }

    /**
     * @return HasMany<Application, $this>
     */
    public function applications(): HasMany
    {
        return $this->hasMany(Application::class, 'job_vacancy_id');
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }
}
