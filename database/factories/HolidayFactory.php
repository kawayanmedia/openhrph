<?php

namespace Database\Factories;

use App\Models\Holiday;
use Database\Factories\Concerns\ResolvesTenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Holiday>
 */
class HolidayFactory extends Factory
{
    use ResolvesTenant;

    protected $model = Holiday::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => $this->tenantId(),
            'year' => (int) now()->year,
            'name' => $this->faker->words(3, true),
            'holiday_type' => 'regular',
            'code' => 'regular',
            'date' => now()->toDateString(),
        ];
    }
}
