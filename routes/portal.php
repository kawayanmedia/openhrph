<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('portal/login', \App\Livewire\Portal\Login::class)->name('portal.login');

Route::get('job-vacancies', \App\Livewire\Portal\Leaves\JobIndex::class)->name('portal.jobs');
Route::get('job-success', \App\Livewire\Portal\Leaves\JobSuccess::class)->name('portal.jobs.success');
Route::get('job-vacancies/{id}/apply', \App\Livewire\Portal\Leaves\ApplicantCreate::class)->name('portal.job.apply');


Route::middleware('auth:portal')->prefix('portal')->group(function () {

    Route::get('/', function () {
        return redirect()->route('portal.dashboard');
    });

    Route::get('dashboard', \App\Livewire\Portal\Dashboard::class)->name('portal.dashboard');
    Route::get('leaves', \App\Livewire\Portal\Leaves\LeaveIndex::class)->name('portal.leaves');
    Route::get('leaves/create', \App\Livewire\Portal\Leaves\LeaveCreate::class)->name('portal.leaves.create');

    Route::get('attendance', \App\Livewire\Portal\Attendance\AttendanceIndex::class)->name('portal.attendance');
    Route::get('attendance/create', \App\Livewire\Portal\Attendance\AttendanceCreate::class)->name('portal.attendance.create');

    Route::get('timesheet', \App\Livewire\Portal\Timesheets\TimesheetPunch::class)->name('portal.timesheet');

    Route::get('schedules', \App\Livewire\Portal\Schedules\ScheduleIndex::class)->name('portal.schedules');
    Route::get('schedules/create', \App\Livewire\Portal\Schedules\ScheduleCreate::class)->name('portal.schedules.create');

    Route::get('schedule-adjustments', \App\Livewire\Portal\Schedules\ScheduleAdjustmentIndex::class)->name('portal.schedulesadjustment');
    Route::get('schedule-adjustments/create', \App\Livewire\Portal\Schedules\ScheduleAdjustmentCreate::class)->name('portal.schedulesadjustment.create');

    Route::get('overtimes', \App\Livewire\Portal\Overtimes\OvertimeIndex::class)->name('portal.overtimes');
    Route::get('overtimes/create', \App\Livewire\Portal\Overtimes\OvertimeCreate::class)->name('portal.overtimes.create');

    Route::get('undertimes', \App\Livewire\Portal\Undertimes\UndertimeIndex::class)->name('portal.undertimes');
    Route::get('undertimes/create', \App\Livewire\Portal\Undertimes\UndertimeCreate::class)->name('portal.undertimes.create');

    Route::get('officialbusiness', \App\Livewire\Portal\OfficialBusiness\OfficialBusinessIndex::class)->name('portal.officialbusiness');
    Route::get('officialbusiness/create', \App\Livewire\Portal\OfficialBusiness\OfficialBusinessCreate::class)->name('portal.officialbusiness.create');

    Route::get('attendance/report', \App\Livewire\Portal\Attendance\AttendanceReport::class)->name('portal.atendance.report');

    Route::get('signout', function () {
        Auth::logout();

        return redirect()->route('portal.login');
    })->name('portal.signout');
});
