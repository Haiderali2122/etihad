<?php

namespace Database\Factories;

use App\Models\CashFloat;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CashFloat>
 */
class CashFloatFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'given_on' => today(),
            'user_id' => User::factory(),
            'amount' => 100000,
        ];
    }
}
