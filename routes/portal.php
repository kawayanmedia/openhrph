<?php

use App\Http\Controllers\Portal\DocumentDownloadController;
use App\Livewire\Portal\Attendance\AttendanceCreate;
use App\Livewire\Portal\Attendance\AttendanceIndex;
use App\Livewire\Portal\Attendance\AttendanceReport;
use App\Livewire\Portal\Dashboard;
use App\Livewire\Portal\Documents;
use App\Livewire\Portal\Leaves\ApplicantCreate;
use App\Livewire\Portal\Leaves\JobIndex;
use App\Livewire\Portal\Leaves\JobSuccess;
use App\Livewire\Portal\Leaves\LeaveCreate;
use App\Livewire\Portal\Leaves\LeaveIndex;
use App\Livewire\Portal\Login;
use App\Livewire\Portal\OfficialBusiness\OfficialBusinessCreate;
use App\Livewire\Portal\OfficialBusiness\OfficialBusinessIndex;
use App\Livewire\Portal\Overtimes\OvertimeCreate;
use App\Livewire\Portal\Overtimes\OvertimeIndex;
use App\Livewire\Portal\Performance;
use App\Livewire\Portal\Schedules\ScheduleAdjustmentCreate;
use App\Livewire\Portal\Schedules\ScheduleAdjustmentIndex;
use App\Livewire\Portal\Schedules\ScheduleCreate;
use App\Livewire\Portal\Schedules\ScheduleIndex;
use App\Livewire\Portal\Timesheets\TimesheetPunch;
use App\Livewire\Portal\Training;
use App\Livewire\Portal\Undertimes\UndertimeCreate;
use App\Livewire\Portal\Undertimes\UndertimeIndex;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('portal/login', Login::class)->name('portal.login');

Route::get('job-vacancies', JobIndex::class)->name('portal.jobs');
Route::get('job-success', JobSuccess::class)->name('portal.jobs.success');
Route::get('job-vacancies/{id}/apply', ApplicantCreate::class)->name('portal.job.apply');

Route::middleware('auth:portal')->prefix('portal')->group(function () {

    Route::get('/', function () {
        return redirect()->route('portal.dashboard');
    });

    Route::get('dashboard', Dashboard::class)->name('portal.dashboard');
    Route::get('leaves', LeaveIndex::class)->name('portal.leaves');
    Route::get('leaves/create', LeaveCreate::class)->name('portal.leaves.create');

    Route::get('attendance', AttendanceIndex::class)->name('portal.attendance');
    Route::get('attendance/create', AttendanceCreate::class)->name('portal.attendance.create');

    Route::get('timesheet', TimesheetPunch::class)->name('portal.timesheet');

    Route::get('schedules', ScheduleIndex::class)->name('portal.schedules');
    Route::get('schedules/create', ScheduleCreate::class)->name('portal.schedules.create');

    Route::get('schedule-adjustments', ScheduleAdjustmentIndex::class)->name('portal.schedulesadjustment');
    Route::get('schedule-adjustments/create', ScheduleAdjustmentCreate::class)->name('portal.schedulesadjustment.create');

    Route::get('overtimes', OvertimeIndex::class)->name('portal.overtimes');
    Route::get('overtimes/create', OvertimeCreate::class)->name('portal.overtimes.create');

    Route::get('undertimes', UndertimeIndex::class)->name('portal.undertimes');
    Route::get('undertimes/create', UndertimeCreate::class)->name('portal.undertimes.create');

    Route::get('officialbusiness', OfficialBusinessIndex::class)->name('portal.officialbusiness');
    Route::get('officialbusiness/create', OfficialBusinessCreate::class)->name('portal.officialbusiness.create');

    Route::get('attendance/report', AttendanceReport::class)->name('portal.atendance.report');

    Route::get('performance', Performance::class)->name('portal.performance');
    Route::get('training', Training::class)->name('portal.training');
    Route::get('documents', Documents::class)->name('portal.documents');
    Route::get('documents/{document}/download', DocumentDownloadController::class)
        ->name('portal.documents.download');

    Route::get('signout', function () {
        Auth::logout();

        return redirect()->route('portal.login');
    })->name('portal.signout');
});
