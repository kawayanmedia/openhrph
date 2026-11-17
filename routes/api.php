<?php

use App\Http\Controllers\Api\AnnouncementController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DepartmentController;
use App\Http\Controllers\Api\EmployeeController;
use App\Http\Controllers\Api\EmploymentTypeController;
use App\Http\Controllers\Api\HolidayController;
use App\Http\Controllers\Api\LeaveRequestController;
use App\Http\Controllers\Api\OfficeController;
use App\Http\Controllers\Api\OvertimeRequestController;
use App\Http\Controllers\Api\PayrollController;
use App\Http\Controllers\Api\PositionController;
use App\Http\Controllers\Api\ScheduleController;
use App\Http\Controllers\Api\TimesheetController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Auth routes
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    // Auth
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index']);

    // Employees
    Route::apiResource('employees', EmployeeController::class);

    // Departments
    Route::apiResource('departments', DepartmentController::class);

    // Offices
    Route::apiResource('offices', OfficeController::class);

    // Positions
    Route::apiResource('positions', PositionController::class);

    // Employment Types
    Route::apiResource('employment-types', EmploymentTypeController::class);

    // Leave Requests
    Route::post('/leave-requests/{leave_request}/approve', [LeaveRequestController::class, 'approve']);
    Route::post('/leave-requests/{leave_request}/reject', [LeaveRequestController::class, 'reject']);
    Route::apiResource('leave-requests', LeaveRequestController::class);

    // Overtime Requests
    Route::post('/overtime-requests/{overtime_request}/approve', [OvertimeRequestController::class, 'approve']);
    Route::post('/overtime-requests/{overtime_request}/reject', [OvertimeRequestController::class, 'reject']);
    Route::apiResource('overtime-requests', OvertimeRequestController::class);

    // Payroll (read-only via API)
    Route::get('/payrolls', [PayrollController::class, 'index']);
    Route::get('/payrolls/{payroll}', [PayrollController::class, 'show']);

    // Timesheets (read-only via API)
    Route::get('/timesheets', [TimesheetController::class, 'index']);
    Route::get('/timesheets/{timesheet}', [TimesheetController::class, 'show']);

    // Announcements
    Route::apiResource('announcements', AnnouncementController::class);

    // Holidays
    Route::apiResource('holidays', HolidayController::class);

    // Schedules
    Route::apiResource('schedules', ScheduleController::class);
});
