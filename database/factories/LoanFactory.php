<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\Loan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Loan>
 */
class LoanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'issued_on' => today(),
            'amount' => 20000,
            'monthly_instalment' => 2500,
            'first_instalment_month' => today()->startOfMonth(),
        ];
    }
}
