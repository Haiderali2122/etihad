<?php

namespace Database\Factories;

use App\Models\PlatePurchase;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlatePurchase>
 */
class PlatePurchaseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'purchased_on' => today(),
            'supplier' => fake()->company(),
            'plate_size' => '20x30',
            'plates' => 100,
            'rate' => 150,
            'amount' => 15000,
        ];
    }
}
