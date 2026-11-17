<?php

namespace App\Actions;

use App\Models\TimesheetRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Laravel\Nova\Notifications\NovaNotification;
use Laravel\Nova\URL;
use Lorisleiva\Actions\Concerns\AsAction;

class CreateAttendanceAction extends BaseAction
{
    use AsAction;

    public function handle(array $applications, $request)
    {
        foreach ($applications as $application) {
            $attendanceRequest = new TimesheetRequest;
            $attendanceRequest->employee_id = request()->user()?->employee->id;
            // $attendanceRequest->employee_id = 1;
            $attendanceRequest->reason = $application['reason'];
            $attendanceRequest->type = $application['type'];
            $attendanceRequest->log_date = $application['date'];
            $attendanceRequest->time = $application['time'];
            $attendanceRequest->log_type = $application['log_type'];
            $attendanceRequest->date_filed = now();
            $attendanceRequest->save();
        }

        // Get the approver for attendance
        // $user = User::find(1);
        // Get the immediate head of the employee
        // $user = request()->user()->employee?->immediateHead?->user;

        // $user->notify(
        //     NovaNotification::make()
        //         ->message('New Attendance Application received.')
        //         ->action('View', URL::remote(request()->root().config('nova.path').'/resources/attendance-requests/'.$attendanceRequest->id))
        //         // ->icon('download')
        //         ->type('info')
        // );

        return true;
    }

    // We can add this as controller route like
    // Route::post('/leaves', CreateAttendanceAction::class)->middleware('auth');
    public function asController(Request $request)
    {
        $this->handle([], $request);

        return redirect()->back();
    }
}
