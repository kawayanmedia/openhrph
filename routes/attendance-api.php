<?php

use App\Http\Controllers\Api\AttendanceLogController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Attendance Ingestion API
|--------------------------------------------------------------------------
|
| Machine-to-machine endpoints for time clocks and other systems that push
| raw attendance logs. Clients authenticate with X-Company-Key or
| X-Device-Key; the tenant is resolved from the key.
|
*/

Route::middleware(['attendance.ingest', 'throttle:120,1'])->group(function () {
    Route::post('attendance/logs', [AttendanceLogController::class, 'store'])
        ->name('api.attendance.logs.store');

    Route::get('attendance/logs', [AttendanceLogController::class, 'index'])
        ->name('api.attendance.logs.index');
});
