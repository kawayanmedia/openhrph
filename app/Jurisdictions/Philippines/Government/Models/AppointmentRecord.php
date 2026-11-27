<?php

namespace App\Jurisdictions\Philippines\Government\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Employee;
use App\Models\Office;
use App\Models\Position;
use App\Models\User;
use App\Models\WorkflowInstance;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A government appointment. Issuance, service, assumption and CSC action are
 * separate events and must never be collapsed into one status.
 */
class AppointmentRecord extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_FOR_REVIEW = 'for_review';

    public const STATUS_APPROVED_FOR_ISSUANCE = 'approved_for_issuance';

    public const STATUS_ISSUED = 'issued';

    public const STATUS_SERVED = 'served';

    public const STATUS_SUBMITTED_TO_CSC = 'submitted_to_csc';

    public const STATUS_CSC_ACTION_PENDING = 'csc_action_pending';

    public const STATUS_VALIDATED = 'validated';

    public const STATUS_RETURNED = 'returned';

    public const STATUS_CANCELLED = 'cancelled';

    public const TRANSITIONS = [
        self::STATUS_DRAFT => [self::STATUS_FOR_REVIEW, self::STATUS_CANCELLED],
        self::STATUS_FOR_REVIEW => [self::STATUS_APPROVED_FOR_ISSUANCE, self::STATUS_RETURNED, self::STATUS_CANCELLED],
        self::STATUS_RETURNED => [self::STATUS_FOR_REVIEW, self::STATUS_CANCELLED],
        self::STATUS_APPROVED_FOR_ISSUANCE => [self::STATUS_ISSUED, self::STATUS_CANCELLED],
        self::STATUS_ISSUED => [self::STATUS_SERVED, self::STATUS_CANCELLED],
        self::STATUS_SERVED => [self::STATUS_SUBMITTED_TO_CSC, self::STATUS_VALIDATED, self::STATUS_CANCELLED],
        self::STATUS_SUBMITTED_TO_CSC => [self::STATUS_CSC_ACTION_PENDING, self::STATUS_VALIDATED, self::STATUS_RETURNED],
        self::STATUS_CSC_ACTION_PENDING => [self::STATUS_VALIDATED, self::STATUS_RETURNED],
        self::STATUS_VALIDATED => [],
        self::STATUS_CANCELLED => [],
    ];

    public const TYPE_ORIGINAL = 'original';

    public const TYPE_PROMOTION = 'promotion';

    public const TYPE_TRANSFER = 'transfer';

    public const TYPE_REASSIGNMENT = 'reassignment';

    public const TYPE_DETAIL = 'detail';

    public const TYPE_DESIGNATION = 'designation';

    public const TYPE_REAPPOINTMENT = 'reappointment';

    public const TYPE_REINSTATEMENT = 'reinstatement';

    public const TYPE_REEMPLOYMENT = 'reemployment';

    public const TYPE_RECLASSIFICATION = 'reclassification';

    public const TYPE_DEMOTION = 'demotion';

    public const TYPE_SECONDMENT = 'secondment';

    public const TYPE_OTHER = 'other';

    public const TYPES = [
        self::TYPE_ORIGINAL,
        self::TYPE_PROMOTION,
        self::TYPE_TRANSFER,
        self::TYPE_REASSIGNMENT,
        self::TYPE_DETAIL,
        self::TYPE_DESIGNATION,
        self::TYPE_REAPPOINTMENT,
        self::TYPE_REINSTATEMENT,
        self::TYPE_REEMPLOYMENT,
        self::TYPE_RECLASSIFICATION,
        self::TYPE_DEMOTION,
        self::TYPE_SECONDMENT,
        self::TYPE_OTHER,
    ];

    public const NATURE_PERMANENT = 'permanent';

    public const NATURE_TEMPORARY = 'temporary';

    public const NATURE_CASUAL = 'casual';

    public const NATURE_CONTRACTUAL = 'contractual';

    public const NATURE_CO_TERMINOUS = 'co_terminous';

    public const NATURE_ELECTIVE = 'elective';

    public const NATURES = [
        self::NATURE_PERMANENT,
        self::NATURE_TEMPORARY,
        self::NATURE_CASUAL,
        self::NATURE_CONTRACTUAL,
        self::NATURE_CO_TERMINOUS,
        self::NATURE_ELECTIVE,
    ];

    protected $fillable = [
        'company_id',
        'employee_id',
        'plantilla_item_id',
        'plantilla_version_id',
        'office_id',
        'position_id',
        'appointment_type',
        'nature',
        'legal_basis',
        'reference_number',
        'effectivity_date',
        'assumption_date',
        'status',
        'qualification_standard_id',
        'qualification_screening_id',
        'workflow_instance_id',
        'approved_by',
        'approved_at',
        'issued_at',
        'served_at',
        'submitted_to_csc_at',
        'csc_action_at',
        'applied_at',
        'metadata',
    ];

    protected $casts = [
        'effectivity_date' => 'date',
        'assumption_date' => 'date',
        'approved_at' => 'datetime',
        'issued_at' => 'datetime',
        'served_at' => 'datetime',
        'submitted_to_csc_at' => 'datetime',
        'csc_action_at' => 'datetime',
        'applied_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function plantillaItem(): BelongsTo
    {
        return $this->belongsTo(PlantillaItem::class);
    }

    public function plantillaVersion(): BelongsTo
    {
        return $this->belongsTo(PlantillaVersion::class);
    }

    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class);
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    public function qualificationStandard(): BelongsTo
    {
        return $this->belongsTo(QualificationStandard::class);
    }

    public function qualificationScreening(): BelongsTo
    {
        return $this->belongsTo(QualificationScreening::class);
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(WorkflowInstance::class, 'workflow_instance_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * @return HasMany<AppointmentDocument, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(AppointmentDocument::class, 'appointment_id');
    }

    /**
     * @return HasMany<AppointmentAction, $this>
     */
    public function actions(): HasMany
    {
        return $this->hasMany(AppointmentAction::class, 'appointment_id')->orderBy('id');
    }

    /**
     * @return HasMany<AssumptionRecord, $this>
     */
    public function assumptions(): HasMany
    {
        return $this->hasMany(AssumptionRecord::class, 'appointment_id');
    }

    public function canTransitionTo(string $status): bool
    {
        return in_array($status, self::TRANSITIONS[$this->status] ?? [], true);
    }

    public function isApplied(): bool
    {
        return $this->applied_at !== null;
    }

    public function isOpen(): bool
    {
        return ! in_array($this->status, [self::STATUS_VALIDATED, self::STATUS_CANCELLED], true);
    }
}
