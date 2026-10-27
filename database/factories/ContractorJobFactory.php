<?php

namespace Database\Factories;

use App\Enums\ContractorJobKind;
use App\Models\Contractor;
use App\Models\ContractorJob;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContractorJob>
 */
class ContractorJobFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'contractor_id' => Contractor::factory(),
            'kind' => ContractorJobKind::Folding,
            'job_on' => today(),
            'forms' => 10,
            'copies' => 12000,
            'folding_rate' => 35,
            'amount' => 4200,
            'state' => 'in_progress',
        ];
    }
}
