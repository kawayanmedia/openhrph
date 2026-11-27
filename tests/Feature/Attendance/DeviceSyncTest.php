<?php

use App\Models\Company;
use App\Models\Device;
use App\Models\Employee;
use App\Models\Timesheet;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use KawayanMedia\Attendance\Contracts\Devices\Driver;
use KawayanMedia\Attendance\Devices\DeviceDescriptor;
use KawayanMedia\Attendance\Devices\DeviceProbe;
use KawayanMedia\Attendance\Devices\DriverManager;
use KawayanMedia\Attendance\Devices\PullResult;
use KawayanMedia\Attendance\Devices\PullWindow;
use KawayanMedia\Attendance\Devices\PunchRecord;
use KawayanMedia\Attendance\Exceptions\DeviceConnectionException;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::factory()->create(['name' => 'Acme']);

    app(TenantContext::class)->set($this->company->id);

    $this->employee = Employee::factory()->create([
        'company_id' => $this->company->id,
        'employee_number' => 'E-0001',
        'biometric_id' => 101,
    ]);
});

/**
 * @param  array<int, PunchRecord>  $records
 */
function registerFakeDriver(array $records, ?Throwable $failure = null): Driver
{
    $driver = new class($records, $failure) implements Driver
    {
        public int $calls = 0;

        /**
         * @param  array<int, PunchRecord>  $records
         */
        public function __construct(private array $records, private ?Throwable $failure) {}

        public function protocol(): string
        {
            return 'zkteco_udp';
        }

        public function canPull(): bool
        {
            return true;
        }

        public function pull(DeviceDescriptor $device, PullWindow $window): PullResult
        {
            $this->calls++;

            if ($this->failure !== null) {
                throw $this->failure;
            }

            return PullResult::fromRecords($this->records);
        }

        public function probe(DeviceDescriptor $device): DeviceProbe
        {
            return DeviceProbe::success('ok');
        }
    };

    config(['attendance-devices.drivers' => ['zkteco_udp' => $driver]]);
    app()->forgetInstance(DriverManager::class);

    return $driver;
}

it('pulls a due device and stores the punches', function () {
    $device = Device::factory()->zktecoUdp()->create([
        'company_id' => $this->company->id,
        'name' => 'Front Desk',
        'last_synced_at' => null,
    ]);

    registerFakeDriver([
        new PunchRecord('101', CarbonImmutable::parse('2026-11-12 08:00:00', 'Asia/Manila'), 0),
        new PunchRecord('101', CarbonImmutable::parse('2026-11-12 17:00:00', 'Asia/Manila'), 1),
    ]);

    $this->artisan('attendance:devices:sync')->assertSuccessful();

    $timesheet = Timesheet::query()->where('employee_id', $this->employee->id)->firstOrFail();

    expect($timesheet->logs)->toHaveCount(2)
        ->and($timesheet->logs[0]['device'])->toBe('Front Desk');

    $device->refresh();

    expect($device->last_sync_status)->toBe(Device::SYNC_OK)
        ->and($device->last_pull_cursor)->not->toBeNull();
});

it('skips a device that is not due yet', function () {
    $device = Device::factory()->zktecoUdp()->create([
        'company_id' => $this->company->id,
        'last_synced_at' => now(),
        'sync_interval_minutes' => 30,
    ]);

    $driver = registerFakeDriver([]);

    $this->artisan('attendance:devices:sync')->assertSuccessful();

    expect($driver->calls)->toBe(0)
        ->and($device->fresh()->last_sync_status)->toBeNull();
});

it('records a failure without stopping the run', function () {
    $device = Device::factory()->zktecoUdp()->create([
        'company_id' => $this->company->id,
        'last_synced_at' => null,
    ]);

    registerFakeDriver([], DeviceConnectionException::unreachable('192.168.1.50', 4370));

    $this->artisan('attendance:devices:sync')->assertFailed();

    $device->refresh();

    expect($device->last_sync_status)->toBe(Device::SYNC_FAILED)
        ->and($device->last_sync_message)->toContain('Could not connect');
});

it('syncs only the requested device', function () {
    $first = Device::factory()->zktecoUdp()->create([
        'company_id' => $this->company->id,
        'last_synced_at' => null,
    ]);

    $second = Device::factory()->zktecoUdp()->create([
        'company_id' => $this->company->id,
        'last_synced_at' => null,
    ]);

    $driver = registerFakeDriver([
        new PunchRecord('101', CarbonImmutable::parse('2026-11-12 08:00:00', 'Asia/Manila'), 0),
    ]);

    $this->artisan('attendance:devices:sync', ['--device' => $first->id])->assertSuccessful();

    expect($driver->calls)->toBe(1)
        ->and($first->fresh()->last_synced_at)->not->toBeNull()
        ->and($second->fresh()->last_synced_at)->toBeNull();
});

it('does not touch the timesheets on a dry run', function () {
    Device::factory()->zktecoUdp()->create([
        'company_id' => $this->company->id,
        'last_synced_at' => null,
    ]);

    registerFakeDriver([
        new PunchRecord('101', CarbonImmutable::parse('2026-11-12 08:00:00', 'Asia/Manila'), 0),
    ]);

    $this->artisan('attendance:devices:sync', ['--dry-run' => true])->assertSuccessful();

    expect(Timesheet::query()->count())->toBe(0);
});
