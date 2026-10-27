<?php

namespace Database\Factories;

use App\Models\Book;
use App\Models\BookFormat;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BookFormat>
 */
class BookFormatFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'book_id' => Book::factory(),
            'name' => 'Standard',
            'size' => '20 × 30',
            'forms' => 10,
            'folding_rate' => 35,
            'binding_rate' => 2.5,
        ];
    }
}
