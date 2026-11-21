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

    public const STATUS_REJECTED = 'rejected';

    public const REASON_DEBOUNCE = 'debounce';

    public const REASON_LOCKED = 'locked';

    public const REASON_CLOSED = 'closed';

    public function __construct(private readonly AttendancePeriod $period) {}

    /**
     * Find the timesheet for the punch's workday without creating it.
     *
     * For overnight shifts the workday can be the previous calendar day, so a
     * time-out at 06:00 is matched to the shift that started at 22:00.
     */
    public function findFor(Employee $employee, CarbonInterface $date): ?Timesheet
    {
        return $this->findTimesheet($employee, $this->workdayFor($employee, $date));
    }

    /**
     * Find the timesheet for an explicit calendar date without creating it.
     */
    public function findForDate(Employee $employee, CarbonInterface $date): ?Timesheet
    {
        return $this->findTimesheet($employee, CarbonImmutable::parse($date)->startOfDay());
    }

    /**
     * Find the timesheet for the punch's workday, generating it when missing.
     */
    public function timesheetFor(Employee $employee, CarbonInterface $date): Timesheet
    {
        return $this->ensureForDate($employee, $this->workdayFor($employee, $date));
    }

    /**
     * Find the timesheet for an explicit calendar date, generating it when missing.
     */
    public function ensureForDate(Employee $employee, CarbonInterface $date): Timesheet
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
     * Resolve the workday a punch belongs to.
     *
     * Only overnight schedules shift the workday: a punch that arrives before
     * the shift's start time closes the previous day's outstanding time-in.
     */
    public function workdayFor(Employee $employee, CarbonInterface $moment): CarbonImmutable
    {
        $moment = CarbonImmutable::parse($moment);
        $today = $moment->startOfDay();
        $schedule = $employee->schedule;

        if ($schedule === null) {
            return $today;
        }

        $shiftStart = $this->clock($schedule->time_in);
        $shiftEnd = $this->clock($schedule->time_out);

        if ($shiftStart === null || $shiftEnd === null || $shiftStart <= $shiftEnd) {
            return $today;
        }

        if ($moment->format('H:i:s') >= $shiftStart) {
            return $today;
        }

        $previous = $this->findTimesheet($employee, $today->subDay());

        if ($previous !== null && $this->nextLogType($previous) === 1) {
            return $today->subDay();
        }

        return $today;
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
     *     locked: bool,
     *     reason?: string
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
        $workday = $this->workdayFor($employee, $moment);
        $timesheet = $this->findTimesheet($employee, $workday);

        $rejected = $this->rejectionFor($employee, $timesheet, $workday);

        if ($rejected !== null) {
            return [
                'status' => self::STATUS_REJECTED,
                'employee_id' => $employee->getKey(),
                'log_date' => $workday->toDateString(),
                'time' => $moment->format('H:i:s'),
                'log_type' => $logType === 1 ? 1 : 0,
                'locked' => (bool) ($timesheet?->is_locked ?? false),
                'reason' => $rejected,
            ];
        }

        $timesheet ??= $this->ensureForDate($employee, $workday);
        $locked = (bool) $timesheet->is_locked;

        $logType ??= $this->nextLogType($timesheet);
        $logType = $logType === 1 ? 1 : 0;

        $lastPunch = $this->lastPunchMoment($timesheet);

        if ($lastPunch !== null && $this->isWithinDebounceWindow($moment, $lastPunch)) {
            return [
                'status' => self::STATUS_DUPLICATE,
                'employee_id' => $employee->getKey(),
                'log_date' => $workday->toDateString(),
                'time' => $moment->format('H:i:s'),
                'log_type' => $logType,
                'locked' => $locked,
                'reason' => self::REASON_DEBOUNCE,
            ];
        }

        $entry = [
            'employee_id' => (string) ($employee->biometric_id ?? $employee->employee_number),
            'log_date' => $workday->toDateString(),
            'time' => $moment->format('H:i:s'),
            'log_type' => $logType,
            'source' => $source,
            'device' => $device?->name,
            'recorded_at' => now()->toDateTimeString(),
        ];

        if (! $this->appendLog($timesheet, $entry)) {
            return [
                'status' => self::STATUS_DUPLICATE,
                'employee_id' => $employee->getKey(),
                'log_date' => $entry['log_date'],
                'time' => $entry['time'],
                'log_type' => $logType,
                'locked' => $locked,
            ];
        }

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

        $timesheet->logs = $logs
            ->sortBy(fn (array $log): string => $this->chronologicalKey($log))
            ->values()
            ->all();

        return true;
    }

    /**
     * Append a log entry, mirror it into the legacy columns and save.
     *
     * @param  array<string, mixed>  $entry
     */
    public function appendLog(Timesheet $timesheet, array $entry): bool
    {
        if (! $this->append($timesheet, $entry)) {
            return false;
        }

        $timesheetDate = $timesheet->log_date?->toDateString();
        $logDate = $entry['log_date'] ?? $timesheetDate;

        if (is_string($logDate) && is_string($entry['time'] ?? null)) {
            $moment = CarbonImmutable::parse($logDate.' '.$entry['time']);
            $logType = (int) ($entry['log_type'] ?? 0);

            $this->mirrorTimeColumns($timesheet, $moment, $logType, $this->isOvernightOut($timesheet, $moment, $logType));
        }

        $timesheet->save();

        return true;
    }

    private function rejectionFor(Employee $employee, ?Timesheet $timesheet, CarbonImmutable $workday): ?string
    {
        if ($timesheet !== null && (bool) $timesheet->is_locked) {
            return self::REASON_LOCKED;
        }

        if ($this->period->isClosedFor($employee, $workday)) {
            return self::REASON_CLOSED;
        }

        return null;
    }

    private function lastPunchMoment(Timesheet $timesheet): ?CarbonImmutable
    {
        $latest = null;

        foreach (collect($timesheet->logs ?? []) as $log) {
            $moment = $this->punchMoment($timesheet, $log);

            if ($moment !== null && ($latest === null || $moment->greaterThan($latest))) {
                $latest = $moment;
            }
        }

        return $latest;
    }

    /**
     * @param  array<string, mixed>  $log
     */
    private function punchMoment(Timesheet $timesheet, array $log): ?CarbonImmutable
    {
        if (! empty($log['recorded_at'])) {
            return CarbonImmutable::parse($log['recorded_at']);
        }

        $date = $log['log_date'] ?? $timesheet->log_date?->toDateString();
        $time = $log['time'] ?? null;

        if (! is_string($date) || ! is_string($time) || $time === '') {
            return null;
        }

        return CarbonImmutable::parse($date.' '.$time);
    }

    private function isWithinDebounceWindow(CarbonImmutable $moment, CarbonImmutable $lastPunch): bool
    {
        $window = (int) config('attendance.punch_debounce_seconds', 60);

        if ($window <= 0) {
            return false;
        }

        return abs($moment->getTimestamp() - $lastPunch->getTimestamp()) < $window;
    }

    /**
     * @param  array<string, mixed>  $log
     */
    private function chronologicalKey(array $log): string
    {
        if (! empty($log['recorded_at'])) {
            return (string) $log['recorded_at'];
        }

        return trim(($log['log_date'] ?? '').' '.($log['time'] ?? ''));
    }

    private function findTimesheet(Employee $employee, CarbonImmutable $day): ?Timesheet
    {
        return Timesheet::query()
            ->where('employee_id', $employee->getKey())
            ->where('log_date', '>=', $day->toDateString())
            ->where('log_date', '<', $day->addDay()->toDateString())
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

    /**
     * A time-out before the start of an overnight shift belongs to the shift
     * that started the evening before, so it is mirrored to `am_out_morning`.
     */
    private function isOvernightOut(Timesheet $timesheet, CarbonImmutable $moment, int $logType): bool
    {
        if ($logType !== 1) {
            return false;
        }

        $schedule = $timesheet->schedule;

        if ($schedule === null) {
            return false;
        }

        $shiftStart = $this->clock($schedule->time_in);
        $shiftEnd = $this->clock($schedule->time_out);

        if ($shiftStart === null || $shiftEnd === null || $shiftStart <= $shiftEnd) {
            return false;
        }

        return $moment->format('H:i:s') < $shiftStart;
    }

    private function mirrorTimeColumns(Timesheet $timesheet, CarbonImmutable $moment, int $logType, bool $overnight): void
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

        if ($overnight) {
            $timesheet->am_out_morning = $time;

            return;
        }

        if ($isAfternoon) {
            $timesheet->pm_logout = $time;
        } else {
            $timesheet->am_logout = $time;
        }
    }

    private function clock(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $value = substr($value, 0, 8);

        return strlen($value) === 5 ? $value.':00' : $value;
    }
}
