<?php

namespace Database\Factories;

use App\Models\Godown;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Godown>
 */
class GodownFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Godown '.fake()->unique()->bothify('?#'),
            'is_active' => true,
        ];
    }
}
