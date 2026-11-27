<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class TimesheetApproval extends Model
{
    use BelongsToTenant;

    public const UPDATED_AT = null;

    public const ACTION_SUBMIT = 'submit';

    public const ACTION_APPROVE = 'approve';

    public const ACTION_REJECT = 'reject';

    public const ACTION_LOCK = 'lock';

    public const ACTION_UNLOCK = 'unlock';

    protected $fillable = [
        'company_id',
        'timesheet_id',
        'actor_id',
        'action',
        'comments',
        'decided_at',
    ];

    protected $casts = [
        'decided_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('Timesheet approvals are immutable.');
        });

        static::deleting(function (): never {
            throw new LogicException('Timesheet approvals are immutable.');
        });
    }

    public function timesheet(): BelongsTo
    {
        return $this->belongsTo(Timesheet::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
