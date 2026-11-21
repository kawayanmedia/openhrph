<?php

use App\Http\Controllers\Admin\PasswordResetController;
use App\Livewire\Admin\AdminDetail;
use App\Livewire\Admin\AdminForm;
use App\Livewire\Admin\AdminIndex;
use App\Livewire\Admin\Assistant\Chat as AssistantChat;
use App\Livewire\Admin\Auth\Login;
use App\Livewire\Admin\Dashboards\Main;
use App\Livewire\Admin\GettingStarted;
use App\Livewire\Admin\Payroll\PayrollPayslip;
use App\Livewire\Admin\Payroll\PayrollRun;
use App\Livewire\Admin\Settings\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

$prefix = trim(config('admin.path'), '/');

Route::middleware(['web', 'guest:web'])
    ->prefix($prefix)
    ->name('admin.')
    ->group(function () {
        Route::get('login', Login::class)->name('login');

        Route::get('password/reset', [PasswordResetController::class, 'requestForm'])->name('password.request');
        Route::post('password/email', [PasswordResetController::class, 'sendLink'])->name('password.email');
        Route::post('password/reset', [PasswordResetController::class, 'reset'])->name('password.update');
    });

// Laravel's ResetPassword notification looks for the canonical route name.
Route::middleware(['web', 'guest:web'])
    ->get($prefix.'/password/reset/{token}', [PasswordResetController::class, 'resetForm'])
    ->name('password.reset');

Route::middleware(['web', 'auth:web,nova', 'can:'.config('admin.gate'), 'demo.verified'])
    ->prefix($prefix)
    ->name('admin.')
    ->group(function () {

        Route::get('/', fn () => redirect()->route('admin.dashboard'))->name('index');

        Route::get('dashboard', Main::class)->name('dashboard');

        Route::get('getting-started', GettingStarted::class)->name('getting-started');

        Route::get('assistant', AssistantChat::class)->name('assistant');

        Route::post('logout', function (Request $request) {
            Auth::guard('nova')->logout();
            Auth::guard('web')->logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('admin.login');
        })->name('logout');

        Route::get('payroll/{payroll}', PayrollRun::class)->name('payroll.run');
        Route::get('payroll/{payroll}/payslips/{employee}', PayrollPayslip::class)->name('payroll.payslip');

        Route::get('settings/{page}', Settings::class)->name('settings')->where('page', 'general|attendance|payroll|statutory|sector|leave');

        Route::get('resources/{resource}', AdminIndex::class)->name('resource.index');
        Route::get('resources/{resource}/new', AdminForm::class)->name('resource.create');
        Route::get('resources/{resource}/{resourceId}', AdminDetail::class)->name('resource.detail');
        Route::get('resources/{resource}/{resourceId}/edit', AdminForm::class)->name('resource.edit');
    });
