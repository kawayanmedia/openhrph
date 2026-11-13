<?php

namespace Database\Factories;

use App\Services\Government\Models\Sss;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Sss>
 */
class SssFactory extends Factory
{
    protected $model = Sss::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'from' => '0',
            'to' => '4249.99',
            'regular_ec' => '10.00',
            'wisp' => '0',
            'regular_er' => '180.00',
            'regular_ee' => 90.0,
            'ecc' => 10,
            'wisp_er' => 0,
            'wisp_ee' => 0,
        ];
    }
}
