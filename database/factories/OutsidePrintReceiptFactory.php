<?php

namespace Database\Factories;

use App\Models\OutsidePrintReceipt;
use App\Models\WorkOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OutsidePrintReceipt>
 */
class OutsidePrintReceiptFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'work_order_id' => WorkOrder::factory(),
            'received_on' => today(),
            'printed_copies' => 1000,
        ];
    }
}
