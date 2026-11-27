<?php

namespace App\Jurisdictions\Philippines\Government\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Immutable-ish record of a CSC or external action on an appointment.
 */
class AppointmentAction extends Model
{
    use BelongsToTenant;
    use HasFactory;

    public const TYPE_CSC_RECEIPT = 'csc_receipt';

    public const TYPE_CSC_APPROVAL = 'csc_approval';

    public const TYPE_CSC_RETURN = 'csc_return';

    public const TYPE_CSC_DENIAL = 'csc_denial';

    public const TYPE_RESUBMISSION = 'resubmission';

    public const TYPE_OTHER = 'other';

    public const TYPES = [
        self::TYPE_CSC_RECEIPT,
        self::TYPE_CSC_APPROVAL,
        self::TYPE_CSC_RETURN,
        self::TYPE_CSC_DENIAL,
        self::TYPE_RESUBMISSION,
        self::TYPE_OTHER,
    ];

    protected $fillable = [
        'company_id',
        'appointment_id',
        'action_type',
        'reference_number',
        'submitted_at',
        'action_date',
        'status',
        'remarks',
        'recorded_by',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'action_date' => 'date',
    ];

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(AppointmentRecord::class, 'appointment_id');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function isApproval(): bool
    {
        return $this->action_type === self::TYPE_CSC_APPROVAL;
    }

    public function isReturn(): bool
    {
        return in_array($this->action_type, [self::TYPE_CSC_RETURN, self::TYPE_CSC_DENIAL], true);
    }
}
