<?php

namespace Database\Factories;

use App\Models\InventoryCategory;
use App\Models\MaterialBill;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MaterialBill>
 */
class MaterialBillFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'inventory_category_id' => fn () => InventoryCategory::query()->where('name', 'Diesel')->value('id')
                ?? InventoryCategory::factory()->create(['name' => 'Diesel', 'show_as_tab' => true])->id,
            'bill_on' => today(),
            'supplier' => fake()->company(),
            'total' => 10000,
        ];
    }
}
