<?php

use App\Http\Middleware\AuthenticateAttendanceIngest;
use App\Http\Middleware\EnsureDemoEmailVerified;
use App\Http\Middleware\EnsureDemoModeEnabled;
use App\Http\Middleware\ResolveTenant;
use App\Http\Middleware\ResolveTenantFromApiKey;
use App\Models\User;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
        then: function () {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/attendance-api.php'));

            Route::middleware('api')
                ->prefix('api');
        },
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Keep signed-in demo visitors on the verification notice instead of
        // bouncing them to the landing page when they revisit the sign-up page.
        $middleware->redirectUsersTo(function (Request $request) {
            $user = $request->user();

            if (config('demo.enabled') && $user instanceof User && ! $user->hasVerifiedEmail()) {
                return route('verification.notice');
            }

            return '/';
        });

        $middleware->redirectGuestsTo(function (Request $request) {
            if ($request->is('portal', 'portal/*')) {
                return route('portal.login');
            }

            if ($request->is(trim(config('admin.path'), '/'), trim(config('admin.path'), '/').'/*', 'email/verify', 'email/verify/*', 'email/verification-notification')) {
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
            'demo.enabled' => EnsureDemoModeEnabled::class,
            'demo.verified' => EnsureDemoEmailVerified::class,
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

// electron-builder drops dotfiles from the packaged desktop app, so it never
// receives the .env NativePHP copies into the build directory. Fall back to the
// copy `desktop:export-env` writes during the build.
if (! is_file(dirname(__DIR__).'/.env') && is_file(__DIR__.'/desktop.env')) {
    $app->loadEnvironmentFrom('bootstrap/desktop.env');
}

return $app;
