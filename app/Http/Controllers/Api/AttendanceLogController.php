<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\IngestAttendanceLogsRequest;
use App\Models\Device;
use App\Models\Timesheet;
use App\Services\Attendance\AttendanceLogIngestor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceLogController extends Controller
{
    public function __construct(
        private readonly AttendanceLogIngestor $ingestor,
    ) {}

    /**
     * Accept one or more raw punches from a time clock or external system.
     */
    public function store(IngestAttendanceLogsRequest $request): JsonResponse
    {
        $device = $request->attributes->get('attendance.device');

        $summary = $this->ingestor->ingest(
            $request->validated()['logs'],
            $device instanceof Device ? 'device' : 'api',
            $device instanceof Device ? $device : null,
        );

        return response()->json(['data' => $summary]);
    }

    /**
     * Inspect the stored raw logs of an employee for a single day.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'employee_number' => ['nullable', 'string', 'max:64'],
            'biometric_id' => ['nullable', 'string', 'max:64'],
        ]);

        $employee = $this->ingestor->matchEmployee($validated);

        if ($employee === null) {
            return response()->json([
                'data' => [
                    'employee' => null,
                    'log_date' => $validated['date'],
                    'logs' => [],
                ],
            ]);
        }

        $timesheet = Timesheet::query()
            ->where('employee_id', $employee->getKey())
            ->whereDate('log_date', $validated['date'])
            ->first();

        return response()->json([
            'data' => [
                'employee' => [
                    'id' => $employee->getKey(),
                    'employee_number' => $employee->employee_number,
                    'biometric_id' => $employee->biometric_id,
                ],
                'log_date' => $validated['date'],
                'timesheet_id' => $timesheet?->getKey(),
                'is_locked' => (bool) ($timesheet?->is_locked ?? false),
                'logs' => $timesheet?->logs ?? [],
            ],
        ]);
    }
}
