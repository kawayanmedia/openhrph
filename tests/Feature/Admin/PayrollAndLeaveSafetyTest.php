<?php

use App\Jobs\ApproveLeaveJob;
use App\Livewire\Admin\Actions\ApproveLeave;
use App\Livewire\Admin\Actions\ProcessPayroll;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\Payroll;
use App\Models\PayrollDate;
use App\Models\PayrollGroup;
use App\Models\Setting;
use App\Models\Timesheet;
use App\Models\User;
use App\Services\Payroll\PayrollReadiness;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create([
        'company_id' => 1,
        'owner' => true,
        'name' => 'HR Admin',
        'email' => 'hr@example.com',
        'password' => 'secret',
    ]);

    $this->actingAs($this->user);
    app(TenantContext::class)->set(1);
});

function payrollSafetyGroup(array $attributes = []): PayrollGroup
{
    return PayrollGroup::factory()->create(array_merge([
        'company_id' => 1,
        'name' => 'Semi-monthly',
        'frequency' => PayrollGroup::FREQUENCY_SEMI_MONTHLY,
        'days_year' => PayrollGroup::NUMBER_WORKING_DAYS_MONFRI,
        'hours_day' => 8,
        'is_default' => true,
    ], $attributes));
}

function payrollSafetyEmployee(PayrollGroup $group, array $attributes = []): Employee
{
    return Employee::factory()->create(array_merge([
        'company_id' => 1,
        'payroll_group_id' => $group->id,
        'employee_number' => 'E-100',
        'first_name' => 'Ana',
        'last_name' => 'Reyes',
        'basic_salary' => 3000000,
        'payment_type' => Employee::PAYMENT_TYPE_MONTHLY,
        'timesheet' => true,
        'office_id' => null,
    ], $attributes));
}

