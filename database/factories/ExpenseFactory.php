<?php

namespace Database\Factories;

use App\Models\Expense;
use App\Models\ExpenseHead;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Expense>
 */
class ExpenseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'spent_on' => today(),
            'user_id' => User::factory(),
            'expense_head_id' => ExpenseHead::factory(),
            'paid_to' => fake()->company(),
            'amount' => 1000,
            'reason' => fake()->sentence(3),
        ];
    }
}
