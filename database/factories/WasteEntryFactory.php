<?php

namespace Database\Factories;

use App\Enums\WasteDisposal;
use App\Enums\WasteStage;
use App\Models\WasteEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WasteEntry>
 */
class WasteEntryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'recorded_on' => today(),
            'stage' => WasteStage::Printing,
            'quantity' => 40,
            'unit' => 'kg',
            'disposal' => WasteDisposal::Kept,
        ];
    }
}
