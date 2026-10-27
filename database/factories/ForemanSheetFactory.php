<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\ForemanSheet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ForemanSheet>
 */
class ForemanSheetFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'foreman_id' => Employee::factory()->foreman(),
            'given_on' => today(),
            'amount' => 20000,
        ];
    }
}
