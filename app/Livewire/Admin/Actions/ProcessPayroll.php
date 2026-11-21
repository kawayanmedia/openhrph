<?php

namespace App\Livewire\Admin\Actions;

use App\Jobs\ProcessPayrollJob;
use App\Models\Payroll;
use App\Services\Payroll\PayrollAttendance;
use Illuminate\Bus\Queueable;
use Illuminate\Http\Request;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class ProcessPayroll extends Action
{
    use InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Perform the action on the given models.
     */
    public function handle(Request $request, array $fields, Collection $payrolls): mixed
    {
        $attendance = app(PayrollAttendance::class);
        $processed = 0;
        $blocked = [];

        foreach ($payrolls as $payroll) {
            if (in_array($payroll->status, [Payroll::STATUS_APPROVED, Payroll::STATUS_CLOSED], true)) {
                $blocked[] = "#{$payroll->getKey()} is approved and locked";

                continue;
            }

            // Bulk processing has no place to record the required override
            // reason, so a run without attendance must go through the payroll
            // run page, where the reason is captured and audited.
            if ($attendance->paysWithoutTimesheet((int) $payroll->company_id)
                && $attendance->missingEmployees($payroll)->isNotEmpty()) {
                $blocked[] = "#{$payroll->getKey()} has employees without attendance";

                continue;
            }

            $payroll->status = Payroll::STATUS_RUNNING;
            $payroll->save();

            ProcessPayrollJob::dispatchFor($payroll);
            $processed++;
        }

        if ($blocked !== []) {
            return self::message(($processed > 0 ? "{$processed} payroll(s) started. " : '')
                .'Not processed: '.implode('; ', $blocked)
                .'. Open the payroll run page to record an override reason.');
        }

        return self::message('Payroll has been processed!');
    }

    /**
     * Get the fields available on the action.
     */
    public function fields(Request $request): array
    {
        return [];
    }
}
