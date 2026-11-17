<?php

use App\Models\Company;
use App\Models\Device;
use App\Models\Employee;
use App\Models\Timesheet;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::factory()->create([
        'name' => 'Acme',
        'api_key' => hash('sha256', 'company-key'),
    ]);

    app(TenantContext::class)->set($this->company->id);

    $this->employee = Employee::factory()->create([
        'company_id' => $this->company->id,
        'employee_number' => 'E-0001',
        'biometric_id' => 101,
        'first_name' => 'Ana',
        'last_name' => 'Cruz',
    ]);

    $this->device = Device::factory()->create([
        'company_id' => $this->company->id,
        'name' => 'Main Entrance',
        'api_key' => hash('sha256', 'device-key'),
        'is_active' => true,
    ]);
});

it('rejects requests without an api key', function () {
    $this->postJson('/api/attendance/logs', [
        'logs' => [['biometric_id' => '101', 'at' => '2026-11-12 08:00:00']],
    ])->assertUnauthorized();
});

it('rejects an invalid device key', function () {
    $this->postJson('/api/attendance/logs', [
        'logs' => [['biometric_id' => '101', 'at' => '2026-11-12 08:00:00']],
    ], ['X-Device-Key' => 'nope'])->assertUnauthorized();
});

it('rejects a disabled device', function () {
    $this->device->forceFill(['is_active' => false])->save();

    $this->postJson('/api/attendance/logs', [
        'logs' => [['biometric_id' => '101', 'at' => '2026-11-12 08:00:00']],
    ], ['X-Device-Key' => 'device-key'])->assertForbidden();
});

it('accepts device logs and stores raw punches on the timesheet', function () {
    $response = $this->postJson('/api/attendance/logs', [
        'logs' => [
            ['biometric_id' => '101', 'at' => '2026-11-12 08:01:15', 'type' => 'in'],
            ['biometric_id' => '101', 'at' => '2026-11-12 17:02:45', 'type' => 'out'],
        ],
    ], ['X-Device-Key' => 'device-key']);

    $response->assertOk()
        ->assertJsonPath('data.accepted', 2)
        ->assertJsonPath('data.unmatched', 0)
        ->assertJsonPath('data.results.0.log_type', 0)
        ->assertJsonPath('data.results.1.log_type', 1);

    app(TenantContext::class)->set($this->company->id);

    $timesheet = Timesheet::query()->where('employee_id', $this->employee->id)->firstOrFail();

    expect($timesheet->log_date->toDateString())->toBe('2026-11-12');
    expect($timesheet->logs)->toHaveCount(2);
    expect($timesheet->logs[0]['time'])->toBe('08:01:15');
    expect($timesheet->logs[0]['log_type'])->toBe(0);
    expect($timesheet->logs[0]['source'])->toBe('device');
    expect($timesheet->logs[0]['device'])->toBe('Main Entrance');
    expect($timesheet->am_login)->toBe('08:01');
    expect($timesheet->pm_logout)->toBe('17:02');

    expect($this->device->fresh()->last_seen_at)->not->toBeNull();
});

it('accepts logs authenticated with a company api key', function () {
    $response = $this->postJson('/api/attendance/logs', [
        'logs' => [
            ['employee_number' => 'E-0001', 'date' => '2026-11-12', 'time' => '07:45:00'],
        ],
    ], ['X-Company-Key' => 'company-key']);

    $response->assertOk()->assertJsonPath('data.accepted', 1);

    app(TenantContext::class)->set($this->company->id);

    $timesheet = Timesheet::query()->where('employee_id', $this->employee->id)->firstOrFail();

    expect($timesheet->logs[0]['source'])->toBe('api');
    expect($timesheet->logs[0]['time'])->toBe('07:45:00');
});

