<?php

namespace Database\Factories;

use App\Models\Payroll;
use App\Models\PayrollGroup;
use Database\Factories\Concerns\ResolvesTenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payroll>
 */
class PayrollFactory extends Factory
{
    use ResolvesTenant;

    protected $model = Payroll::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = now()->startOfMonth();

        return [
            'company_id' => $this->tenantId(),
            'payroll_group_id' => fn (array $attributes) => PayrollGroup::factory()
                ->create(['company_id' => $attributes['company_id']])
                ->id,
            'payroll_date' => now()->toDateString(),
            'date_from' => $start->toDateString(),
            'date_to' => now()->toDateString(),
            'status' => Payroll::STATUS_OPEN,
            'type' => Payroll::TYPE_REGULAR,
        ];
    }
}
