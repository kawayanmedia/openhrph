<?php

use App\Exceptions\Tenancy\TenantNotResolvedException;
use App\Jurisdictions\Philippines\Government\Legacy\EloquentPdsStore;
use App\Jurisdictions\Philippines\Government\Legacy\LegacyLeaveStore;
use App\Jurisdictions\Philippines\Government\Legacy\TimesheetDtrStore;
use App\Models\Company;
use App\Models\Employee;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use KawayanMedia\DtrPdf\Service\DtrRecorder;
use KawayanMedia\LeaveForms\Model\LeaveApplicationRequest;
use KawayanMedia\LeaveForms\Service\LeaveApplicationService;
use KawayanMedia\LeaveForms\Service\LeaveCardService;
use KawayanMedia\Pds\Model\PdsProfile;
use KawayanMedia\Pds\Service\PdsEditor;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->company = Company::factory()->create();
    app(TenantContext::class)->set($this->company);
    $this->employee = Employee::factory()->create(['company_id' => $this->company->id]);
});

/**
 * @param  array<string, mixed>  $conditions
 */
function legacyRow(string $table, array $conditions): ?object
{
    $query = DB::table($table);

    foreach ($conditions as $column => $value) {
        $query->where($column, $value);
    }

    return $query->first();
}

it('writes manual attendance logs into timesheets and the audit trail', function (): void {
    $recorder = new DtrRecorder(new TimesheetDtrStore);

    expect($recorder->log((string) $this->employee->id, '2026-01-05', 1, '08:05', null, true, 'hr-user'))
        ->toBeTrue();

    $timesheet = legacyRow('timesheets', ['employee_id' => $this->employee->id, 'log_date' => '2026-01-05']);

    expect($timesheet)->not->toBeNull()
        ->and($timesheet->am_login)->toBe('08:05')
        ->and((int) $timesheet->company_id)->toBe($this->company->id)
        ->and($timesheet->employee_number)->toBe($this->employee->employee_number);

    $audit = legacyRow('hr_audit_events', ['company_id' => $this->company->id]);

    expect($audit)->not->toBeNull()
        ->and($audit->event)->toBe('MANUAL LOG')
        ->and((int) $audit->employee_id)->toBe($this->employee->id);

    expect(json_decode((string) $audit->payload, true))
        ->toMatchArray(['username' => 'hr-user', 'employee_id_affected' => (string) $this->employee->id]);
});

it('creates and removes the blank attendance dates a report needs', function (): void {
    $recorder = new DtrRecorder(new TimesheetDtrStore);

    $created = $recorder->ensureDates((string) $this->employee->id, '2026-01-01', '2026-01-03');

    expect($created)->toBe(['2026-01-01', '2026-01-02', '2026-01-03'])
        ->and(DB::table('timesheets')->where('employee_id', $this->employee->id)->count())->toBe(3);

    expect($recorder->ensureDates((string) $this->employee->id, '2026-01-01', '2026-01-03'))->toBe([]);
    expect($recorder->removeDates((string) $this->employee->id, $created))->toBe(3)
        ->and(DB::table('timesheets')->where('employee_id', $this->employee->id)->count())->toBe(0);
});

it('maps tardiness onto late minutes without lowering the payroll value', function (): void {
    $recorder = new DtrRecorder(new TimesheetDtrStore);
    $employeeId = (string) $this->employee->id;

    $recorder->recordTardiness($employeeId, '2026-01-05', 1, 300);

    expect((int) legacyRow('timesheets', ['employee_id' => $this->employee->id])->late_minutes)->toBe(5);

    $recorder->recordTardiness($employeeId, '2026-01-05', 3, 60);
    expect((int) legacyRow('timesheets', ['employee_id' => $this->employee->id])->late_minutes)->toBe(5);

    $recorder->recordTardiness($employeeId, '2026-01-05', 1, 0);
    expect((int) legacyRow('timesheets', ['employee_id' => $this->employee->id])->late_minutes)->toBe(5);
});

