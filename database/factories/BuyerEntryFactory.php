<?php

namespace Database\Factories;

use App\Enums\BuyerEntryKind;
use App\Enums\PartyKind;
use App\Models\BuyerEntry;
use App\Models\Party;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BuyerEntry>
 */
class BuyerEntryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'party_id' => Party::factory()->kind(PartyKind::Buyer),
            'entry_on' => today(),
            'kind' => BuyerEntryKind::Advance,
            'amount' => 50000,
        ];
    }
}
