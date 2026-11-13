<?php

namespace Database\Factories;

use App\Models\Group;
use Database\Factories\Concerns\ResolvesTenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Group>
 */
class GroupFactory extends Factory
{
    use ResolvesTenant;

    protected $model = Group::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => $this->tenantId(),
            'name' => Str::limit($this->faker->jobTitle(), 64, ''),
            'active' => true,
            'permissions' => [],
        ];
    }
}
