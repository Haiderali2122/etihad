<?php

namespace Database\Factories;

use App\Models\BillPayment;
use App\Models\MaterialBill;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BillPayment>
 */
class BillPaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'payable_type' => (new MaterialBill)->getMorphClass(),
            'payable_id' => MaterialBill::factory(),
            'paid_on' => today(),
            'amount' => 1000,
        ];
    }
}
