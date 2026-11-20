<?php

namespace App\Http\Middleware;

use App\Services\Desktop\DesktopEnvironment;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureDesktopMode
{
    /**
     * Keep the desktop-only setup wizard out of web deployments.
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(DesktopEnvironment::isRunning(), 404);

        return $next($request);
    }
}
