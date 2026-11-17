<?php

namespace Database\Factories;

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
            'api_key' => hash('sha256', Str::random(40)),
            'is_active' => true,
        ];
    }
}
