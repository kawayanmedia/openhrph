<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class AttendanceCorrectionDecision extends Model
{
    use BelongsToTenant;

    public const UPDATED_AT = null;

    protected $fillable = [
        'company_id',
        'attendance_correction_id',
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
            throw new LogicException('Correction decisions are immutable.');
        });

        static::deleting(function (): never {
            throw new LogicException('Correction decisions are immutable.');
        });
    }

    public function correction(): BelongsTo
    {
        return $this->belongsTo(AttendanceCorrection::class, 'attendance_correction_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
