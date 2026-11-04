<?php

namespace Database\Factories;

use App\Models\RimLoading;
use App\Models\WorkOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RimLoading>
 */
class RimLoadingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'work_order_id' => WorkOrder::factory(),
            'loaded_on' => today(),
            'rims' => 100,
            'rate_per_rim' => 5,
            'amount' => 500,
            'loaded_by' => 'Younus',
        ];
    }
}
