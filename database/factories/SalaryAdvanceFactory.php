<?php

namespace Database\Factories;

use App\Enums\AdvanceKind;
use App\Models\Employee;
use App\Models\SalaryAdvance;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SalaryAdvance>
 */
class SalaryAdvanceFactory extends Factory
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
            'given_on' => today(),
            'amount' => 5000,
            'kind' => AdvanceKind::AdHoc,
        ];
    }
}
