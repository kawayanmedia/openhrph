<?php

use App\Http\Middleware\AuthenticateAttendanceIngest;
use App\Http\Middleware\ResolveTenant;
use App\Http\Middleware\ResolveTenantFromApiKey;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
        then: function () {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/attendance-api.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->redirectGuestsTo(function (Request $request) {
            if ($request->is('portal', 'portal/*')) {
                return route('portal.login');
            }

            if ($request->is(trim(config('admin.path'), '/'), trim(config('admin.path'), '/').'/*')) {
                return route('admin.login');
            }

            return route('login');
        });

        // Behind an AWS ALB or another trusted reverse proxy, set
        // TRUSTED_PROXIES=* so HTTPS and client IPs are detected correctly.
        $middleware->trustProxies(at: env('TRUSTED_PROXIES', '127.0.0.1'));

        $middleware->alias([
            'tenant.api-key' => ResolveTenantFromApiKey::class,
            'attendance.ingest' => AuthenticateAttendanceIngest::class,
        ]);

        $middleware->web(append: [
            ResolveTenant::class,
        ]);

        $middleware->api(append: [
            ResolveTenant::class,
        ]);

        $middleware->preventRequestForgery(except: [
            'contis/*',
            'face/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
