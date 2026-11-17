<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\OfficialBusinessRequest;
use Database\Factories\Concerns\ResolvesTenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OfficialBusinessRequest>
 */
class OfficialBusinessRequestFactory extends Factory
{
    use ResolvesTenant;

    protected $model = OfficialBusinessRequest::class;

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
            'date_filed' => now()->toDateString(),
            'date_from' => now()->toDateString(),
            'time_from' => '09:00:00',
            'status' => 'Pending',
        ];
    }
}
