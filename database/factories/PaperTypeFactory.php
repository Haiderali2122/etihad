<?php

namespace Database\Factories;

use App\Models\PaperType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaperType>
 */
class PaperTypeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('??-####')),
            'name' => fake()->randomElement(['AA printing', 'Offset', 'Newsprint', 'Art paper']),
            'gsm' => fake()->randomElement([45, 68, 80, 128]),
            'size' => '20 × 30',
            'unit' => 'rims',
            'sheets_per_unit' => 500,
            'reorder_level' => 100,
            'is_active' => true,
        ];
    }
}
