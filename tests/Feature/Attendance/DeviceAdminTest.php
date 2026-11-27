<?php

use App\Enums\DeviceProtocol;
use App\Livewire\Admin\Actions\RotateDeviceKey;
use App\Livewire\Admin\AdminForm;
use App\Livewire\Admin\AdminIndex;
use App\Livewire\Admin\Resources\AttendanceDeviceEvent as AttendanceDeviceEventResource;
use App\Models\AttendanceDeviceEvent;
use App\Models\Company;
use App\Models\Device;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::factory()->create(['name' => 'Acme']);

    app(TenantContext::class)->set($this->company->id);

    $this->user = User::factory()->forCompany($this->company)->create([
        'owner' => true,
        'name' => 'Admin User',
        'email' => 'admin@example.com',
        'password' => 'secret',
    ]);

    $this->actingAs($this->user);
});

it('creates a pull device from the admin form', function () {
    Livewire::test(AdminForm::class, ['resource' => 'device'])
        ->set('form.name', 'Lobby')
        ->set('form.protocol', DeviceProtocol::ZktecoUdp->value)
        ->set('form.ip_address', '192.168.1.50')
        ->set('form.port', 4370)
        ->set('form.sync_enabled', true)
        ->set('form.sync_interval_minutes', 10)
        ->call('save')
        ->assertHasNoErrors();

    $device = Device::query()->where('name', 'Lobby')->firstOrFail();

    expect($device->protocol)->toBe(DeviceProtocol::ZktecoUdp)
        ->and($device->ip_address)->toBe('192.168.1.50')
        ->and($device->sync_enabled)->toBeTrue()
        ->and($device->sync_interval_minutes)->toBe(10);
});

it('stores driver config and credentials as json', function () {
    Livewire::test(AdminForm::class, ['resource' => 'device'])
        ->set('form.name', 'Gate')
        ->set('form.protocol', DeviceProtocol::HikvisionIsapi->value)
        ->set('form.ip_address', '192.168.1.60')
        ->set('form.credentials', '{"username":"admin","password":"secret"}')
        ->set('form.config', '{"direction_map":{"1":0}}')
        ->call('save')
        ->assertHasNoErrors();

    $device = Device::query()->where('name', 'Gate')->firstOrFail();

    expect($device->credentials)->toBe(['username' => 'admin', 'password' => 'secret'])
        ->and($device->config)->toBe(['direction_map' => ['1' => 0]]);
});

it('lists devices in the admin', function () {
    Device::factory()->zktecoUdp()->create([
        'company_id' => $this->company->id,
        'name' => 'Main Lobby',
    ]);

    Livewire::test(AdminIndex::class, ['resource' => 'device'])
        ->assertSee('Main Lobby');
});

it('rotates the device api key and stores only its hash', function () {
    $device = Device::factory()->create([
        'company_id' => $this->company->id,
        'name' => 'Front Desk',
        'api_key' => null,
    ]);

    $message = (new RotateDeviceKey($device))->handle(Request::create('/'), [], collect([$device]));

    $plain = substr($message, strrpos($message, ':') + 1);

    expect($device->fresh()->api_key)->toBe(hash('sha256', trim($plain)))
        ->and($message)->toContain('Front Desk');
});

it('lists the device protocol events', function () {
    $device = Device::factory()->adms('ADMS-1')->create([
        'company_id' => $this->company->id,
        'name' => 'Main Gate',
    ]);

    AttendanceDeviceEvent::factory()->create([
        'company_id' => $this->company->id,
        'device_id' => $device->id,
        'serial_number' => 'ADMS-1',
        'kind' => AttendanceDeviceEvent::KIND_PUSH,
        'message' => 'Batch accepted',
    ]);

    Livewire::test(AdminIndex::class, ['resource' => 'attendance-device-event'])
        ->assertSee('Batch accepted')
        ->assertSee('Main Gate');
});

it('keeps the device event screen read-only', function () {
    expect(AttendanceDeviceEventResource::canCreateResource())->toBeFalse()
        ->and(AttendanceDeviceEventResource::authorizedToUpdate())->toBeFalse()
        ->and(AttendanceDeviceEventResource::authorizedToDelete())->toBeFalse();
});