it('files and approves leave, writing the card, manual log and approver', function (): void {
    $service = new LeaveApplicationService(new LegacyLeaveStore);

    $id = $service->file(new LeaveApplicationRequest(
        employeeId: (string) $this->employee->id,
        leaveTypeId: 1,
        fromDate: '2026-02-02',
        toDate: '2026-02-04',
        officeId: (string) $this->employee->office_id,
    ));

    $service->approve($id, 'approver');

    $application = legacyRow('leave_applications', ['id' => $id]);

    expect((int) $application->approved)->toBe(1)
        ->and($application->approved_by)->toBe('approver')
        ->and((float) $application->days)->toBe(3.0)
        ->and((int) $application->company_id)->toBe($this->company->id);

    $card = legacyRow('leave_card', ['employee_id' => $this->employee->id]);

    expect($card)->not->toBeNull()
        ->and((float) $card->v_abs)->toBe(3.0);

    expect(legacyRow('manual_log', ['employee_id' => $this->employee->id]))->not->toBeNull();
});

it('earns and spends leave card credits', function (): void {
    $card = new LeaveCardService(new LegacyLeaveStore);
    $employeeId = (string) $this->employee->id;

    expect($card->earn($employeeId, '2026-01-31', 1.25))->toBeTrue();
    $card->spend($employeeId, 'vacation', 3, 'February 2-4, 2026', '2026-02-04', 1);

    expect((float) legacyRow('leave_card', ['employee_id' => $this->employee->id, 'v_earned' => 1.25])->v_earned)
        ->toBe(1.25)
        ->and((float) legacyRow('leave_card', ['employee_id' => $this->employee->id, 'v_abs' => 3])->v_abs)
        ->toBe(3.0);
});

it('writes every PDS section on the CS Form 212 tables', function (): void {
    $editor = new PdsEditor(new EloquentPdsStore);
    $employeeId = (string) $this->employee->id;

    $editor->savePersonal($employeeId, [
        'lname' => 'Santos',
        'fname' => 'Maria',
        'birth_date' => '1994-03-18',
        'sex' => 'F',
        'res_address' => '12 Mabini Street',
    ]);

    expect(legacyRow('pds_personal_info', ['employee_id' => $this->employee->id])->lname)->toBe('Santos');

    $employee = $this->employee->fresh();

    expect((string) $employee->birth_date)->toBe('1994-03-18')
        ->and($employee->sex)->toBe('F')
        ->and($employee->res_address)->toBe('12 Mabini Street');

    $editor->saveEducation($employeeId, [
        1 => ['school_name' => 'Elementary School'],
        4 => ['school_name' => 'State University'],
    ]);

    $editor->saveEducation($employeeId, [4 => ['school_name' => 'State University Two']]);

    expect(DB::table('pds_education_background')->where('employee_id', $this->employee->id)->count())->toBe(2)
        ->and(DB::table('pds_education_background')->where('employee_id', $this->employee->id)->where('level', 4)->value('school_name'))
        ->toBe('State University Two');
});

it('writes the PDS position details onto the existing employee columns', function (): void {
    $editor = new PdsEditor(new EloquentPdsStore);

    $editor->savePositionDetails((string) $this->employee->id, new PdsProfile(
        position: 'Administrative Officer I',
        permanent: 2,
        firstDayOfService: '2020-01-06',
    ));

    $employee = $this->employee->fresh();

    expect($employee->position)->toBe('Administrative Officer I')
        ->and((int) $employee->employment_type_id)->toBe(2)
        ->and($employee->date_hired?->format('Y-m-d'))->toBe('2020-01-06');
});

it('refuses legacy store access without a resolved tenant', function (): void {
    app(TenantContext::class)->forget();

    (new TimesheetDtrStore)->rows('dtr');
})->throws(TenantNotResolvedException::class);
