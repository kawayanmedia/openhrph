<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Effective-dated shift assignment, optionally with a rotation cycle.
 *
 * `rotation` is a list of shift ids (or null for a rest day) applied from
 * `cycle_anchor`. `weekdays` restricts a single-shift assignment to the given
 * ISO weekdays (1 = Monday).
 */
class AttendanceScheduleAssignment extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'attendance_schedule_assignments';

    protected $fillable = [
        'company_id',
        'employee_id',
        'shift_id',
        'rotation',
        'cycle_anchor',
        'weekdays',
        'effective_from',
        'effective_to',
        'reason',
    ];

    protected $casts = [
        'rotation' => 'array',
        'weekdays' => 'array',
        'cycle_anchor' => 'date',
        'effective_from' => 'date',
        'effective_to' => 'date',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(AttendanceShift::class, 'shift_id');
    }

    /**
     * The shift id (or null for a rest day) that applies on the given date.
     */
    public function shiftIdFor(CarbonInterface $date): ?int
    {
        $date = Carbon::parse($date->toDateString());
        $rotation = is_array($this->rotation) ? array_values($this->rotation) : [];

        if ($rotation !== []) {
            $anchor = $this->cycle_anchor?->copy() ?? $date->copy()->startOfYear();
            $offset = (int) $anchor->startOfDay()->diffInDays($date->copy()->startOfDay(), false);
            $index = (($offset % count($rotation)) + count($rotation)) % count($rotation);
            $value = $rotation[$index];

            return $value === null || $value === '' ? null : (int) $value;
        }

        $weekdays = is_array($this->weekdays) ? array_map('intval', $this->weekdays) : [];

        if ($weekdays !== [] && ! in_array((int) $date->isoWeekday(), $weekdays, true)) {
            return null;
        }

        return $this->shift_id === null ? null : (int) $this->shift_id;
    }
}
