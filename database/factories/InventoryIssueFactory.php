<?php

namespace Database\Factories;

use App\Models\InventoryIssue;
use App\Models\InventoryItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryIssue>
 */
class InventoryIssueFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'issued_on' => today(),
            'inventory_item_id' => InventoryItem::factory(),
            'quantity' => 5,
            'issued_to' => 'Machine P-01',
        ];
    }
}
