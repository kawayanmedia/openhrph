<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Immutable record of an employment movement (hire, promotion, transfer …).
 */
class EmploymentChange extends Model
{
    use BelongsToTenant;
    use HasFactory;

    public const TYPE_HIRE = 'hire';

    public const TYPE_PROMOTION = 'promotion';

    public const TYPE_TRANSFER = 'transfer';

    public const TYPE_DEMOTION = 'demotion';

    public const TYPE_REGULARIZATION = 'regularization';

    public const TYPE_DESIGNATION = 'designation';

    public const TYPE_COMPENSATION = 'compensation';

    public const TYPE_SEPARATION = 'separation';

    public const TYPE_OTHER = 'other';

    protected $fillable = [
        'company_id',
        'employee_id',
        'change_type',
        'effective_date',
        'from_position_id',
        'to_position_id',
        'from_organization_unit_id',
        'to_organization_unit_id',
        'from_supervisor_employee_id',
        'to_supervisor_employee_id',
        'reason',
        'approved_by',
        'workflow_instance_id',
        'metadata',
    ];

    protected $casts = [
        'effective_date' => 'date',
        'metadata' => 'array',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function fromPosition(): BelongsTo
    {
        return $this->belongsTo(Position::class, 'from_position_id');
    }

    public function toPosition(): BelongsTo
    {
        return $this->belongsTo(Position::class, 'to_position_id');
    }

    public function fromOrganizationUnit(): BelongsTo
    {
        return $this->belongsTo(OrganizationUnit::class, 'from_organization_unit_id');
    }

    public function toOrganizationUnit(): BelongsTo
    {
        return $this->belongsTo(OrganizationUnit::class, 'to_organization_unit_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
