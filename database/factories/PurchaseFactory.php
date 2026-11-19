<?php

namespace Database\Factories;

use App\Enums\PartyKind;
use App\Models\InventoryItem;
use App\Models\Party;
use App\Models\Purchase;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Purchase>
 */
class PurchaseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'purchased_on' => today(),
            'party_id' => Party::factory()->kind(PartyKind::Supplier),
            'inventory_item_id' => InventoryItem::factory(),
            'quantity' => 25,
            'rate' => 740,
            'amount' => 18500,
        ];
    }

    /**
     * A purchase whose bill has been paid in full on its date.
     */
    public function paid(): static
    {
        return $this->afterCreating(fn (Purchase $purchase) => $purchase->pay((int) $purchase->amount, $purchase->purchased_on, $purchase->created_by));
    }
}
