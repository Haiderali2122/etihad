<?php

namespace Database\Factories;

use App\Models\Book;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Book>
 */
class BookFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['Urdu Reader', 'Mathematics', 'General Science', 'Islamiyat']).' Class '.fake()->numberBetween(1, 10),
            'is_active' => true,
        ];
    }
}
