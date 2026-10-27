<?php

namespace Database\Factories;

use App\Models\ExpenseHead;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExpenseHead>
 */
class ExpenseHeadFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word(),
            'color' => '#3E6FC1',
        ];
    }
}
