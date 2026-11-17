<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\EmployeeCertificate;
use Database\Factories\Concerns\ResolvesTenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeeCertificate>
 */
class EmployeeCertificateFactory extends Factory
{
    use ResolvesTenant;

    protected $model = EmployeeCertificate::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => $this->tenantId(),
            'employee_id' => fn (array $attributes) => Employee::factory()
                ->create(['company_id' => $attributes['company_id']])
                ->id,
            'certicate_number' => $this->faker->bothify('CERT-####'),
            'certicate_title' => $this->faker->words(3, true),
            'date' => now()->toDateString(),
            'is_active' => true,
        ];
    }
}
