<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureDemoModeEnabled
{
    /**
     * Hide the demo registration and verification routes unless this
     * deployment is the public demo.
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(config('demo.enabled', false), 404);

        return $next($request);
    }
}
