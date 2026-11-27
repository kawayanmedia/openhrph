<?php

use App\Models\AttendanceDeviceEvent;
use App\Models\Company;
use App\Models\Device;
use App\Models\Employee;
use App\Models\Timesheet;
use App\Services\Attendance\Devices\EloquentDeviceRepository;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use KawayanMedia\Attendance\Import\DeviceLogImporter;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::factory()->create(['name' => 'Acme']);

    app(TenantContext::class)->set($this->company->id);

    $this->employee = Employee::factory()->create([
        'company_id' => $this->company->id,
        'employee_number' => 'E-0001',
        'biometric_id' => 101,
    ]);

    $this->device = Device::factory()->csvExport()->create([
        'company_id' => $this->company->id,
        'name' => 'Legacy Terminal',
    ]);
});

function importCsv(Device $device, string $csv, string $filename = 'export.csv'): array
{
    $descriptor = app(EloquentDeviceRepository::class)->descriptorFor($device);

    return app(DeviceLogImporter::class)->import($descriptor, $csv, $filename);
}

it('imports a vendor export through the shared ingestor', function () {
    $csv = "Employee ID,Date,Time,Type\n"
        ."101,2026-11-12,08:00:00,IN\n"
        ."101,2026-11-12,17:00:00,OUT\n";

    $summary = importCsv($this->device, $csv);

    expect($summary['parser'])->toBe('generic-csv')
        ->and($summary['records'])->toBe(2)
        ->and($summary['accepted'])->toBe(2);

    $timesheet = Timesheet::query()->where('employee_id', $this->employee->id)->firstOrFail();

    expect($timesheet->logs)->toHaveCount(2)
        ->and($timesheet->logs[0]['source'])->toBe('device_import')
        ->and($timesheet->logs[0]['device'])->toBe('Legacy Terminal');

    expect(AttendanceDeviceEvent::query()->where('kind', AttendanceDeviceEvent::KIND_IMPORT)->exists())->toBeTrue();
});

it('treats a re-import as duplicates', function () {
    $csv = "Employee ID,Date,Time,Type\n101,2026-11-12,08:00:00,IN\n";

    importCsv($this->device, $csv);
    $summary = importCsv($this->device, $csv);

    expect($summary['accepted'])->toBe(0)
        ->and($summary['duplicates'])->toBe(1)
        ->and(Timesheet::query()->firstOrFail()->logs)->toHaveCount(1);
});

it('matches an alphanumeric device pin', function () {
    $employee = Employee::factory()->create([
        'company_id' => $this->company->id,
        'employee_number' => 'E-0002',
        'biometric_id' => 'EMP-A1',
    ]);

    $summary = importCsv($this->device, "EMP-A1,2026-11-12 08:00:00,0\n");

    expect($summary['accepted'])->toBe(1)
        ->and(Timesheet::query()->where('employee_id', $employee->id)->exists())->toBeTrue();
});

it('reports a zero-padded pin for the same employee', function () {
    $summary = importCsv($this->device, "0101,2026-11-12 08:00:00,0\n");

    expect($summary['accepted'])->toBe(1)
        ->and(Timesheet::query()->where('employee_id', $this->employee->id)->exists())->toBeTrue();
});
