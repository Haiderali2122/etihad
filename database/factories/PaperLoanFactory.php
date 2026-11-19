<?php

namespace Database\Factories;

use App\Models\PaperLoan;
use App\Models\PaperType;
use App\Models\Party;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaperLoan>
 */
class PaperLoanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'borrowed_on' => today(),
            'paper_type_id' => PaperType::factory(),
            'borrower_party_id' => Party::factory(),
            'lender_party_id' => Party::factory(),
            'rims' => 5,
        ];
    }
}
