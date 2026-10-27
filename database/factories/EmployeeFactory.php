<?php

namespace Database\Factories;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'E-'.fake()->unique()->numberBetween(100, 99999),
            'name' => fake()->name(),
            'designation' => fake()->randomElement(['Printer', 'Binder', 'Folder', 'Helper', 'Cutter']),
            'monthly_salary' => 31000,
            'joined_on' => '2024-01-01',
            'is_active' => true,
        ];
    }

    public function foreman(): static
    {
        return $this->state(fn () => ['is_foreman' => true, 'designation' => 'Foreman']);
    }
}
