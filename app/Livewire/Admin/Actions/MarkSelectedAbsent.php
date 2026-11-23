<?php

namespace App\Livewire\Admin\Actions;

use App\Models\Timesheet;
use App\Services\Attendance\TimesheetCalculator;
use App\Services\HrAudit;
use Illuminate\Bus\Queueable;
use Illuminate\Http\Request;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

/**
 * Mark the selected scheduled days absent.
 *
 * Only a regular scheduled workday with no punch, leave or holiday is eligible,
 * so selecting a whole page cannot accidentally mark a rest day or a worked
 * day absent.
 */
class MarkSelectedAbsent extends Action
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public string $name = 'Mark selected absent';

    public string $confirmText = 'Mark the selected eligible days absent? Rest days, holidays, leaves, locked days and days with punches are skipped.';

    public string $confirmButtonText = 'Mark absent';

    public function handle(Request $request, array $fields, Collection $models): mixed
    {
        $calculator = app(TimesheetCalculator::class);
        $marked = 0;
        $skipped = 0;

        foreach ($models as $model) {
            if (! $this->isEligible($model)) {
                $skipped++;

                continue;
            }

            $model->is_absent = true;
            $model->save();

            $calculator->compute($model);

            HrAudit::record((int) $model->company_id, 'timesheet_marked_absent', $model->employee_id, null, [
                'timesheet_id' => $model->getKey(),
                'log_date' => $model->log_date?->toDateString(),
            ]);

            $marked++;
        }

        return self::message($skipped === 0
            ? "{$marked} days marked absent."
            : "{$marked} days marked absent; {$skipped} skipped.");
    }

    private function isEligible(mixed $model): bool
    {
        if (! $model instanceof Timesheet) {
            return false;
        }

        if ((bool) $model->is_locked || (bool) $model->is_leave || (bool) $model->is_absent) {
            return false;
        }

        if ($model->day_type !== Timesheet::REGULAR_DAY || $model->holiday_id !== null) {
            return false;
        }

        if ($model->schedule_id === null && $model->shift_id === null) {
            return false;
        }

        if (collect($model->logs ?? [])->isNotEmpty()) {
            return false;
        }

        return $model->am_login === null
            && $model->am_logout === null
            && $model->pm_login === null
            && $model->pm_logout === null;
    }
}
