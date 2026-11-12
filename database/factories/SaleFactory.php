<?php

namespace Database\Factories;

use App\Models\InventoryCategory;
use App\Models\Sale;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Sale>
 */
class SaleFactory extends Factory
{
    /**
     * A raddi sale with one line.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'inventory_category_id' => fn () => InventoryCategory::query()->where('name', 'Raddi')->value('id') ?? InventoryCategory::factory()->create(['name' => 'Raddi', 'show_as_tab' => true])->id,
            'sold_on' => today(),
            'total' => 5000,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Sale $sale): void {
            if ($sale->items()->doesntExist()) {
                $sale->items()->create(['description' => 'Raddi', 'quantity' => 100, 'rate' => $sale->total / 100, 'amount' => $sale->total]);
            }
        });
    }
}
