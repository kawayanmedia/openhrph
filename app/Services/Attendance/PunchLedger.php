<?php

namespace App\Services\Attendance;

use App\Models\AttendancePunch;
use App\Models\Timesheet;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

/**
 * Keeps the append-only punch ledger and the per-day `logs` view in step.
 *
 * The ledger is the audit record. `timesheets.logs` is rebuilt from active
 * ledger rows after a void or a manual replacement so the calculator, portal
 * and exports keep their simple per-day input.
 */
final class PunchLedger
{
    public function record(Timesheet $timesheet, array $entry): ?AttendancePunch
    {
        if (! Schema::hasTable('attendance_punches')) {
            return null;
        }

        $employeeId = (int) ($timesheet->employee_id ?? $entry['employee_id'] ?? 0);
        $punchedAt = $this->moment($timesheet, $entry);

        if ($employeeId === 0 || $punchedAt === null) {
            return null;
        }

        $direction = (int) ($entry['log_type'] ?? 0) === 1 ? 1 : 0;
        $companyId = (int) ($timesheet->company_id ?? 0);

        $punch = AttendancePunch::query()
            ->where('company_id', $companyId)
            ->where('employee_id', $employeeId)
            ->where('punched_at', $punchedAt->toDateTimeString())
            ->where('direction', $direction)
            ->first();

        if ($punch !== null) {
            if ($punch->isVoided()) {
                $punch->forceFill([
                    'voided_at' => null,
                    'voided_by' => null,
                    'void_reason' => null,
                ])->save();
            }

            return $punch;
        }

        try {
            return AttendancePunch::create([
                'company_id' => $companyId ?: null,
                'employee_id' => $employeeId,
                'timesheet_id' => $timesheet->getKey(),
                'workday_date' => $entry['log_date'] ?? $timesheet->log_date?->toDateString(),
                'punched_at' => $punchedAt->toDateTimeString(),
                'direction' => $direction,
                'source' => (string) ($entry['source'] ?? 'biometric'),
                'device' => $entry['device'] ?? null,
                'request_id' => $entry['request_id'] ?? null,
                'raw' => $entry,
            ]);
        } catch (QueryException) {
            // Two devices can deliver the same punch at once. The unique key
            // wins and the second writer returns the existing row.
            $query = AttendancePunch::query()
                ->where('employee_id', $employeeId)
                ->where('punched_at', $punchedAt->toDateTimeString())
                ->where('direction', $direction);

            if ($companyId > 0) {
                $query->where('company_id', $companyId);
            }

            return $query->first();
        }
    }

    /**
     * Replace every active punch on the day with the supplied entries.
     *
     * @param  array<int, array<string, mixed>>  $entries
     */
    public function replace(Timesheet $timesheet, array $entries): void
    {
        if (! Schema::hasTable('attendance_punches')) {
            return;
        }

        $this->voidForTimesheet($timesheet, 'Replaced by a manual correction');

        foreach ($entries as $entry) {
            $this->record($timesheet, $entry);
        }

        $this->syncLogs($timesheet);
    }

    public function void(AttendancePunch $punch, ?int $userId = null, string $reason = 'Voided by a timekeeper'): bool
    {
        if ($punch->isVoided()) {
            return false;
        }

        $punch->forceFill([
            'voided_at' => now(),
            'voided_by' => $userId ?? Auth::id(),
            'void_reason' => $reason,
        ])->save();

        $timesheet = $punch->timesheet
            ?? Timesheet::query()
                ->where('employee_id', $punch->employee_id)
                ->whereDate('log_date', $punch->workday_date)
                ->first();

        if ($timesheet === null) {
            return true;
        }

        $this->syncLogs($timesheet);
        app(TimesheetCalculator::class)->compute($timesheet);

        return true;
    }

    /**
     * Rebuild `timesheets.logs` from active ledger rows.
     */
    public function syncLogs(Timesheet $timesheet): void
    {
        if (! Schema::hasTable('attendance_punches')) {
            return;
        }

        $logs = AttendancePunch::query()
            ->where('timesheet_id', $timesheet->getKey())
            ->active()
            ->orderBy('punched_at')
            ->get()
            ->map(fn (AttendancePunch $punch): array => [
                'employee_id' => (string) $punch->employee_id,
                'log_date' => $punch->workday_date?->toDateString(),
                'time' => $punch->punched_at?->format('H:i:s'),
                'log_type' => $punch->direction,
                'source' => $punch->source,
                'device' => $punch->device,
                'recorded_at' => $punch->created_at?->toDateTimeString(),
                'punched_at' => $punch->punched_at?->toDateTimeString(),
                'request_id' => $punch->request_id,
            ])
            ->values()
            ->all();

        $timesheet->logs = $logs;
        $timesheet->save();
    }

    private function voidForTimesheet(Timesheet $timesheet, string $reason): void
    {
        AttendancePunch::query()
            ->where('timesheet_id', $timesheet->getKey())
            ->active()
            ->update([
                'voided_at' => now(),
                'voided_by' => Auth::id(),
                'void_reason' => $reason,
            ]);
    }

    /**
     * @param  array<string, mixed>  $entry
     */
    private function moment(Timesheet $timesheet, array $entry): ?CarbonImmutable
    {
        if (! empty($entry['punched_at'])) {
            return CarbonImmutable::parse((string) $entry['punched_at']);
        }

        $date = $entry['log_date'] ?? $timesheet->log_date?->toDateString();
        $time = $entry['time'] ?? null;

        if (! is_string($date) || $date === '' || ! is_string($time) || $time === '') {
            return null;
        }

        return CarbonImmutable::parse($date.' '.$time);
    }
}
