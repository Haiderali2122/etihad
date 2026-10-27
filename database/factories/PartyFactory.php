<?php

namespace Database\Factories;

use App\Enums\PartyKind;
use App\Models\Party;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Party>
 */
class PartyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'kind' => PartyKind::PaperParty,
            'phone' => fake()->phoneNumber(),
            'is_active' => true,
        ];
    }

    public function kind(PartyKind $kind): static
    {
        return $this->state(fn () => ['kind' => $kind]);
    }
}
