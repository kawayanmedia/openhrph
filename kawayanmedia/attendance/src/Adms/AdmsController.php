<?php

namespace KawayanMedia\Attendance\Adms;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use KawayanMedia\Attendance\Contracts\Devices\DeviceRepository;
use KawayanMedia\Attendance\Contracts\Devices\PunchSink;
use KawayanMedia\Attendance\Devices\DeviceDescriptor;
use KawayanMedia\Attendance\Devices\DeviceEvent;

/**
 * The HTTP surface ADMS/iClock devices talk to.
 *
 * All replies are plain text because that is what the firmware parses:
 * `GET OPTION ...` for a handshake, `OK: <n>` for an accepted upload and `OK`
 * for keepalives and command results.
 */
final class AdmsController
{
    public function __construct(
        private readonly DeviceRepository $devices,
        private readonly PunchSink $sink,
    ) {}

    /**
     * Handshake (`options=all`) and ATTLOG upload share one URL.
     */
    public function cdata(Request $request): Response
    {
        $device = $this->device($request);
        $table = strtoupper((string) ($request->query('table') ?? $request->input('table') ?? ''));
        $body = (string) $request->getContent();

        if ($this->isHandshake($request, $body, $table)) {
            return $this->text(AdmsProtocol::handshake(
                (string) ($device->serialNumber ?? ''),
                $device->timezone,
                (array) config('attendance-devices.adms', []),
            ));
        }

        $lines = AdmsProtocol::lineCount($body);

        // OPERLOG (door alarms, verification changes) and ATTPHOTO are not
        // punches; acknowledge them so the device does not retry forever.
        if (in_array($table, ['OPERLOG', 'ATTPHOTO', 'OPLOG'], true)) {
            return $this->text(AdmsProtocol::accepted($lines));
        }

        $records = AdmsProtocol::parseAttendance($body, $device->timezone);

        $summary = $this->devices->runInTenant(
            $device->tenantId,
            fn (): array => $this->sink->ingest($records, 'device', $device),
        );

        $this->devices->markSeen($device->id);

        $this->devices->recordEvent(DeviceEvent::fromSummary(
            $device,
            DeviceEvent::KIND_PUSH,
            ['records' => $lines] + $summary,
            null,
            200,
            'iclock/cdata',
        ));

        return $this->text(AdmsProtocol::accepted($lines));
    }

    /**
     * The device polls for queued commands; none are queued, so `OK` is enough.
     */
    public function getRequest(Request $request): Response
    {
        $this->devices->markSeen($this->device($request)->id);

        return $this->text('OK');
    }

    /**
     * Results of commands executed by the device.
     */
    public function deviceCommand(Request $request): Response
    {
        return $this->text('OK');
    }

    public function ping(Request $request): Response
    {
        $this->devices->markSeen($this->device($request)->id);

        return $this->text('OK');
    }

    private function isHandshake(Request $request, string $body, string $table): bool
    {
        if ($request->query('options') !== null || $request->input('options') !== null) {
            return true;
        }

        if ($table !== '') {
            return false;
        }

        return $body === '' || str_contains(strtolower($body), 'options=');
    }

    private function device(Request $request): DeviceDescriptor
    {
        $device = $request->attributes->get('attendance.device');

        if (! $device instanceof DeviceDescriptor) {
            abort(401, 'No device was authenticated for this request.');
        }

        return $device;
    }

    private function text(string $body): Response
    {
        return response($body, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
