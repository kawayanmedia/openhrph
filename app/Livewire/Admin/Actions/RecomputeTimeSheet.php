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
 * Recompute one day from its raw punches.
 *
 * This is the safe way to repair hours after a missing punch was added: the
 * calculator applies the resolved shift, breaks and night window instead of
 * relying on the operator to retype hours.
 */
class RecomputeTimeSheet extends Action
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public string $name = 'Recompute';

    public string $confirmText = 'Recompute this day from its raw punches?';

    public string $confirmButtonText = 'Recompute';

    public function handle(Request $request, array $fields, Collection $models): mixed
    {
        $period = app(AttendancePeriod::class);
        $calculator = app(TimesheetCalculator::class);
        $computed = 0;

        foreach ($models as $model) {
            if (! $model instanceof Timesheet) {
                continue;
            }

            if ($this->hasNoRawPunches($model)) {
                return self::message("{$model->log_date?->toDateString()} has no raw punches; there is nothing to recompute.");
            }

            if ((bool) $model->is_locked) {
                return self::message("{$model->log_date?->toDateString()} is locked by payroll and cannot be recomputed.");
            }

            $employee = $model->employee;

            if ($employee !== null && $period->isClosedFor($employee, $model->log_date)) {
                return self::message("Attendance for {$model->log_date?->toDateString()} is closed and cannot be recomputed.");
            }

            $calculator->compute($model);
            $computed++;
        }

        return self::message($computed === 1 ? 'Timesheet recomputed.' : "{$computed} timesheets recomputed.");
    }

    private function hasNoRawPunches(Timesheet $timesheet): bool
    {
        if (collect($timesheet->logs ?? [])->isNotEmpty()) {
            return false;
        }

        return $timesheet->rule_version === null && $timesheet->logs_rates !== null;
    }
}
