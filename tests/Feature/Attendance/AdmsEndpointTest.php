<?php

use App\Models\AttendanceDeviceEvent;
use App\Models\Company;
use App\Models\Device;
use App\Models\Employee;
use App\Models\Timesheet;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::factory()->create(['name' => 'Acme']);

    app(TenantContext::class)->set($this->company->id);

    $this->employee = Employee::factory()->create([
        'company_id' => $this->company->id,
        'employee_number' => 'E-0001',
        'biometric_id' => 101,
        'first_name' => 'Ana',
        'last_name' => 'Cruz',
    ]);

    $this->device = Device::factory()->adms('ADMS-0001')->create([
        'company_id' => $this->company->id,
        'name' => 'Main Entrance',
    ]);

    app(TenantContext::class)->forget();
});

it('answers the ADMS handshake with the option block', function () {
    $response = $this->get('/iclock/cdata?SN=ADMS-0001&options=all&pushver=2.2.14');

    $response->assertOk()
        ->assertSee('GET OPTION FROM: ADMS-0001', false)
        ->assertSee('Stamp=9999', false)
        ->assertSee('ServerTZ=8', false);
});

it('accepts an attendance batch and stores the punches', function () {
    $body = "101\t2026-11-12 08:01:15\t0\t1\t0\n"
        ."101\t2026-11-12 17:02:45\t1\t1\t0\n";

    $response = $this->call('POST', '/iclock/cdata?SN=ADMS-0001&table=ATTLOG&Stamp=1', [], [], [], ['CONTENT_TYPE' => 'text/plain'], $body);

    $response->assertOk()->assertSee('OK: 2', false);

    app(TenantContext::class)->set($this->company->id);

    $timesheet = Timesheet::query()->where('employee_id', $this->employee->id)->firstOrFail();

    expect($timesheet->logs)->toHaveCount(2)
        ->and($timesheet->logs[0]['time'])->toBe('08:01:15')
        ->and($timesheet->logs[0]['log_type'])->toBe(0)
        ->and($timesheet->logs[1]['log_type'])->toBe(1)
        ->and($timesheet->logs[0]['source'])->toBe('device')
        ->and($timesheet->logs[0]['device'])->toBe('Main Entrance');

    $event = AttendanceDeviceEvent::query()->latest('id')->firstOrFail();

    expect($event->kind)->toBe(AttendanceDeviceEvent::KIND_PUSH)
        ->and($event->accepted)->toBe(2)
        ->and($event->records)->toBe(2);

    expect($this->device->fresh()->last_seen_at)->not->toBeNull();
});

it('suppresses a resent batch', function () {
    $body = "101\t2026-11-12 08:00:00\t0\n101\t2026-11-12 17:00:00\t1\n";
    $url = '/iclock/cdata?SN=ADMS-0001&table=ATTLOG&Stamp=1';

    $this->call('POST', $url, [], [], [], ['CONTENT_TYPE' => 'text/plain'], $body)->assertOk();
    $this->call('POST', $url, [], [], [], ['CONTENT_TYPE' => 'text/plain'], $body)->assertOk()->assertSee('OK: 2', false);

    app(TenantContext::class)->set($this->company->id);

    expect(Timesheet::query()->firstOrFail()->logs)->toHaveCount(2);
});

it('rejects an unknown serial number', function () {
    $this->get('/iclock/cdata?SN=NOT-REGISTERED&options=all')->assertUnauthorized();
});

it('rejects a disabled device', function () {
    app(TenantContext::class)->set($this->company->id);
    $this->device->forceFill(['is_active' => false])->save();
    app(TenantContext::class)->forget();

    $this->get('/iclock/cdata?SN=ADMS-0001&options=all')->assertForbidden();
});

it('requires the push key when the device has one', function () {
    app(TenantContext::class)->set($this->company->id);
    $this->device->forceFill(['push_key' => hash('sha256', 'push-secret')])->save();
    app(TenantContext::class)->forget();

    $this->get('/iclock/cdata?SN=ADMS-0001&options=all')->assertUnauthorized();
    $this->get('/iclock/cdata?SN=ADMS-0001&options=all&key=wrong')->assertUnauthorized();
    $this->get('/iclock/cdata?SN=ADMS-0001&options=all&key=push-secret')->assertOk();
    $this->get('/iclock/cdata?SN=ADMS-0001&options=all', ['X-Push-Key' => 'push-secret'])->assertOk();
});

it('acknowledges non-punch tables without storing them', function () {
    $response = $this->call(
        'POST',
        '/iclock/cdata?SN=ADMS-0001&table=OPERLOG&Stamp=1',
        [],
        [],
        [],
        ['CONTENT_TYPE' => 'text/plain'],
        "101\t2026-11-12 08:00:00\t0\n",
    );

    $response->assertOk()->assertSee('OK: 1', false);

    app(TenantContext::class)->set($this->company->id);

    expect(Timesheet::query()->count())->toBe(0);
});

it('answers keepalives and command results', function () {
    $this->get('/iclock/getrequest?SN=ADMS-0001')->assertOk()->assertSee('OK', false);
    $this->post('/iclock/devicecmd?SN=ADMS-0001', ['CMD' => 'OK'])->assertOk()->assertSee('OK', false);
});

it('routes a device batch to its own tenant', function () {
    $other = Company::factory()->create(['name' => 'Other']);

    app(TenantContext::class)->set($other->id);

    $otherEmployee = Employee::factory()->create([
        'company_id' => $other->id,
        'employee_number' => 'E-9001',
        'biometric_id' => 202,
    ]);

    Device::factory()->adms('ADMS-0002')->create([
        'company_id' => $other->id,
        'name' => 'Other Gate',
    ]);

    app(TenantContext::class)->forget();

    $this->call(
        'POST',
        '/iclock/cdata?SN=ADMS-0002&table=ATTLOG&Stamp=1',
        [],
        [],
        [],
        ['CONTENT_TYPE' => 'text/plain'],
        "202\t2026-11-12 08:00:00\t0\n",
    )->assertOk();

    app(TenantContext::class)->set($other->id);
    expect(Timesheet::query()->where('employee_id', $otherEmployee->id)->exists())->toBeTrue();

    app(TenantContext::class)->set($this->company->id);
    expect(Timesheet::query()->where('employee_id', $this->employee->id)->exists())->toBeFalse();
});
