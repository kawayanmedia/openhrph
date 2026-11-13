<?php

use App\Http\Controllers\TimesheetController;
use Illuminate\Support\Facades\Route;

Route::get('timesheet/print', [TimesheetController::class, 'show'])
    ->middleware(['auth:web,nova,portal'])
    ->name('timesheet.print');
