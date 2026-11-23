<?php

namespace App\Livewire\Admin\Actions;

use App\Models\Timesheet;
use App\Services\Attendance\AttendancePeriod;
use App\Services\Attendance\TimesheetCalculator;
use Illuminate\Bus\Queueable;
use Illuminate\Http\Request;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

/**
 * Recompute every selected day from its raw punches.
 */
class RecomputeSelected extends Action
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public string $name = 'Recompute selected';

    public string $confirmText = 'Recompute the selected days from their raw punches?';

    public string $confirmButtonText = 'Recompute';

    public function handle(Request $request, array $fields, Collection $models): mixed
    {
        $period = app(AttendancePeriod::class);
        $calculator = app(TimesheetCalculator::class);
        $computed = 0;
        $skipped = 0;

        foreach ($models as $model) {
            if (! $model instanceof Timesheet || (bool) $model->is_locked) {
                $skipped++;

                continue;
            }

            $employee = $model->employee;

            if ($employee !== null && $period->isClosedFor($employee, $model->log_date)) {
                $skipped++;

                continue;
            }

            $calculator->compute($model);
            $computed++;
        }

        return self::message($skipped === 0
            ? "{$computed} timesheets recomputed."
            : "{$computed} timesheets recomputed; {$skipped} skipped because they are locked or closed.");
    }
}
