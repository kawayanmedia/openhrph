<?php

namespace App\Actions;

use App\Models\LeaveRequest;
use Illuminate\Http\Request;
use Lorisleiva\Actions\Concerns\AsAction;

class CreateLeaveAction extends BaseAction
{
    use AsAction;

    public function handle(Request $request, $leaveData)
    {
        $leaveRequest = new LeaveRequest();
        $leaveRequest->company_id = $request->user()?->company_id ?? 1;
        $leaveRequest->employee_id = $request->user()?->employee?->id;
        $leaveRequest->date_filed = now()->format('Y-m-d');
        $leaveRequest->leave_type_id = $leaveData->leaveTypeId;
        $leaveRequest->date_from = $leaveData->dateFrom;
        $leaveRequest->date_to = $leaveData->dateTo;
        $leaveRequest->days_with_pay = $leaveData->daysWithPay;
        $leaveRequest->days_without_pay = $leaveData->daysWithoutPay;
        $leaveRequest->reason = $leaveData->reason;
        $leaveRequest->is_whole_day = $leaveData->isWholeDay == 'true';
        $leaveRequest->attachments = $leaveData->attachments;
        $leaveRequest->save();

        return true;
    }

    // We can add this as controller route
    // Route::post('/leaves', CreateLeaveAction::class)->middleware('auth');
    public function asController(Request $request)
    {
        $this->handle($request, []);

        return redirect()->back();
    }
}
