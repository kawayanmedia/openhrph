<?php

namespace Database\Factories;

use App\Models\CompanyBank;
use Database\Factories\Concerns\ResolvesTenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CompanyBank>
 */
class CompanyBankFactory extends Factory
{
    use ResolvesTenant;

    protected $model = CompanyBank::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => $this->tenantId(),
            'name' => Str::limit($this->faker->company().' Bank', 64, ''),
            'code' => Str::upper(Str::random(8)),
            'account_number' => $this->faker->numerify('############'),
        ];
    }
}
