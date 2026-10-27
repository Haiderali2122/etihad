<?php

namespace Database\Factories;

use App\Enums\PartyKind;
use App\Models\Dispatch;
use App\Models\Party;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Dispatch>
 */
class DispatchFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'number' => 'DSP-'.fake()->unique()->numberBetween(1000, 99999),
            'dispatched_on' => today(),
            'buyer_id' => Party::factory()->kind(PartyKind::Buyer),
            'bundles' => 100,
            'copies_per_bundle' => 25,
            'copies' => 2500,
        ];
    }
}
