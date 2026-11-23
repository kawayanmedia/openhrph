<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A reusable working pattern: one or more working segments plus unpaid breaks.
 *
 * Segments are stored as `[["08:00","12:00"],["13:00","17:00"]]`. A segment
 * that ends before it starts crosses midnight, which is how overnight shifts
 * are expressed without a separate column set.
 */
class AttendanceShift extends Model
{
    use BelongsToTenant;
    use HasFactory;

    public const TYPE_FIXED = 'fixed';

    public const TYPE_SPLIT = 'split';

    public const TYPE_OVERNIGHT = 'overnight';

    public const TYPE_FLEXIBLE = 'flexible';

    protected $table = 'attendance_shifts';

    protected $fillable = [
        'company_id',
        'name',
        'code',
        'type',
        'segments',
        'breaks',
        'night_window_start',
        'night_window_end',
        'grace_minutes',
        'is_default',
        'is_active',
    ];

    protected $casts = [
        'segments' => 'array',
        'breaks' => 'array',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
        'grace_minutes' => 'integer',
    ];

    public function assignments(): HasMany
    {
        return $this->hasMany(AttendanceScheduleAssignment::class, 'shift_id');
    }

    public function getSegmentsTextAttribute(): string
    {
        return collect($this->segments ?? [])
            ->filter(fn ($segment): bool => is_array($segment) && count($segment) >= 2)
            ->map(fn (array $segment): string => $segment[0].' - '.$segment[1])
            ->implode(', ');
    }

    public function getBreaksTextAttribute(): string
    {
        return collect($this->breaks ?? [])
            ->filter(fn ($break): bool => is_array($break) && count($break) >= 2)
            ->map(fn (array $break): string => $break[0].' - '.$break[1])
            ->implode(', ');
    }
}
