<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExternalClaimTracking extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'external_claim_tracking';

    public const AGENCY_GSIS = 'GSIS';

    public const AGENCY_SSS = 'SSS';

    public const AGENCY_OTHER = 'other';

    public const STATUS_NOT_SUBMITTED = 'not_submitted';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_IN_PROCESS = 'in_process';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_DENIED = 'denied';

    public const STATUS_RELEASED = 'released';

    public const STATUSES = [
        self::STATUS_NOT_SUBMITTED,
        self::STATUS_SUBMITTED,
        self::STATUS_IN_PROCESS,
        self::STATUS_APPROVED,
        self::STATUS_DENIED,
        self::STATUS_RELEASED,
    ];

    protected $fillable = [
        'company_id',
        'retirement_case_id',
        'agency',
        'reference',
        'status',
        'submitted_at',
        'last_checked_at',
        'released_at',
        'notes',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'last_checked_at' => 'datetime',
        'released_at' => 'datetime',
    ];

    public function retirementCase(): BelongsTo
    {
        return $this->belongsTo(RetirementCase::class);
    }

    public function isOpen(): bool
    {
        return in_array($this->status, [
            self::STATUS_SUBMITTED,
            self::STATUS_IN_PROCESS,
            self::STATUS_APPROVED,
        ], true);
    }
}
