<?php

use App\Http\Controllers\NosaController;
use App\Http\Controllers\PayrollController;
use App\Http\Controllers\ServiceRecordController;
use App\Http\Controllers\SetupController;
use App\Livewire\Portal\Legislative;
use App\Livewire\Portal\TestPayroll;
use App\Models\MinimumWageRate;
use Bugsnag\BugsnagLaravel\Facades\Bugsnag;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('admin.login');
    return view('welcome');
});

Route::get('/pds/{employee}/pdf', function ($employee) {
    dd($employee);

    return view('welcome');
})->name('pds.pdf');

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
require 'gov.php';

// TODO for reports
// https://github.com/laracraft-tech/laravel-date-scopes


Route::get('sr/{id}/print', [ServiceRecordController::class, 'serviceRecord'])->middleware(['auth:web,nova,portal'])->name('sr.show');

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

// APP_PORTAL_SUBDOMAIN
// 'portal.hris.test'
Route::domain(env('APP_PORTAL_SUBDOMAIN'))->group(function () {

    Route::get('test', function () {
        return 'test1111';
    });
});
