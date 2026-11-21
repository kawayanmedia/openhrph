<?php

use App\Jobs\ApproveLeaveJob;
use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveLedger;
use App\Models\LeavePolicy;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Services\Leave\LeaveLedgerService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create([
        'company_id' => 1,
        'owner' => true,
        'name' => 'HR Admin',
        'email' => 'ledger@example.com',
        'password' => 'secret',
    ]);

    $this->actingAs($this->user);
    app(TenantContext::class)->set(1);

    $this->employee = Employee::factory()->create([
        'company_id' => 1,
        'employee_number' => 'E-900',
        'first_name' => 'Liza',
        'last_name' => 'Cruz',
        'date_hired' => '2024-01-15',
        'office_id' => null,
    ]);

    $this->leaveTypeId = DB::table('leave_types')->insertGetId([
        'code' => 'VL',
        'name' => 'Vacation Leave',
        'deductable' => true,
        'deduct_to' => 0,
        'allow_per_year' => 15,
        'is_active' => true,
        'company_id' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    LeavePolicy::query()->create([
        'company_id' => 1,
        'leave_type_id' => $this->leaveTypeId,
        'entitlement_days' => 15,
        'accrual' => 'annual',
        'prorate_first_year' => true,
        'carry_over_days' => 5,
        'expiry_months' => 0,
        'half_day_allowed' => true,
        'is_paid' => true,
        'min_service_months' => 0,
        'requires_evidence' => false,
        'is_active' => true,
    ]);

    $this->ledger = app(LeaveLedgerService::class);
});

it('accrues, draws down and rebuilds a leave balance', function () {
    $this->ledger->accrueYear(1, 2026);

    expect($this->ledger->balance(1, (int) $this->employee->getKey(), (int) $this->leaveTypeId, 2026))->toBe(15.0);

    $this->ledger->post(
        1, (int) $this->employee->getKey(), (int) $this->leaveTypeId, 2026,
        LeaveLedger::MOVEMENT_AVAILMENT, -5, 'test:availment',
    );

    $balance = LeaveBalance::query()
        ->where('employee_id', $this->employee->getKey())
        ->where('leave_type_id', $this->leaveTypeId)
        ->where('year', 2026)
        ->first();

    expect((float) $balance->balance)->toBe(10.0)
        ->and((float) $balance->used)->toBe(5.0);

    // Posting the same reference twice must not double count.
    $this->ledger->post(
        1, (int) $this->employee->getKey(), (int) $this->leaveTypeId, 2026,
        LeaveLedger::MOVEMENT_AVAILMENT, -5, 'test:availment',
    );

    expect($this->ledger->balance(1, (int) $this->employee->getKey(), (int) $this->leaveTypeId, 2026))->toBe(10.0);
});

it('carries over the cap and expires the rest', function () {
    $this->ledger->post(
        1, (int) $this->employee->getKey(), (int) $this->leaveTypeId, 2026,
        LeaveLedger::MOVEMENT_ACCRUAL, 15, 'test:accrual',
    );

    $this->ledger->carryOver(1, 2026, 2027);

    expect($this->ledger->balance(1, (int) $this->employee->getKey(), (int) $this->leaveTypeId, 2027))->toBe(5.0)
        ->and($this->ledger->balance(1, (int) $this->employee->getKey(), (int) $this->leaveTypeId, 2026))->toBe(0.0);
});

it('monetizes only within the available balance', function () {
    $this->ledger->post(
        1, (int) $this->employee->getKey(), (int) $this->leaveTypeId, 2026,
        LeaveLedger::MOVEMENT_ACCRUAL, 10, 'test:accrual',
    );

    $this->ledger->monetize(1, (int) $this->employee->getKey(), (int) $this->leaveTypeId, 2026, 3);

    expect($this->ledger->balance(1, (int) $this->employee->getKey(), (int) $this->leaveTypeId, 2026))->toBe(7.0);

    expect(fn () => $this->ledger->monetize(1, (int) $this->employee->getKey(), (int) $this->leaveTypeId, 2026, 100))
        ->toThrow(InvalidArgumentException::class);
});

it('posts approved leave to the ledger without double posting', function () {
    $request = LeaveRequest::factory()->create([
        'company_id' => 1,
        'employee_id' => $this->employee->id,
        'leave_type_id' => $this->leaveTypeId,
        'date_from' => '2026-11-09',
        'date_to' => '2026-11-10',
        'days_with_pay' => 2,
        'days_without_pay' => 0,
        'is_whole_day' => true,
    ]);

    $this->ledger->post(
        1, (int) $this->employee->getKey(), (int) $this->leaveTypeId, 2026,
        LeaveLedger::MOVEMENT_ACCRUAL, 15, 'test:accrual',
    );

    ApproveLeaveJob::dispatchSync($request, $this->employee, $this->user->id);
    app(TenantContext::class)->set(1);

    expect($this->ledger->balance(1, (int) $this->employee->getKey(), (int) $this->leaveTypeId, 2026))->toBe(13.0);

    ApproveLeaveJob::dispatchSync($request->refresh(), $this->employee, $this->user->id);
    app(TenantContext::class)->set(1);

    expect($this->ledger->balance(1, (int) $this->employee->getKey(), (int) $this->leaveTypeId, 2026))->toBe(13.0);
});
