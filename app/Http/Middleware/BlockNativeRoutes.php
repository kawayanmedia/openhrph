<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The NativePHP package registers its Electron bridge endpoints globally.
 * They are only meaningful inside the desktop runtime, so hide them from web
 * deployments instead of leaving the event dispatcher reachable.
 */
class BlockNativeRoutes
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_if($request->is('_native', '_native/*'), 404);

        return $next($request);
    }
}
