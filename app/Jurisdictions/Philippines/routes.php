<?php

use App\Jurisdictions\Philippines\Http\Controllers\CalculatorController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public Calculator API
|--------------------------------------------------------------------------
|
| Stateless payroll calculators for the public home page. They only expose
| published BIR and statutory tables, so no authentication or tenant is
| required. Callers are rate limited.
|
*/

Route::middleware('throttle:60,1')->group(function () {
    Route::post('calculators/withholding-tax', [CalculatorController::class, 'withholdingTax'])
        ->name('api.calculators.withholding-tax');

    Route::post('calculators/take-home-pay', [CalculatorController::class, 'takeHomePay'])
        ->name('api.calculators.take-home-pay');
});