it('matches an employee by biometric id before employee number', function () {
    $other = Employee::factory()->create([
        'company_id' => $this->company->id,
        'employee_number' => '202',
        'first_name' => 'Ben',
        'last_name' => 'Santos',
    ]);

    $this->postJson('/api/attendance/logs', [
        'logs' => [
            ['biometric_id' => '101', 'employee_number' => '202', 'at' => '2026-11-12 08:00:00'],
        ],
    ], ['X-Device-Key' => 'device-key'])->assertOk();

    app(TenantContext::class)->set($this->company->id);

    expect(Timesheet::query()->where('employee_id', $this->employee->id)->exists())->toBeTrue();
    expect(Timesheet::query()->where('employee_id', $other->id)->exists())->toBeFalse();
});

it('falls back to the employee number when the biometric id is unknown', function () {
    $this->postJson('/api/attendance/logs', [
        'logs' => [
            ['biometric_id' => '999', 'employee_number' => 'E-0001', 'at' => '2026-11-12 08:00:00'],
        ],
    ], ['X-Device-Key' => 'device-key'])
        ->assertOk()
        ->assertJsonPath('data.accepted', 1);
});

it('reports unmatched employees without failing the batch', function () {
    $this->postJson('/api/attendance/logs', [
        'logs' => [
            ['biometric_id' => '999', 'employee_number' => 'UNKNOWN', 'at' => '2026-11-12 08:00:00'],
            ['biometric_id' => '101', 'at' => '2026-11-12 08:00:00'],
        ],
    ], ['X-Device-Key' => 'device-key'])
        ->assertOk()
        ->assertJsonPath('data.unmatched', 1)
        ->assertJsonPath('data.accepted', 1)
        ->assertJsonPath('data.results.0.status', 'unmatched');
});

it('suppresses duplicate punches on resend', function () {
    $payload = [
        'logs' => [['biometric_id' => '101', 'at' => '2026-11-12 08:00:00', 'type' => 'in']],
    ];

    $this->postJson('/api/attendance/logs', $payload, ['X-Device-Key' => 'device-key'])
        ->assertOk()
        ->assertJsonPath('data.accepted', 1);

    $this->postJson('/api/attendance/logs', $payload, ['X-Device-Key' => 'device-key'])
        ->assertOk()
        ->assertJsonPath('data.accepted', 0)
        ->assertJsonPath('data.duplicates', 1);

    app(TenantContext::class)->set($this->company->id);

    expect(Timesheet::query()->firstOrFail()->logs)->toHaveCount(1);
});

it('sequences log types when the device omits them', function () {
    $response = $this->postJson('/api/attendance/logs', [
        'logs' => [
            ['biometric_id' => '101', 'at' => '2026-11-12 08:00:00'],
            ['biometric_id' => '101', 'at' => '2026-11-12 17:00:00'],
            ['biometric_id' => '101', 'at' => '2026-11-12 18:00:00'],
        ],
    ], ['X-Device-Key' => 'device-key']);

    $response->assertOk()
        ->assertJsonPath('data.results.0.log_type', 0)
        ->assertJsonPath('data.results.1.log_type', 1)
        ->assertJsonPath('data.results.2.log_type', 0);
});

it('validates that each log has an employee and a timestamp', function () {
    $this->postJson('/api/attendance/logs', [
        'logs' => [
            ['at' => '2026-11-12 08:00:00'],
            ['biometric_id' => '101'],
        ],
    ], ['X-Device-Key' => 'device-key'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['logs.0', 'logs.1']);
});

it('returns the stored raw logs for a day', function () {
    $this->postJson('/api/attendance/logs', [
        'logs' => [['biometric_id' => '101', 'at' => '2026-11-12 08:00:00', 'type' => 'in']],
    ], ['X-Device-Key' => 'device-key'])->assertOk();

    $response = $this->getJson('/api/attendance/logs?date=2026-11-12&biometric_id=101', [
        'X-Device-Key' => 'device-key',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.employee.id', $this->employee->id)
        ->assertJsonPath('data.logs.0.time', '08:00:00');
});
