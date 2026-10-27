<?php

namespace Database\Factories;

use App\Enums\PaperIssueKind;
use App\Models\PaperIssue;
use App\Models\PaperType;
use App\Models\Party;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaperIssue>
 */
class PaperIssueFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'issued_on' => today(),
            'kind' => PaperIssueKind::InHouse,
            'party_id' => Party::factory(),
            'paper_type_id' => PaperType::factory(),
            'quantity' => 100,
            'issued_to' => 'Machine P-01',
            'wastage_percent' => 3,
        ];
    }
}
