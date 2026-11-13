<?php

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
    });

Route::middleware(['web', 'auth:web,nova', 'can:'.config('admin.gate')])
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

        Route::get('settings/{page}', Settings::class)->name('settings')->where('page', 'general|attendance|payroll|leave');

        Route::get('resources/{resource}', AdminIndex::class)->name('resource.index');
        Route::get('resources/{resource}/new', AdminForm::class)->name('resource.create');
        Route::get('resources/{resource}/{resourceId}', AdminDetail::class)->name('resource.detail');
        Route::get('resources/{resource}/{resourceId}/edit', AdminForm::class)->name('resource.edit');
    });
