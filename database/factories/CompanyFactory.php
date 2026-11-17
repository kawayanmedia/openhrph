<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Company>
 */
class CompanyFactory extends Factory
{
    protected $model = Company::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = $this->faker->company();

        return [
            'user_id' => User::factory(),
            'name' => Str::limit($name, 64, ''),
            'code' => Str::upper(Str::random(8)),
            'trade_name' => Str::limit($name, 64, ''),
            'type' => 'Private',
            'email' => $this->faker->unique()->safeEmail(),
            'is_subscription' => false,
            'setup_done' => false,
        ];
    }
}
