<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\Government\EmployeePlantilla;
use App\Models\Government\PlantillaItem;
use Database\Factories\Concerns\ResolvesTenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeePlantilla>
 */
class EmployeePlantillaFactory extends Factory
{
    use ResolvesTenant;

    protected $model = EmployeePlantilla::class;

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
            'plantilla_item_id' => fn (array $attributes) => PlantillaItem::factory()
                ->create(['company_id' => $attributes['company_id']])
                ->id,
            'year' => (int) now()->year,
            'active' => true,
        ];
    }
}
