<?php

namespace Database\Factories;

use App\Enums\ContractorWork;
use App\Models\Contractor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contractor>
 */
class ContractorFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'work' => ContractorWork::Both,
            'folding_rate' => 35,
            'form_rate' => 0.6,
            'copy_rate' => 2.5,
            'is_active' => true,
        ];
    }
}
