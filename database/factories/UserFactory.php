<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $firstName = $this->faker->firstName();
        $lastName = $this->faker->lastName();

        return [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'name' => Str::limit("{$firstName} {$lastName}", 32, ''),
            'email' => $this->faker->unique()->safeEmail(),
            'password' => 'password',
            'owner' => false,
            'is_temporary_password' => false,
            'remember_token' => Str::random(10),
        ];
    }

    public function owner(): static
    {
        return $this->state(fn (): array => ['owner' => true]);
    }

    public function forCompany(Company|int $company): static
    {
        $companyId = $company instanceof Company ? $company->id : $company;

        return $this->state(fn (): array => ['company_id' => $companyId]);
    }
}
