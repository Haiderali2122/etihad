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
}
