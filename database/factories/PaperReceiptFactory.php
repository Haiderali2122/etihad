<?php

namespace Database\Factories;

use App\Models\Godown;
use App\Models\PaperReceipt;
use App\Models\PaperType;
use App\Models\Party;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaperReceipt>
 */
class PaperReceiptFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'slip_no' => (string) fake()->unique()->numberBetween(5000, 99999),
            'received_at' => now(),
            'party_id' => Party::factory(),
            'paper_type_id' => PaperType::factory(),
            'quantity' => 776,
            'truck_no' => 'LRL-'.fake()->numberBetween(1000, 9999),
            'godown_id' => Godown::factory(),
            'received_by' => 'Faisal',
        ];
    }
}
