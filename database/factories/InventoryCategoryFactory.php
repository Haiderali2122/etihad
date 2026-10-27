<?php

namespace Database\Factories;

use App\Models\InventoryCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryCategory>
 */
class InventoryCategoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->randomElement(['Ink', 'Plates', 'Glue', 'Chemicals', 'Packing', 'Thread', 'Wire']).' '.fake()->unique()->numberBetween(1, 9999),
            'tracks_stock' => true,
            'is_active' => true,
        ];
    }
}
