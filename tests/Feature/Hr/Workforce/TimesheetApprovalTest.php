<?php

use App\Models\Company;
use App\Models\Timesheet;
use App\Models\TimesheetApproval;
use App\Models\User;
use App\Services\Attendance\TimesheetApprovalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->company = Company::factory()->create(['name' => 'Approval Co']);
    $this->user = User::factory()->forCompany($this->company)->create();
    $this->actingAs($this->user);

    $this->timesheet = Timesheet::factory()->create(['company_id' => $this->company->id]);
    $this->service = app(TimesheetApprovalService::class);
});

it('submits, approves and locks a timesheet for payroll', function (): void {
    $submitted = $this->service->submit($this->timesheet, $this->user);

    expect($submitted->approval_status)->toBe('submitted')
        ->and($submitted->submitted_at)->not->toBeNull();

    $approved = $this->service->approve($submitted, $this->user, 'Hours verified.');

    expect($approved->approval_status)->toBe('approved')
        ->and((int) $approved->approved_by)->toBe($this->user->id);

    $locked = $this->service->lock($approved->refresh(), $this->user);

    expect((bool) $locked->is_locked)->toBeTrue()
        ->and($locked->locked_at)->not->toBeNull();

    expect(TimesheetApproval::withoutTenant()->where('timesheet_id', $this->timesheet->id)->orderBy('id')->pluck('action')->all())
        ->toBe(['submit', 'approve', 'lock']);
});

it('refuses to approve or lock a timesheet out of sequence', function (): void {
    expect(fn () => $this->service->approve($this->timesheet, $this->user))
        ->toThrow(ValidationException::class);

    $this->service->submit($this->timesheet, $this->user);

    expect(fn () => $this->service->lock($this->timesheet->refresh(), $this->user))
        ->toThrow(ValidationException::class);
});

it('supports rejection and blocks resubmission of a locked timesheet', function (): void {
    $this->service->submit($this->timesheet, $this->user);

    $rejected = $this->service->reject($this->timesheet->refresh(), $this->user, 'Missing lunch-out punch.');

    expect($rejected->approval_status)->toBe('rejected');

    $this->service->submit($rejected, $this->user);
    $this->service->approve($this->timesheet->refresh(), $this->user);
    $locked = $this->service->lock($this->timesheet->refresh(), $this->user);

    expect(fn () => $this->service->submit($locked, $this->user))
        ->toThrow(ValidationException::class);
});

it('keeps the approval history immutable', function (): void {
    $this->service->submit($this->timesheet, $this->user);

    $approval = TimesheetApproval::withoutTenant()->firstOrFail();

    expect(fn () => $approval->update(['action' => 'approve']))
        ->toThrow(LogicException::class);
});
