<?php

namespace Database\Factories;

use App\Models\Government\PlantillaItem;
use App\Models\Office;
use Database\Factories\Concerns\ResolvesTenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PlantillaItem>
 */
class PlantillaItemFactory extends Factory
{
    use ResolvesTenant;

    protected $model = PlantillaItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => $this->tenantId(),
            'office_id' => fn (array $attributes) => Office::factory()
                ->create(['company_id' => $attributes['company_id']])
                ->id,
            'item_number' => Str::upper($this->faker->bothify('ITM-####')),
            'position_title' => Str::limit($this->faker->jobTitle(), 64, ''),
            'salary_grade' => $this->faker->numberBetween(1, 33),
            'step' => $this->faker->numberBetween(1, 8),
            'have_budget' => true,
        ];
    }
}
