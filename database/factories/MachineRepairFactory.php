<?php

namespace Database\Factories;

use App\Enums\PartyKind;
use App\Enums\RepairKind;
use App\Models\Machine;
use App\Models\MachineRepair;
use App\Models\Party;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MachineRepair>
 */
class MachineRepairFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'repaired_on' => today(),
            'machine_id' => Machine::factory(),
            'party_id' => Party::factory()->kind(PartyKind::Mechanic),
            'kind' => RepairKind::Repair,
            'description' => 'Roller re-rubbered',
            'bill_amount' => 10500,
            'discount' => 1000,
            'amount_paid' => 9500,
        ];
    }
}
