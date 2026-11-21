<?php

namespace App\Actions;

use App\Models\LeaveRequest;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

class CreateLeaveAction extends BaseAction
{
    use AsAction;

    public function handle(Request $request, $leaveData)
    {
        $companyId = $request->user()?->company_id;
        $employeeId = $request->user()?->employee?->id;

        if ($companyId === null || $employeeId === null) {
            throw ValidationException::withMessages([
                'leaveTypeId' => 'Your account is not linked to a company employee record.',
            ]);
        }

        $leaveRequest = new LeaveRequest;
        $leaveRequest->company_id = $companyId;
        $leaveRequest->employee_id = $employeeId;
        $leaveRequest->date_filed = now()->format('Y-m-d');
        $leaveRequest->leave_type_id = $leaveData->leaveTypeId;
        $leaveRequest->date_from = $leaveData->dateFrom;
        $leaveRequest->date_to = $leaveData->dateTo;
        $leaveRequest->days_with_pay = (float) ($leaveData->daysWithPay ?? 0);
        $leaveRequest->days_without_pay = (float) ($leaveData->daysWithoutPay ?? 0);
        $leaveRequest->reason = $leaveData->reason;
        $leaveRequest->is_whole_day = $leaveData->isWholeDay === 'true' || $leaveData->isWholeDay === true;
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
