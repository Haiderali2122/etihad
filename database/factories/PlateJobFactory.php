<?php

namespace Database\Factories;

use App\Enums\PartyKind;
use App\Models\Party;
use App\Models\PlateJob;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlateJob>
 */
class PlateJobFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'party_id' => Party::factory()->kind(PartyKind::PlateProcessor),
            'job_on' => today(),
            'plates' => 12,
            'rate' => 125,
            'amount' => 1500,
        ];
    }
}
