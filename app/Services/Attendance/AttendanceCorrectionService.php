<?php

namespace App\Services\Attendance;

use App\Models\AttendanceCorrection;
use App\Models\AttendanceCorrectionDecision;
use App\Models\AttendancePunch;
use App\Models\Employee;
use App\Models\User;
use App\Services\Hr\AuditLogger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Correction workflow that never mutates the original device punch.
 */
class AttendanceCorrectionService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function request(Employee $employee, array $attributes, User $actor): AttendanceCorrection
    {
        return DB::transaction(function () use ($employee, $attributes, $actor): AttendanceCorrection {
            $punch = ! empty($attributes['attendance_punch_id'])
                ? AttendancePunch::withoutTenant()->find($attributes['attendance_punch_id'])
                : null;

            $correction = AttendanceCorrection::create([
                ...$attributes,
                'company_id' => $employee->company_id,
                'employee_id' => $employee->getKey(),
                'original_values' => $punch === null ? null : [
                    'punched_at' => $punch->punched_at?->toDateTimeString(),
                    'direction' => $punch->direction,
                    'source' => $punch->source,
                    'voided_at' => $punch->voided_at?->toDateTimeString(),
                ],
                'status' => AttendanceCorrection::STATUS_SUBMITTED,
                'requested_by' => $actor->getKey(),
            ]);

            $this->audit->record('attendance.correction_requested', $correction, after: $correction->getAttributes(), metadata: [
                'actor_id' => $actor->getKey(),
            ]);

            return $correction->refresh();
        });
    }

    public function approve(AttendanceCorrection $correction, User $actor, ?string $comments = null): AttendanceCorrection
    {
        return $this->decide($correction, AttendanceCorrection::STATUS_APPROVED, $actor, 'approve', $comments);
    }

    public function reject(AttendanceCorrection $correction, User $actor, string $comments): AttendanceCorrection
    {
        return $this->decide($correction, AttendanceCorrection::STATUS_REJECTED, $actor, 'reject', $comments);
    }

    public function apply(AttendanceCorrection $correction, User $actor): AttendanceCorrection
    {
        return DB::transaction(function () use ($correction, $actor): AttendanceCorrection {
            $correction = AttendanceCorrection::withoutTenant()->whereKey($correction->getKey())->lockForUpdate()->firstOrFail();

            if ($correction->status !== AttendanceCorrection::STATUS_APPROVED) {
                throw ValidationException::withMessages([
                    'status' => 'Only an approved correction can be applied.',
                ]);
            }

            if ($correction->applied_at !== null) {
                return $correction;
            }

            $requested = $correction->requested_values ?? [];
            $punchedAt = $requested['punched_at'] ?? null;

            if ($punchedAt === null) {
                throw ValidationException::withMessages([
                    'requested_values' => 'The correction must include the corrected punch time.',
                ]);
            }

            AttendancePunch::create([
                'company_id' => $correction->company_id,
                'employee_id' => $correction->employee_id,
                'timesheet_id' => $correction->timesheet_id,
                'workday_date' => $correction->workday_date->toDateString(),
                'punched_at' => Carbon::parse($punchedAt),
                'direction' => (int) ($requested['direction'] ?? 0),
                'source' => 'correction',
                'device' => null,
                'request_id' => $correction->getKey(),
                'raw' => [
                    'correction_id' => $correction->getKey(),
                    'original_values' => $correction->original_values,
                    'requested_values' => $correction->requested_values,
                ],
            ]);

            $correction->forceFill([
                'status' => AttendanceCorrection::STATUS_APPLIED,
                'applied_at' => now(),
            ])->save();

            $this->recordDecision($correction, $actor, 'apply', null);

            $this->audit->record('attendance.correction_applied', $correction, after: [
                'punched_at' => (string) $punchedAt,
            ], metadata: ['actor_id' => $actor->getKey()]);

            return $correction->refresh();
        });
    }

    protected function decide(
        AttendanceCorrection $correction,
        string $status,
        User $actor,
        string $action,
        ?string $comments,
    ): AttendanceCorrection {
        return DB::transaction(function () use ($correction, $status, $actor, $action, $comments): AttendanceCorrection {
            $correction = AttendanceCorrection::withoutTenant()->whereKey($correction->getKey())->lockForUpdate()->firstOrFail();

            if ($correction->status !== AttendanceCorrection::STATUS_SUBMITTED) {
                throw ValidationException::withMessages([
                    'status' => 'Only a submitted correction can be reviewed.',
                ]);
            }

            $correction->forceFill([
                'status' => $status,
                'reviewed_by' => $actor->getKey(),
                'reviewed_at' => now(),
            ])->save();

            $this->recordDecision($correction, $actor, $action, $comments);

            $this->audit->record('attendance.correction_'.$action, $correction, metadata: [
                'actor_id' => $actor->getKey(),
                'comments' => $comments,
            ]);

            return $correction->refresh();
        });
    }

    protected function recordDecision(AttendanceCorrection $correction, User $actor, string $action, ?string $comments): void
    {
        AttendanceCorrectionDecision::create([
            'company_id' => $correction->company_id,
            'attendance_correction_id' => $correction->getKey(),
            'actor_id' => $actor->getKey(),
            'action' => $action,
            'comments' => $comments,
            'decided_at' => now(),
        ]);
    }
}
