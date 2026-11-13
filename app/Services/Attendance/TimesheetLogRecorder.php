<?php

namespace App\Services\Attendance;

use App\Gateways\GenerateTimesheetGateway;
use App\Models\Device;
use App\Models\Employee;
use App\Models\Timesheet;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;

/**
 * Records raw attendance punches into a timesheet's `logs` array.
 *
 * Hours and rates are intentionally left untouched: raw logs are stored so an
 * administrator can compute them with the existing timesheet tooling.
 */
class TimesheetLogRecorder
{
    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_DUPLICATE = 'duplicate';

    public const STATUS_UNMATCHED = 'unmatched';

    public const STATUS_INVALID = 'invalid';

    public function __construct(private readonly AttendancePeriod $period) {}

    /**
     * Find the timesheet for the day without creating it.
     */
    public function findFor(Employee $employee, CarbonInterface $date): ?Timesheet
    {
        return $this->findTimesheet($employee, CarbonImmutable::parse($date)->startOfDay());
    }

    /**
     * Find the timesheet for the day, generating it from the schedule when missing.
     */
    public function timesheetFor(Employee $employee, CarbonInterface $date): Timesheet
    {
        $day = CarbonImmutable::parse($date)->startOfDay();

        $timesheet = $this->findTimesheet($employee, $day);

        if ($timesheet !== null) {
            return $timesheet;
        }

        (new GenerateTimesheetGateway)->addDays($employee, CarbonPeriod::create($day, $day));

        return $this->findTimesheet($employee, $day) ?? $this->createTimesheet($employee, $day);
    }

    /**
     * The next punch type for the day: 0 (in) when empty or after an out punch.
     */
    public function nextLogType(Timesheet $timesheet): int
    {
        $last = collect($timesheet->logs ?? [])->last();

        if ($last === null) {
            return 0;
        }

        return (int) ($last['log_type'] ?? 0) === 0 ? 1 : 0;
    }

    /**
     * Whether the employee may still punch into the given timesheet.
     */
    public function canRecord(Employee $employee, Timesheet $timesheet): bool
    {
        if ((bool) $timesheet->is_locked) {
            return false;
        }

        return ! $this->period->isClosedFor($employee, $timesheet->log_date);
    }

    /**
     * Store a punch for the given moment.
     *
     * @return array{
     *     status: string,
     *     employee_id: int|null,
     *     log_date: string,
     *     time: string,
     *     log_type: int,
     *     locked: bool
     * }
     */
    public function record(
        Employee $employee,
        CarbonInterface $at,
        string $source = 'portal',
        ?Device $device = null,
        ?int $logType = null,
    ): array {
        $moment = CarbonImmutable::parse($at);
        $timesheet = $this->timesheetFor($employee, $moment);
        $locked = (bool) $timesheet->is_locked;

        $logType ??= $this->nextLogType($timesheet);
        $logType = $logType === 1 ? 1 : 0;

        $entry = [
            'employee_id' => (string) ($employee->biometric_id ?? $employee->employee_number),
            'log_date' => $moment->toDateString(),
            'time' => $moment->format('H:i:s'),
            'log_type' => $logType,
            'source' => $source,
            'device' => $device?->name,
            'recorded_at' => now()->toDateTimeString(),
        ];

        if (! $this->append($timesheet, $entry)) {
            return [
                'status' => self::STATUS_DUPLICATE,
                'employee_id' => $employee->getKey(),
                'log_date' => $entry['log_date'],
                'time' => $entry['time'],
                'log_type' => $logType,
                'locked' => $locked,
            ];
        }

        // Keep the legacy AM/PM columns in step for screens that still read them.
        if (! $locked) {
            $this->mirrorTimeColumns($timesheet, $moment, $logType);
        }

        $timesheet->save();

        return [
            'status' => self::STATUS_ACCEPTED,
            'employee_id' => $employee->getKey(),
            'log_date' => $entry['log_date'],
            'time' => $entry['time'],
            'log_type' => $logType,
            'locked' => $locked,
        ];
    }

    /**
     * Merge a raw log into the timesheet, rejecting identical punches.
     *
     * @param  array<string, mixed>  $entry
     */
    public function append(Timesheet $timesheet, array $entry): bool
    {
        $logs = collect($timesheet->logs ?? []);

        $isDuplicate = $logs->contains(function ($log) use ($entry): bool {
            return ($log['time'] ?? null) === ($entry['time'] ?? null)
                && (int) ($log['log_type'] ?? 0) === (int) ($entry['log_type'] ?? 0);
        });

        if ($isDuplicate) {
            return false;
        }

        $logs->push($entry);

        $timesheet->logs = $logs->sortBy('time')->values()->all();

        return true;
    }

    private function findTimesheet(Employee $employee, CarbonImmutable $day): ?Timesheet
    {
        return Timesheet::query()
            ->where('employee_id', $employee->getKey())
            ->whereDate('log_date', $day->toDateString())
            ->first();
    }

    private function createTimesheet(Employee $employee, CarbonImmutable $day): Timesheet
    {
        $timesheet = new Timesheet;
        $timesheet->employee_id = $employee->getKey();
        $timesheet->employee_number = $employee->employee_number;
        $timesheet->biometric_id = $employee->biometric_id;
        $timesheet->log_date = $day->toDateString();
        $timesheet->day_type = Timesheet::REGULAR_DAY;
        $timesheet->rate_code = Timesheet::REGULAR_DAY;
        $timesheet->schedule_id = $employee->schedule_id;
        $timesheet->office_id = $employee->office_id;
        $timesheet->department_id = $employee->department_id;
        $timesheet->save();

        return $timesheet;
    }

    private function mirrorTimeColumns(Timesheet $timesheet, CarbonImmutable $moment, int $logType): void
    {
        $time = $moment->format('H:i');
        $isAfternoon = $time >= '12:00';

        if ($logType === 0) {
            if ($isAfternoon) {
                $timesheet->pm_login = $time;
            } else {
                $timesheet->am_login = $time;
            }

            return;
        }

        if ($isAfternoon) {
            $timesheet->pm_logout = $time;
        } else {
            $timesheet->am_logout = $time;
        }
    }
}
