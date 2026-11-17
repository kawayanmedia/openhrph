<?php

namespace Database\Factories;

use App\Models\Position;
use Database\Factories\Concerns\ResolvesTenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Position>
 */
class PositionFactory extends Factory
{
    use ResolvesTenant;

    protected $model = Position::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => $this->tenantId(),
            'code' => Str::upper(Str::random(6)),
            'name' => $this->faker->jobTitle(),
            'is_vacant' => false,
        ];
    }
}
