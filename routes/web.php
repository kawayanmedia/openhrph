<?php

use App\Http\Controllers\PayrollController;
use App\Http\Controllers\SetupController;
use App\Http\Controllers\TimesheetController;
use App\Http\Middleware\EnsureDesktopMode;
use App\Livewire\Desktop\Setup as DesktopSetup;
use App\Livewire\Portal\TestPayroll;
use Bugsnag\BugsnagLaravel\Facades\Bugsnag;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// First-run onboarding for the standalone NativePHP desktop build. The
// middleware keeps this route out of every web deployment.
Route::get('setup', DesktopSetup::class)
    ->middleware(EnsureDesktopMode::class)
    ->name('desktop.setup');

Route::get('/test-error-bugs', function () {
    abort_unless(app()->isLocal(), 404);

    Bugsnag::notifyException(new RuntimeException('Test error'));
});

Route::get('/test-error-503', function () {
    abort_unless(app()->isLocal(), 404);

    abort(503);
});
// Route::get('/', function () {
//     return view('landing');
// });

require 'face.php';
Route::get('timesheet/print', [TimesheetController::class, 'show'])
    ->middleware(['auth:web,nova,portal'])
    ->name('timesheet.print');

// TODO for reports
// https://github.com/laracraft-tech/laravel-date-scopes

// This is where to process and test payroll
Route::get('payroll-process', TestPayroll::class)->middleware(['auth:web,nova'])->name('portal.test');

Route::get('payslip/{id}', [PayrollController::class, 'show'])
    ->middleware(['web', 'auth:web,nova'])
    ->name('payslip.show');

Route::get('setup-company/{id}', [SetupController::class, 'setup'])->name('setup.index');
Route::get('setup-login/{token}', [SetupController::class, 'login'])->name('setup.login');

// Routes for portal
require 'portal.php';

// Routes for Livewire admin
require 'admin.php';

// Public demo sign-up and email verification (hidden unless DEMO_MODE=true)
require 'demo.php';

// APP_PORTAL_SUBDOMAIN
// 'portal.hris.test'
Route::domain(env('APP_PORTAL_SUBDOMAIN'))->group(function () {

    Route::get('test', function () {
        return 'test1111';
    });
});
