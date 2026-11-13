<?php

namespace Database\Factories;

use App\Models\Office;
use Database\Factories\Concerns\ResolvesTenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Office>
 */
class OfficeFactory extends Factory
{
    use ResolvesTenant;

    protected $model = Office::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => $this->tenantId(),
            'code' => Str::upper(Str::random(6)),
            'name' => Str::limit($this->faker->company().' Office', 128, ''),
            'location' => 'internal',
            'disbursing_officer' => '',
        ];
    }
}
