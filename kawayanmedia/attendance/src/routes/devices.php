<?php

use Illuminate\Support\Facades\Route;
use KawayanMedia\Attendance\Adms\AdmsController;
use KawayanMedia\Attendance\Adms\AuthenticateAdmsDevice;

/*
|--------------------------------------------------------------------------
| ADMS / iClock push protocol
|--------------------------------------------------------------------------
|
| A device is configured with the application's base URL and then calls these
| paths itself. They are stateless, CSRF-free and authenticate by serial
| number, so they do not use the session or the web middleware group.
|
*/

$prefix = trim((string) config('attendance-devices.route_prefix', 'iclock'), '/');
$throttle = max(60, (int) config('attendance-devices.adms.throttle_per_minute', 600));

Route::middleware(['api', AuthenticateAdmsDevice::class, 'throttle:'.$throttle.',1'])
    ->prefix($prefix === '' ? 'iclock' : $prefix)
    ->group(function (): void {
        Route::match(['get', 'post'], 'cdata', [AdmsController::class, 'cdata'])
            ->name('attendance.adms.cdata');

        Route::get('getrequest', [AdmsController::class, 'getRequest'])
            ->name('attendance.adms.getrequest');

        Route::post('devicecmd', [AdmsController::class, 'deviceCommand'])
            ->name('attendance.adms.devicecmd');

        Route::match(['get', 'post'], 'ping', [AdmsController::class, 'ping'])
            ->name('attendance.adms.ping');
    });
