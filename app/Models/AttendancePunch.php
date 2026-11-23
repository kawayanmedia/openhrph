<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One immutable punch event.
 *
 * Rows are never deleted: a correction sets `voided_at`, `voided_by` and
 * `void_reason` so the original device punch, portal punch or import remains
 * visible for audit.
 */
class AttendancePunch extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'attendance_punches';

    protected $fillable = [
        'company_id',
        'employee_id',
        'timesheet_id',
        'workday_date',
        'punched_at',
        'direction',
        'source',
        'device',
        'request_id',
        'voided_at',
        'voided_by',
        'void_reason',
        'raw',
    ];

    protected $casts = [
        'workday_date' => 'date',
        'punched_at' => 'datetime',
        'voided_at' => 'datetime',
        'direction' => 'integer',
        'raw' => 'array',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function timesheet(): BelongsTo
    {
        return $this->belongsTo(Timesheet::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('voided_at');
    }

    public function isVoided(): bool
    {
        return $this->voided_at !== null;
    }

    public function directionLabel(): string
    {
        return $this->direction === 0 ? 'In' : 'Out';
    }
}
