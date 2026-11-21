<?php

namespace App\Services\Attendance;

use App\Models\Employee;
use Carbon\CarbonImmutable;
use KawayanMedia\Attendance\Import\FileImport;
use Throwable;

/**
 * Imports biometric log files into the timesheet `logs` arrays.
 *
 * The import merges instead of replacing, reports what could not be applied and
 * refuses to touch attendance that payroll has already locked.
 */
class TimesheetImport
{
    public function __construct(
        private readonly TimesheetLogRecorder $recorder,
        private readonly AttendancePeriod $period,
    ) {}

    /**
     * @return array{
     *     accepted: int,
     *     duplicates: int,
     *     unmatched: int,
     *     invalid: int,
     *     blocked: int,
     *     timesheets: int
     * }
     */
    public function import(string $path): array
    {
        $fileImport = new FileImport;
        $fileImport->setLimit();
        $fileImport->filePath = $path;

        $summary = [
            'accepted' => 0,
            'duplicates' => 0,
            'unmatched' => 0,
            'invalid' => 0,
            'blocked' => 0,
            'timesheets' => 0,
        ];

        foreach ($fileImport->process() as $biometricId => $days) {
            $employee = Employee::query()->where('biometric_id', (int) $biometricId)->first();

            if ($employee === null) {
                foreach ($days as $logs) {
                    $summary['unmatched'] += count($logs);
                }

                continue;
            }

            foreach ($days as $date => $logs) {
                if (! is_string($date) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
                    $summary['invalid'] += count($logs);

                    continue;
                }

                try {
                    $day = CarbonImmutable::parse($date)->startOfDay();
                } catch (Throwable) {
                    $summary['invalid'] += count($logs);

                    continue;
                }

                $timesheet = $this->recorder->findForDate($employee, $day);

                $blocked = $timesheet === null
                    ? $this->period->isClosedFor($employee, $day)
                    : ! $this->recorder->canRecord($employee, $timesheet);

                if ($blocked) {
                    $summary['blocked'] += count($logs);

                    continue;
                }

                $timesheet ??= $this->recorder->ensureForDate($employee, $day);
                $summary['timesheets']++;

                foreach ($logs as $log) {
                    $time = $log['time'] ?? null;

                    if (! is_string($time) || $time === '') {
                        $summary['invalid']++;

                        continue;
                    }

                    $entry = [
                        'employee_id' => (string) $biometricId,
                        'log_date' => $day->toDateString(),
                        'time' => $time,
                        'log_type' => (int) ($log['log_type'] ?? 0) === 1 ? 1 : 0,
                        'source' => 'import',
                        'device' => null,
                        'recorded_at' => now()->toDateTimeString(),
                    ];

                    if ($this->recorder->appendLog($timesheet, $entry)) {
                        $summary['accepted']++;
                    } else {
                        $summary['duplicates']++;
                    }
                }
            }
        }

        return $summary;
    }
}
