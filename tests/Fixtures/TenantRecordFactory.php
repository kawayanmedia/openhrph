<?php

namespace Tests\Fixtures;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TenantRecord>
 */
class TenantRecordFactory extends Factory
{
    protected $model = TenantRecord::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => null,
            'name' => $this->faker->word(),
        ];
    }
}
