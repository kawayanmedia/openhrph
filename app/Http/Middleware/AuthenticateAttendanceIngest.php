<?php

namespace App\Http\Middleware;

use App\Models\Company;
use App\Models\Device;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates attendance log push clients with either a company API key or a
 * device API key, and resolves the tenant for the request.
 */
class AuthenticateAttendanceIngest
{
    public function __construct(private readonly TenantContext $tenant) {}

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $companyKey = $request->header('X-Company-Key');
        $deviceKey = $request->header('X-Device-Key');

        if (($companyKey === null || $companyKey === '') && ($deviceKey === null || $deviceKey === '')) {
            abort(401, 'Missing X-Company-Key or X-Device-Key header.');
        }

        if ($companyKey !== null && $companyKey !== '') {
            $company = Company::query()
                ->where('api_key', hash('sha256', $companyKey))
                ->first();

            abort_unless($company !== null, 401, 'Invalid company API key.');

            return $this->withinTenant($company, $next, $request);
        }

        $device = Device::findByApiKey((string) $deviceKey);

        abort_unless($device !== null, 401, 'Invalid device API key.');
        abort_unless((bool) $device->is_active, 403, 'This device is disabled.');

        $request->attributes->set('attendance.device', $device);

        return $this->withinTenant($device->company_id, $next, $request, $device);
    }

    private function withinTenant(
        Company|int $tenant,
        Closure $next,
        Request $request,
        ?Device $device = null,
    ): Response {
        try {
            $this->tenant->set($tenant);

            if ($device !== null) {
                $device->markSeen();
            }

            return $next($request);
        } finally {
            $this->tenant->forget();
        }
    }
}
