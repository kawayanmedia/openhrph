<?php

use App\Models\AttendanceCorrection;
use App\Models\AttendanceCorrectionDecision;
use App\Models\AttendancePunch;
use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use App\Services\Attendance\AttendanceCorrectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->company = Company::factory()->create(['name' => 'Correction Co']);
    $this->user = User::factory()->forCompany($this->company)->create();
    $this->actingAs($this->user);

    $this->employee = Employee::factory()->create(['company_id' => $this->company->id]);

    $this->punch = AttendancePunch::create([
        'company_id' => $this->company->id,
        'employee_id' => $this->employee->id,
        'workday_date' => '2026-11-02',
        'punched_at' => '2026-11-02 08:17:00',
        'direction' => 0,
        'source' => 'biometric',
        'device' => 'Main Gate',
    ]);

    $this->service = app(AttendanceCorrectionService::class);
});

it('records a correction request without touching the original punch', function (): void {
    $correction = $this->service->request($this->employee, [
        'attendance_punch_id' => $this->punch->id,
        'workday_date' => '2026-11-02',
        'correction_type' => AttendanceCorrection::TYPE_WRONG_TIME,
        'requested_values' => [
            'punched_at' => '2026-11-02 08:00:00',
            'direction' => 0,
        ],
        'reason' => 'Clocked in late due to device error.',
    ], $this->user);

    expect($correction->status)->toBe(AttendanceCorrection::STATUS_SUBMITTED)
        ->and((int) $correction->company_id)->toBe($this->company->id)
        ->and((int) $correction->requested_by)->toBe($this->user->id)
        ->and($correction->original_values['punched_at'])->toBe('2026-11-02 08:17:00');

    expect($this->punch->fresh()->punched_at?->toDateTimeString())->toBe('2026-11-02 08:17:00');
});

it('applies an approved correction as a new punch and never mutates the original', function (): void {
    $correction = $this->service->request($this->employee, [
        'attendance_punch_id' => $this->punch->id,
        'workday_date' => '2026-11-02',
        'correction_type' => AttendanceCorrection::TYPE_WRONG_TIME,
        'requested_values' => ['punched_at' => '2026-11-02 08:00:00', 'direction' => 0],
        'reason' => 'Device error.',
    ], $this->user);

    $this->service->approve($correction, $this->user, 'Verified with the guard log.');
    $applied = $this->service->apply($correction->refresh(), $this->user);

    expect($applied->status)->toBe(AttendanceCorrection::STATUS_APPLIED)
        ->and($applied->applied_at)->not->toBeNull();

    expect($this->punch->fresh()->punched_at?->toDateTimeString())->toBe('2026-11-02 08:17:00')
        ->and($this->punch->fresh()->voided_at)->toBeNull();

    $replacement = AttendancePunch::withoutTenant()->where('source', 'correction')->sole();

    expect($replacement->punched_at?->toDateTimeString())->toBe('2026-11-02 08:00:00')
        ->and((int) $replacement->request_id)->toBe($correction->id);

    expect(AttendanceCorrectionDecision::withoutTenant()->where('attendance_correction_id', $correction->id)->count())->toBe(2);
});

it('rejects invalid correction transitions', function (): void {
    $correction = $this->service->request($this->employee, [
        'workday_date' => '2026-11-02',
        'correction_type' => AttendanceCorrection::TYPE_MISSING_PUNCH,
        'requested_values' => ['punched_at' => '2026-11-02 17:00:00', 'direction' => 1],
        'reason' => 'Forgot to clock out.',
    ], $this->user);

    expect(fn () => $this->service->apply($correction, $this->user))
        ->toThrow(ValidationException::class);

    $this->service->reject($correction, $this->user, 'No supporting evidence.');

    expect(fn () => $this->service->approve($correction->refresh(), $this->user))
        ->toThrow(ValidationException::class);
});

it('keeps correction decisions immutable', function (): void {
    $correction = $this->service->request($this->employee, [
        'workday_date' => '2026-11-02',
        'correction_type' => AttendanceCorrection::TYPE_WRONG_TIME,
        'requested_values' => ['punched_at' => '2026-11-02 08:00:00', 'direction' => 0],
    ], $this->user);

    $this->service->approve($correction, $this->user);

    $decision = AttendanceCorrectionDecision::withoutTenant()->firstOrFail();

    expect(fn () => $decision->update(['action' => 'reject']))
        ->toThrow(LogicException::class);
});
