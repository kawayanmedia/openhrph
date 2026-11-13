<?php

namespace Database\Factories;

use App\Models\MinimumWageRate;
use Database\Factories\Concerns\ResolvesTenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MinimumWageRate>
 */
class MinimumWageRateFactory extends Factory
{
    use ResolvesTenant;

    protected $model = MinimumWageRate::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => $this->tenantId(),
            'region' => 'NCR',
            'industry' => 'Non-agriculture',
            'wage_start' => 61000,
        ];
    }
}
