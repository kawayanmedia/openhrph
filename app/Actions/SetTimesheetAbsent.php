<?php

namespace App\Actions;

use App\Models\Timesheet;
use Illuminate\Http\Request;
use Lorisleiva\Actions\Concerns\AsAction;

class SetTimesheetAbsent extends BaseAction
{
    use AsAction;

    public function handle($payroll)
    {
        Timesheet::whereBetween('log_date', [$payroll->date_from->format('Y-m-d'), $payroll->date_to->format('Y-m-d')])
            ->whereNull('logs_rates')
            ->update(['is_absent' => true]);

        return true;
    }

    // We can add this as controller route
    // Route::post('/leaves', CreateLeaveAction::class)->middleware('auth');
    // public function asController(Request $request)
    // {
    //     $this->handle($request, []);

    //     return redirect()->back();
    // }
}
