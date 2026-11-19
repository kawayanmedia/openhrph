<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;

/**
 * Applies Laravel's verified-email check only on the public demo.
 *
 * Self-hosted installations and accounts created by `admin:create` keep the
 * historical behaviour: email verification is required for demo sign-ups only.
 */
class EnsureDemoEmailVerified extends EnsureEmailIsVerified
{
    public function handle($request, Closure $next, $redirectToRoute = null)
    {
        if (! config('demo.enabled')) {
            return $next($request);
        }

        return parent::handle($request, $next, $redirectToRoute);
    }
}
