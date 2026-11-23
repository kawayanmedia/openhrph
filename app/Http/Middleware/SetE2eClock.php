<?php

namespace App\Http\Middleware;

use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lets the browser suite punch past dates by shifting the application clock.
 *
 * The portal records "now" as the punch moment, so a multi-day attendance
 * journey needs the server to believe it is a different day per request. The
 * override is opt-in via a header and stays unavailable outside local
 * environments.
 */
class SetE2eClock
{
    public const HEADER = 'X-E2E-Now';

    public function handle(Request $request, Closure $next): Response
    {
        $moment = $request->header(self::HEADER);

        if (! is_string($moment) || $moment === '') {
            return $next($request);
        }

        if (! app()->environment('local', 'testing')) {
            abort(403, 'The E2E clock override is only available in local environments.');
        }

        Carbon::setTestNow(CarbonImmutable::parse($moment, config('app.timezone')));

        try {
            return $next($request);
        } finally {
            Carbon::setTestNow();
        }
    }
}
