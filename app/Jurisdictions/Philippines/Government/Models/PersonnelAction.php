<?php

namespace App\Jurisdictions\Philippines\Government\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Document;
use App\Models\Employee;
use App\Models\Office;
use App\Models\Position;
use App\Models\User;
use App\Models\WorkflowInstance;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Promotion, transfer, reassignment, detail, designation and other
 * personnel actions. Completion applies an effective-dated placement while
 * preserving the prior appointment and service history.
 */
class PersonnelAction extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_UNDER_REVIEW = 'under_review';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    public const TRANSITIONS = [
        self::STATUS_DRAFT => [self::STATUS_SUBMITTED, self::STATUS_CANCELLED],
        self::STATUS_SUBMITTED => [self::STATUS_UNDER_REVIEW, self::STATUS_CANCELLED],
        self::STATUS_UNDER_REVIEW => [self::STATUS_APPROVED, self::STATUS_REJECTED, self::STATUS_CANCELLED],
        self::STATUS_APPROVED => [self::STATUS_COMPLETED, self::STATUS_CANCELLED],
        self::STATUS_REJECTED => [],
        self::STATUS_COMPLETED => [],
        self::STATUS_CANCELLED => [],
    ];

    protected $fillable = [
        'company_id',
        'employee_id',
        'action_type',
        'current_appointment_id',
        'target_plantilla_item_id',
        'target_position_id',
        'target_office_id',
        'reason',
        'proposed_effective_date',
        'effective_date',
        'status',
        'approved_by',
        'approved_at',
        'workflow_instance_id',
        'document_id',
        'metadata',
    ];

    protected $casts = [
        'proposed_effective_date' => 'date',
        'effective_date' => 'date',
        'approved_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function currentAppointment(): BelongsTo
    {
        return $this->belongsTo(AppointmentRecord::class, 'current_appointment_id');
    }

    public function targetPlantillaItem(): BelongsTo
    {
        return $this->belongsTo(PlantillaItem::class, 'target_plantilla_item_id');
    }

    public function targetPosition(): BelongsTo
    {
        return $this->belongsTo(Position::class, 'target_position_id');
    }

    public function targetOffice(): BelongsTo
    {
        return $this->belongsTo(Office::class, 'target_office_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(WorkflowInstance::class, 'workflow_instance_id');
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function canTransitionTo(string $status): bool
    {
        return in_array($status, self::TRANSITIONS[$this->status] ?? [], true);
    }
}
