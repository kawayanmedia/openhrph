<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Services\Attendance\Devices\EloquentDeviceRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceDeviceController extends Controller
{
    /**
     * Devices the on-premise agent should relay for the authenticated company.
     *
     * The agent authenticates with `X-Company-Key`; only pullable (LAN) devices
     * are returned, together with the credentials needed to reach them. Serve
     * this over HTTPS only.
     */
    public function index(Request $request, EloquentDeviceRepository $devices): JsonResponse
    {
        $list = [];

        Device::query()
            ->pullable()
            ->orderBy('id')
            ->each(function (Device $device) use ($devices, &$list): void {
                $list[] = $devices->descriptorFor($device)->toArray();
            });

        return response()->json(['data' => ['devices' => $list]]);
    }
}
