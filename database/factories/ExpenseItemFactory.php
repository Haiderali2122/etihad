<?php

namespace Database\Factories;

use App\Models\Expense;
use App\Models\ExpenseItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExpenseItem>
 */
class ExpenseItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'expense_id' => Expense::factory(),
            'description' => fake()->randomElement(['Tea for night shift', 'Diesel, 20 litres', 'Machine oil', 'Envelopes']),
            'amount' => fake()->numberBetween(100, 5000),
        ];
    }
}