function payrollSafetyLeaveType(string $code = 'VL', string $name = 'Vacation Leave'): int
{
    return DB::table('leave_types')->insertGetId([
        'code' => $code,
        'name' => $name,
        'deductable' => true,
        'deduct_to' => 0,
        'allow_per_year' => 15,
        'is_active' => true,
        'company_id' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

it('rejects a duplicate payroll run for the same group, date and type', function () {
    $group = payrollSafetyGroup();

    Payroll::factory()->create([
        'company_id' => 1,
        'payroll_group_id' => $group->id,
        'payroll_date' => '2026-11-15',
        'type' => Payroll::TYPE_REGULAR,
    ]);

    expect(fn () => Payroll::factory()->create([
        'company_id' => 1,
        'payroll_group_id' => $group->id,
        'payroll_date' => '2026-11-15',
        'type' => Payroll::TYPE_REGULAR,
    ]))->toThrow(ValidationException::class);
});

it('blocks bulk processing when employees have no attendance', function () {
    $group = payrollSafetyGroup();
    $employee = payrollSafetyEmployee($group);

    Setting::factory()->create([
        'company_id' => 1,
        'key' => 'auto_absent_no_timesheet',
        'value' => '0',
    ]);

    $payroll = Payroll::factory()->create([
        'company_id' => 1,
        'payroll_group_id' => $group->id,
        'payroll_date' => '2026-11-15',
        'date_from' => '2026-11-01',
        'date_to' => '2026-11-15',
        'status' => Payroll::STATUS_OPEN,
    ]);

    $message = (new ProcessPayroll)->handle(request(), [], new Collection([$payroll]));

    expect($payroll->refresh()->status)->toBe(Payroll::STATUS_OPEN)
        ->and($message)->toContain('without attendance');
});

it('does not treat auto-absent as completed attendance in the readiness checklist', function () {
    $group = payrollSafetyGroup();
    payrollSafetyEmployee($group);

    PayrollDate::factory()->create([
        'company_id' => 1,
        'payroll_group_id' => $group->id,
        'payroll_date' => now()->endOfMonth()->toDateString(),
        'attendance_date_start' => now()->startOfMonth()->toDateString(),
        'attendance_date_end' => now()->endOfMonth()->toDateString(),
        'status' => PayrollDate::STATUS_OPEN,
    ]);

    Setting::factory()->create([
        'company_id' => 1,
        'key' => 'auto_absent_no_timesheet',
        'value' => '1',
    ]);

    $summary = app(PayrollReadiness::class)->summary();
    $attendance = collect($summary['sections'])->firstWhere('key', 'attendance');
    $item = collect($attendance['items'])->firstWhere('key', 'attendance');

    expect($item['status'])->toBe('pending')
        ->and($item['meta'])->not->toContain('paid on basic salary');
});

it('marks only scheduled working days as paid leave and records the approver', function () {
    $group = payrollSafetyGroup();
    $employee = payrollSafetyEmployee($group, ['total_vacation_leave' => 10]);
    $typeId = payrollSafetyLeaveType();

    $request = LeaveRequest::factory()->create([
        'company_id' => 1,
        'employee_id' => $employee->id,
        'leave_type_id' => $typeId,
        'date_from' => '2026-11-06', // Friday
        'date_to' => '2026-11-09',   // Monday
        'days_with_pay' => 2,
        'days_without_pay' => 0,
        'is_whole_day' => true,
    ]);

    ApproveLeaveJob::dispatchSync($request, $employee, $this->user->id);
    app(TenantContext::class)->set(1);

    $leaveDays = Timesheet::query()
        ->where('employee_id', $employee->id)
        ->orderBy('log_date')
        ->get();

    expect($leaveDays)->toHaveCount(2)
        ->and($leaveDays->pluck('log_date')->map->toDateString()->all())
        ->toBe(['2026-11-06', '2026-11-09']);

    foreach ($leaveDays as $day) {
        expect((bool) $day->is_paid_leave)->toBeTrue()
            ->and($day->day_rate_code)->toBe('pl');
    }

    expect((int) $request->refresh()->approved_user_id)->toBe($this->user->id)
        ->and((float) $employee->refresh()->total_vacation_leave)->toBe(8.0);
});

it('records a half-day leave as a half day', function () {
    $group = payrollSafetyGroup();
    $employee = payrollSafetyEmployee($group, ['total_sick_leave' => 5]);
    $typeId = payrollSafetyLeaveType('SL', 'Sick Leave');

    $request = LeaveRequest::factory()->create([
        'company_id' => 1,
        'employee_id' => $employee->id,
        'leave_type_id' => $typeId,
        'date_from' => '2026-11-09',
        'date_to' => '2026-11-09',
        'days_with_pay' => 0.5,
        'days_without_pay' => 0,
        'is_whole_day' => false,
    ]);

    ApproveLeaveJob::dispatchSync($request, $employee, $this->user->id);
    app(TenantContext::class)->set(1);

    $day = Timesheet::query()
        ->where('employee_id', $employee->id)
        ->whereDate('log_date', '2026-11-09')
        ->first();

    expect($day)->not->toBeNull()
        ->and((bool) $day->leave_half_day)->toBeTrue()
        ->and((bool) $day->is_paid_leave)->toBeTrue()
        ->and((float) $employee->refresh()->total_sick_leave)->toBe(4.5);
});

it('refuses to approve leave that overlaps a locked payroll period', function () {
    $group = payrollSafetyGroup();
    $employee = payrollSafetyEmployee($group);
    $typeId = payrollSafetyLeaveType();

    $locked = Timesheet::factory()->create([
        'company_id' => 1,
        'employee_id' => $employee->id,
        'log_date' => '2026-11-09',
    ]);
    $locked->forceFill(['is_locked' => true])->save();

    $request = LeaveRequest::factory()->create([
        'company_id' => 1,
        'employee_id' => $employee->id,
        'leave_type_id' => $typeId,
        'date_from' => '2026-11-09',
        'date_to' => '2026-11-09',
        'days_with_pay' => 1,
    ]);

    $message = (new ApproveLeave)->handle(request(), [], new Collection([$request]));

    expect($message)->toContain('locked')
        ->and($request->refresh()->status)->toBe(LeaveRequest::PENDING);
});
