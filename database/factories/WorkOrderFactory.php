<?php

namespace Database\Factories;

use App\Enums\PrintMode;
use App\Enums\WorkOrderStage;
use App\Models\BookFormat;
use App\Models\Machine;
use App\Models\PaperType;
use App\Models\Party;
use App\Models\WorkOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkOrder>
 */
class WorkOrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'number' => 'WO-'.fake()->unique()->numberBetween(2000, 99999),
            'ordered_on' => today(),
            'book_format_id' => BookFormat::factory(),
            'copies' => 10000,
            'forms' => 10,
            'wastage_percent' => 3,
            'rims_needed' => 206,
            'paper_type_id' => PaperType::factory(),
            'party_id' => Party::factory(),
            'mode' => PrintMode::InHouse,
            'machine_id' => Machine::factory(),
            'stage' => WorkOrderStage::Waiting,
        ];
    }
}
