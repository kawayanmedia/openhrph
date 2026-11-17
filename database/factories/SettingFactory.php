<?php

namespace Database\Factories;

use App\Models\Setting;
use Database\Factories\Concerns\ResolvesTenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Setting>
 */
class SettingFactory extends Factory
{
    use ResolvesTenant;

    protected $model = Setting::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => $this->tenantId(),
            'key' => $this->faker->unique()->word(),
            'value' => 'set',
        ];
    }
}
