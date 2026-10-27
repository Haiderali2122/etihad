<?php

namespace Database\Factories;

use App\Enums\ContractorPaymentKind;
use App\Models\Contractor;
use App\Models\ContractorPayment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContractorPayment>
 */
class ContractorPaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'contractor_id' => Contractor::factory(),
            'paid_on' => today(),
            'amount' => 5000,
            'kind' => ContractorPaymentKind::Advance,
        ];
    }
}
