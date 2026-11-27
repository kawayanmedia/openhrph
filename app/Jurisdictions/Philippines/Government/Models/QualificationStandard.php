<?php

namespace App\Jurisdictions\Philippines\Government\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Position;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Versioned CSC/agency qualification standard for a position.
 */
class QualificationStandard extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_RETIRED = 'retired';

    protected $fillable = [
        'company_id',
        'position_id',
        'position_title',
        'version',
        'education',
        'training',
        'experience',
        'eligibility',
        'license',
        'training_hours',
        'experience_years',
        'requires_eligibility',
        'requires_license',
        'effective_from',
        'effective_until',
        'status',
        'source_reference',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'version' => 'integer',
        'training_hours' => 'integer',
        'experience_years' => 'decimal:2',
        'requires_eligibility' => 'boolean',
        'requires_license' => 'boolean',
        'effective_from' => 'date',
        'effective_until' => 'date',
        'approved_at' => 'datetime',
    ];

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }
}
