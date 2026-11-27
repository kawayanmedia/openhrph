<?php

use App\Models\Company;
use App\Models\Device;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::factory()->create([
        'name' => 'Acme',
        'api_key' => hash('sha256', 'company-key'),
    ]);

    app(TenantContext::class)->set($this->company->id);

    $this->device = Device::factory()->zktecoUdp('192.168.10.20')->create([
        'company_id' => $this->company->id,
        'name' => 'Lobby',
        'credentials' => ['username' => 'admin', 'password' => 'secret'],
    ]);

    app(TenantContext::class)->forget();
});

it('lists the pullable devices for the company key', function () {
    $response = $this->getJson('/api/attendance/devices', ['X-Company-Key' => 'company-key']);

    $response->assertOk()
        ->assertJsonCount(1, 'data.devices')
        ->assertJsonPath('data.devices.0.id', $this->device->id)
        ->assertJsonPath('data.devices.0.protocol', 'zkteco_udp')
        ->assertJsonPath('data.devices.0.host', '192.168.10.20')
        ->assertJsonPath('data.devices.0.credentials.username', 'admin')
        ->assertJsonPath('data.devices.0.company_id', $this->company->id);
});

it('excludes push-only and disabled devices', function () {
    app(TenantContext::class)->set($this->company->id);

    Device::factory()->adms('ADMS-9000')->create(['company_id' => $this->company->id]);
    Device::factory()->zktecoUdp()->create(['company_id' => $this->company->id, 'is_active' => false]);

    app(TenantContext::class)->forget();

    $this->getJson('/api/attendance/devices', ['X-Company-Key' => 'company-key'])
        ->assertOk()
        ->assertJsonCount(1, 'data.devices');
});

it('does not leak another company device', function () {
    $other = Company::factory()->create(['name' => 'Other']);

    app(TenantContext::class)->set($other->id);
    Device::factory()->zktecoUdp('10.0.0.9')->create(['company_id' => $other->id]);
    app(TenantContext::class)->forget();

    $this->getJson('/api/attendance/devices', ['X-Company-Key' => 'company-key'])
        ->assertOk()
        ->assertJsonCount(1, 'data.devices')
        ->assertJsonPath('data.devices.0.id', $this->device->id);
});

it('requires an api key', function () {
    $this->getJson('/api/attendance/devices')->assertUnauthorized();
});
