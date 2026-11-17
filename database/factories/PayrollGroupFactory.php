<?php

namespace Database\Factories;

use App\Models\PayrollGroup;
use Database\Factories\Concerns\ResolvesTenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PayrollGroup>
 */
class PayrollGroupFactory extends Factory
{
    use ResolvesTenant;

    protected $model = PayrollGroup::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => $this->tenantId(),
            'name' => 'Semi-monthly',
            'frequency' => PayrollGroup::FREQUENCY_SEMI_MONTHLY,
            'days_year' => PayrollGroup::NUMBER_WORKING_DAYS_MONFRI,
            'hours_day' => 8,
            'is_default' => false,
        ];
    }
}
