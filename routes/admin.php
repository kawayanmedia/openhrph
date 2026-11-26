<?php

use App\Http\Controllers\Admin\BankFileDownloadController;
use App\Http\Controllers\Admin\EmployeeFileController;
use App\Http\Controllers\Admin\EmployeeMasterFileController;
use App\Http\Controllers\Admin\ImportTemplateController;
use App\Http\Controllers\Admin\PasswordResetController;
use App\Http\Controllers\Admin\RemittanceDownloadController;
use App\Livewire\Admin\AdminDetail;
use App\Livewire\Admin\AdminForm;
use App\Livewire\Admin\AdminIndex;
use App\Livewire\Admin\Assistant\Chat as AssistantChat;
use App\Livewire\Admin\Auth\Login;
use App\Livewire\Admin\Dashboards\Main;
use App\Livewire\Admin\GettingStarted;
use App\Livewire\Admin\Imports\DataImport;
use App\Livewire\Admin\Payroll\PayrollBankFile;
use App\Livewire\Admin\Payroll\PayrollPayslip;
use App\Livewire\Admin\Payroll\PayrollRun;
use App\Livewire\Admin\Reports\EmployeeMasterFile as EmployeeMasterFileReport;
use App\Livewire\Admin\Reports\Remittances as RemittancesReport;
use App\Livewire\Admin\Settings\Settings;
use App\Livewire\Admin\SoftwareUpdate;
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

        Route::get('updates', SoftwareUpdate::class)->name('updates');

        Route::get('imports', DataImport::class)->name('import');
        Route::get('imports/template', ImportTemplateController::class)->name('import.template');

        Route::get('assistant', AssistantChat::class)->name('assistant');

        Route::get('reports/remittances/{payroll}/download/{format}', [RemittanceDownloadController::class, 'download'])
            ->name('reports.remittances.download');
        Route::get('reports/remittances/{payroll}/download-all', [RemittanceDownloadController::class, 'downloadAll'])
            ->name('reports.remittances.download-all');
        Route::get('reports/remittances/{payroll?}', RemittancesReport::class)
            ->name('reports.remittances');

        Route::get('reports/employee-master-file.csv', [EmployeeMasterFileController::class, 'csv'])
            ->name('reports.employee-master-file.csv');
        Route::get('reports/employee-master-file', EmployeeMasterFileReport::class)
            ->name('reports.employee-master-file');

        Route::get('employees/{employee}/201', [EmployeeFileController::class, 'show'])
            ->name('employees.file');

        Route::post('logout', function (Request $request) {
            Auth::guard('nova')->logout();
            Auth::guard('web')->logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('admin.login');
        })->name('logout');

        Route::get('payroll/{payroll}', PayrollRun::class)->name('payroll.run');
        Route::get('payroll/{payroll}/payslips/{employee}', PayrollPayslip::class)->name('payroll.payslip');
        Route::get('payroll/{payroll}/bank-file/download', [BankFileDownloadController::class, 'download'])->name('payroll.bank-file.download');
        Route::get('payroll/{payroll}/bank-file', PayrollBankFile::class)->name('payroll.bank-file');

        Route::get('settings/{page}', Settings::class)->name('settings')->where('page', 'general|attendance|payroll|statutory|sector|leave');

        Route::get('resources/{resource}', AdminIndex::class)->name('resource.index');
        Route::get('resources/{resource}/new', AdminForm::class)->name('resource.create');
        Route::get('resources/{resource}/{resourceId}', AdminDetail::class)->name('resource.detail');
        Route::get('resources/{resource}/{resourceId}/edit', AdminForm::class)->name('resource.edit');
    });
