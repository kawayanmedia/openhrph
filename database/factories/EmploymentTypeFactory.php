<?php

namespace Database\Factories;

use App\Models\EmploymentType;
use Database\Factories\Concerns\ResolvesTenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<EmploymentType>
 */
class EmploymentTypeFactory extends Factory
{
    use ResolvesTenant;

    protected $model = EmploymentType::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => $this->tenantId(),
            'code' => Str::upper(Str::random(6)),
            'name' => EmploymentType::TYPE_REGULAR_PERMANENT,
            'is_payroll' => true,
        ];
    }
}
