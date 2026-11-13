<?php

namespace App\Services\Attendance;

use App\Models\Device;
use App\Models\Employee;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * Turns raw clock logs coming from biometric devices or other systems into
 * timesheet log entries.
 */
class AttendanceLogIngestor
{
    public function __construct(private readonly TimesheetLogRecorder $recorder) {}

    /**
     * @param  array<int, array<string, mixed>>  $logs
     * @return array{
     *     accepted: int,
     *     duplicates: int,
     *     unmatched: int,
     *     invalid: int,
     *     results: array<int, array<string, mixed>>
     * }
     */
    public function ingest(array $logs, string $source = 'api', ?Device $device = null): array
    {
        $summary = [
            'accepted' => 0,
            'duplicates' => 0,
            'unmatched' => 0,
            'invalid' => 0,
            'results' => [],
        ];

        foreach (array_values($logs) as $index => $log) {
            try {
                $employee = $this->matchEmployee($log);

                if ($employee === null) {
                    $summary['unmatched']++;
                    $summary['results'][] = [
                        'index' => $index,
                        'status' => TimesheetLogRecorder::STATUS_UNMATCHED,
                        'message' => 'No employee matches the supplied biometric_id or employee_number.',
                    ];

                    continue;
                }

                $moment = $this->resolveMoment($log);

                if ($moment === null) {
                    $summary['invalid']++;
                    $summary['results'][] = [
                        'index' => $index,
                        'status' => TimesheetLogRecorder::STATUS_INVALID,
                        'message' => 'A valid at (or date + time) value is required.',
                    ];

                    continue;
                }

                $result = $this->recorder->record(
                    $employee,
                    $moment,
                    $source,
                    $device,
                    $this->resolveLogType($log),
                );

                $result['index'] = $index;

                if ($result['status'] === TimesheetLogRecorder::STATUS_DUPLICATE) {
                    $summary['duplicates']++;
                } else {
                    $summary['accepted']++;
                }

                $summary['results'][] = $result;
            } catch (Throwable $exception) {
                report($exception);

                $summary['invalid']++;
                $summary['results'][] = [
                    'index' => $index,
                    'status' => TimesheetLogRecorder::STATUS_INVALID,
                    'message' => $exception->getMessage(),
                ];
            }
        }

        return $summary;
    }

    /**
     * @param  array<string, mixed>  $log
     */
    public function matchEmployee(array $log): ?Employee
    {
        $biometricId = $log['biometric_id'] ?? null;

        if ($biometricId !== null && $biometricId !== '') {
            $employee = Employee::query()
                ->where('biometric_id', (int) $biometricId)
                ->first();

            if ($employee !== null) {
                return $employee;
            }
        }

        $employeeNumber = $log['employee_number'] ?? null;

        if ($employeeNumber !== null && $employeeNumber !== '') {
            return Employee::query()
                ->where('employee_number', (string) $employeeNumber)
                ->first();
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $log
     */
    private function resolveMoment(array $log): ?CarbonImmutable
    {
        $moment = $log['at'] ?? $log['timestamp'] ?? null;

        if (is_string($moment) && $moment !== '') {
            try {
                return CarbonImmutable::parse($moment);
            } catch (Throwable) {
                return null;
            }
        }

        $date = $log['date'] ?? $log['log_date'] ?? null;
        $time = $log['time'] ?? null;

        if (is_string($date) && $date !== '' && is_string($time) && $time !== '') {
            try {
                return CarbonImmutable::parse($date.' '.$time);
            } catch (Throwable) {
                return null;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $log
     */
    private function resolveLogType(array $log): ?int
    {
        $type = $log['type'] ?? $log['log_type'] ?? null;

        if ($type === null || $type === '') {
            return null;
        }

        if (is_string($type)) {
            $normalized = strtolower(trim($type));

            if (in_array($normalized, ['in', 'login', 'time_in', '0'], true)) {
                return 0;
            }

            if (in_array($normalized, ['out', 'logout', 'time_out', '1'], true)) {
                return 1;
            }

            return null;
        }

        return (int) $type === 1 ? 1 : 0;
    }
}
