<?php

namespace Database\Factories;

use App\Enums\DeviceProtocol;
use App\Models\Device;
use Database\Factories\Concerns\ResolvesTenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Device>
 */
class DeviceFactory extends Factory
{
    use ResolvesTenant;

    protected $model = Device::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => $this->tenantId(),
            'name' => $this->faker->words(2, true).' Entrance',
            'protocol' => DeviceProtocol::GenericJson,
            'api_key' => hash('sha256', Str::random(40)),
            'is_active' => true,
            'sync_enabled' => false,
        ];
    }

    public function adms(string $serial = 'ADMS-0001'): static
    {
        return $this->state(fn (): array => [
            'protocol' => DeviceProtocol::ZktecoAdms,
            'serial_number' => $serial,
            'port' => null,
        ]);
    }

    public function zktecoUdp(string $ip = '192.168.1.201'): static
    {
        return $this->state(fn (): array => [
            'protocol' => DeviceProtocol::ZktecoUdp,
            'ip_address' => $ip,
            'port' => 4370,
            'sync_enabled' => true,
        ]);
    }

    public function hikvision(string $ip = '192.168.1.202'): static
    {
        return $this->state(fn (): array => [
            'protocol' => DeviceProtocol::HikvisionIsapi,
            'ip_address' => $ip,
            'port' => 80,
            'sync_enabled' => true,
            'credentials' => ['username' => 'admin', 'password' => 'secret'],
        ]);
    }

    public function dahua(string $ip = '192.168.1.203'): static
    {
        return $this->state(fn (): array => [
            'protocol' => DeviceProtocol::DahuaCgi,
            'ip_address' => $ip,
            'port' => 80,
            'sync_enabled' => true,
            'credentials' => ['username' => 'admin', 'password' => 'secret'],
        ]);
    }

    public function suprema(string $ip = '192.168.1.204'): static
    {
        return $this->state(fn (): array => [
            'protocol' => DeviceProtocol::SupremaBiostar,
            'ip_address' => $ip,
            'port' => 443,
            'sync_enabled' => true,
            'credentials' => ['login_id' => 'admin', 'password' => 'secret'],
        ]);
    }

    public function csvExport(): static
    {
        return $this->state(fn (): array => [
            'protocol' => DeviceProtocol::CsvExport,
            'is_active' => true,
            'sync_enabled' => false,
        ]);
    }
}
