<?php

namespace Database\Factories;

use App\Enums\MachineStatus;
use App\Enums\MachineType;
use App\Models\Machine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Machine>
 */
class MachineFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('P-##')),
            'name' => 'Single-colour offset',
            'type' => MachineType::Printing,
            'status' => MachineStatus::Running,
        ];
    }
}
