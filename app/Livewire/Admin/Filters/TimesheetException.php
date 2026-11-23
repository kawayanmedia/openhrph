<?php

namespace App\Livewire\Admin\Filters;

use App\Models\Timesheet;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * The timekeeper's exception worklist.
 *
 * A day only shows up here when someone has to make a decision; ordinary
 * computed days stay out of the way.
 */
class TimesheetException extends Filter
{
    public string $name = 'Exceptions';

    public function apply(Request $request, Builder $query, mixed $value): Builder
    {
        return match ((string) $value) {
            'missing_punch' => $query->whereNotNull('logs')->where('paid_minutes', 0),
            'late' => $query->where('late_minutes', '>', 0),
            'undertime' => $query->where('undertime_minutes', '>', 0),
            'no_schedule' => $query->whereNull('schedule_id')->whereNull('shift_id'),
            'rest_day_work' => $query->where('day_type', Timesheet::REST_DAY)->where('paid_minutes', '>', 0),
            'absent' => $query->where('is_absent', true),
            'ot_without_approval' => $query->where('actual_ot', '>', 0)
                ->where(function (Builder $query): void {
                    $query->whereNull('approved_ot_minutes')->orWhere('approved_ot_minutes', 0);
                }),
            default => $query,
        };
    }

    public function options(Request $request): array
    {
        return [
            '' => 'All days',
            'missing_punch' => 'Missing punch',
            'late' => 'Late',
            'undertime' => 'Undertime',
            'no_schedule' => 'No schedule',
            'rest_day_work' => 'Rest-day work',
            'absent' => 'Absent',
            'ot_without_approval' => 'OT without approval',
        ];
    }
}
