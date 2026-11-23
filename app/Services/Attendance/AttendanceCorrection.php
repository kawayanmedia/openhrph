<?php

namespace App\Services\Attendance;

use App\Models\Employee;
use App\Models\TimesheetRequest;
use App\Services\HrAudit;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Applies an approved attendance request to the employee's timesheet.
 *
 * Approved corrections land in the same `logs` array as biometric and portal
 * punches, so the portal, exports and the attendance pipeline all see them.
 */
class AttendanceCorrection
{
    public const STATUS_APPLIED = 'applied';

    public const STATUS_DUPLICATE = 'duplicate';

    public const STATUS_LOCKED = 'locked';

    public const STATUS_CLOSED = 'closed';

    public const STATUS_INVALID = 'invalid';

    public function __construct(
        private readonly TimesheetLogRecorder $recorder,
        private readonly AttendancePeriod $period,
    ) {}

    /**
     * @return array{status: string, timesheet_id: int|null, message: string}
     */
    public function apply(TimesheetRequest $request, ?Employee $approver = null, ?string $remarks = null): array
    {
        return DB::transaction(function () use ($request, $approver, $remarks): array {
            $employee = $request->employee;

            if ($employee === null) {
                return $this->result(self::STATUS_INVALID, null, 'The request is not linked to an employee.');
            }

            if ($request->status === STATUS_APPROVED) {
                return $this->result(self::STATUS_DUPLICATE, null, 'The request was already approved.');
            }

            if ($request->log_date === null || $request->time === null) {
                return $this->result(self::STATUS_INVALID, null, 'The request needs a date and a time.');
            }

            $logDate = CarbonImmutable::parse($request->log_date->toDateString())->startOfDay();
            $moment = CarbonImmutable::parse($logDate->toDateString().' '.$request->time);
            $logType = (int) $request->log_type === 1 ? 1 : 0;

            $timesheet = $this->recorder->findForDate($employee, $logDate);

            if ($timesheet !== null && (bool) $timesheet->is_locked) {
                return $this->result(self::STATUS_LOCKED, $timesheet->getKey(), 'The timesheet is locked.');
            }

            if ($this->period->isClosedFor($employee, $logDate)) {
                return $this->result(self::STATUS_CLOSED, $timesheet?->getKey(), 'Attendance for this payroll period is closed.');
            }

            $timesheet ??= $this->recorder->ensureForDate($employee, $logDate);

            $entry = [
                'employee_id' => (string) ($employee->biometric_id ?? $employee->employee_number),
                'log_date' => $logDate->toDateString(),
                'time' => $moment->format('H:i:s'),
                'log_type' => $logType,
                'source' => 'attendance_request',
                'device' => null,
                'recorded_at' => now()->toDateTimeString(),
                'punched_at' => $moment->toDateTimeString(),
                'request_id' => $request->getKey(),
            ];

            $applied = $this->recorder->appendLog($timesheet, $entry);

            $request->approved_user_id = $approver?->getKey();
            $request->approved_at = now();
            $request->status = STATUS_APPROVED;

            if ($remarks !== null && $remarks !== '') {
                $request->remarks = $remarks;
            }

            $request->save();

            HrAudit::record((int) $request->company_id, 'attendance_request_approved', $employee->getKey(), $remarks, [
                'request_id' => $request->getKey(),
                'timesheet_id' => $timesheet->getKey(),
                'log_date' => $logDate->toDateString(),
                'time' => $moment->format('H:i:s'),
                'log_type' => $logType,
                'punch' => $applied ? 'recorded' : 'already_recorded',
            ]);

            return $this->result(
                $applied ? self::STATUS_APPLIED : self::STATUS_DUPLICATE,
                $timesheet->getKey(),
                $applied ? 'Attendance approved.' : 'That punch was already recorded; the request was approved.',
            );
        });
    }

    /**
     * @return array{status: string, timesheet_id: int|null, message: string}
     */
    private function result(string $status, ?int $timesheetId, string $message): array
    {
        return [
            'status' => $status,
            'timesheet_id' => $timesheetId,
            'message' => $message,
        ];
    }
}
