<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
|
| This file is where you may define all of your Closure based console
| commands. Each Closure is bound to a command instance allowing a
| simple approach to interacting with each command's IO methods.
|
*/

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

// $schedule->command('payroll_date:generate {payroll_group_id?}')->daily();
Schedule::command('payroll_date:generate')->daily();

// $schedule->command('blank_timesheet:generate {from_date?} {to_date?}')->daily();
Schedule::command('blank_timesheet:generate')->daily();