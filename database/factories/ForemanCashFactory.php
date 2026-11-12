<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\ForemanCash;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ForemanCash>
 */
class ForemanCashFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'foreman_id' => Employee::factory()->state(['is_foreman' => true]),
            'month' => today()->startOfMonth(),
            'payday' => ForemanCash::Advance,
            'given_on' => today(),
            'amount' => 50000,
        ];
    }
}
