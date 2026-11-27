<?php

namespace KawayanMedia\Attendance\Adms;

use Closure;
use Illuminate\Http\Request;
use KawayanMedia\Attendance\Contracts\Devices\DeviceRepository;
use KawayanMedia\Attendance\Devices\DeviceDescriptor;
use Symfony\Component\HttpFoundation\Response;

/**
 * Identifies the device that is calling the ADMS endpoints.
 *
 * Devices are matched on the serial number they report. A device that has a
 * push key stored must also supply it as `?key=` (older firmware) or in the
 * `X-Push-Key` header. Unknown serials are rejected so a stray device cannot
 * write into any tenant.
 */
final class AuthenticateAdmsDevice
{
    public function __construct(private readonly DeviceRepository $devices) {}

    public function handle(Request $request, Closure $next): Response
    {
        $serial = $this->serial($request);

        abort_if($serial === '', 400, 'Missing device serial number (SN).');

        $device = $this->devices->findBySerial($serial);

        abort_if($device === null, 401, 'Unknown device serial number.');
        abort_unless($device->isActive, 403, 'This device is disabled.');

        $this->authorisePushKey($request, $device);

        $request->attributes->set('attendance.device', $device);

        return $next($request);
    }

    private function serial(Request $request): string
    {
        foreach (['SN', 'sn', 'serial', 'serial_number'] as $key) {
            $value = $request->query($key) ?? $request->input($key);

            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        $body = (string) $request->getContent();

        if (preg_match('/(?:^|&)SN=([^&\s]+)/i', $body, $matches) === 1) {
            return trim(urldecode($matches[1]));
        }

        return '';
    }

    private function authorisePushKey(Request $request, DeviceDescriptor $device): void
    {
        $required = (bool) config('attendance-devices.adms.push_key_required', false);

        if ($device->pushKeyHash === null || $device->pushKeyHash === '') {
            abort_if($required, 401, 'This device must be configured with a push key.');

            return;
        }

        $key = $request->query('key') ?? $request->header('X-Push-Key');

        abort_if(
            ! is_string($key) || $key === '' || ! hash_equals($device->pushKeyHash, hash('sha256', $key)),
            401,
            'Invalid device push key.',
        );
    }
}
