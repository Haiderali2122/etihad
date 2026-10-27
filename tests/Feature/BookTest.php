<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\BookFormat;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookTest extends TestCase
{
    use RefreshDatabase;

    public function test_book_pages_render(): void
    {
        $user = User::factory()->create();
        $book = BookFormat::factory()->create()->book;

        $this->actingAs($user)->get(route('books.index'))->assertOk()->assertSee($book->name);
        $this->actingAs($user)->get(route('books.create'))->assertOk();
        $this->actingAs($user)->get(route('books.show', $book))->assertOk();
        $this->actingAs($user)->get(route('books.edit', $book))->assertOk();
    }

    public function test_create_a_book_with_two_formats(): void
    {
        $this->actingAs(User::factory()->create())->post(route('books.store'), [
            'name' => 'Urdu Reader Class 5',
            'is_active' => 1,
            'formats' => [
                ['name' => 'Standard', 'size' => '20 × 30', 'forms' => 12, 'folding_rate' => 35, 'binding_rate' => 2.5],
                ['name' => 'Small', 'forms' => 6.5],
            ],
        ])->assertSessionHasNoErrors();

        $book = Book::query()->sole();
        $this->assertSame(['Standard', 'Small'], $book->formats->pluck('name')->all());
        $this->assertSame('6.50', $book->formats[1]->forms);
        $this->assertNull($book->formats[1]->folding_rate);
    }

    public function test_a_book_needs_at_least_one_format_with_forms(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('books.store'), ['name' => 'No formats'])->assertSessionHasErrors('formats');
        $this->actingAs($user)->post(route('books.store'), ['name' => 'Bad', 'formats' => [['name' => 'Standard']]])
            ->assertSessionHasErrors('formats.0.forms');

        $this->assertDatabaseCount('books', 0);
    }

    public function test_update_edits_adds_and_removes_formats(): void
    {
        $book = Book::factory()->create();
        $keep = BookFormat::factory()->for($book)->create(['name' => 'Standard', 'forms' => 10]);
        $drop = BookFormat::factory()->for($book)->create(['name' => 'Old']);

        $this->actingAs(User::factory()->create())->put(route('books.update', $book), [
            'name' => $book->name,
            'is_active' => 1,
            'formats' => [
                ['id' => $keep->id, 'name' => 'Standard', 'forms' => 12],
                ['name' => 'Gift edition', 'forms' => 14],
            ],
        ])->assertRedirect(route('books.show', $book));

        $this->assertSame('12.00', $keep->refresh()->forms);
        $this->assertModelMissing($drop);
        $this->assertSame(['Standard', 'Gift edition'], $book->formats()->pluck('name')->all());
    }

    public function test_cannot_edit_another_books_format(): void
    {
        $book = Book::factory()->create();
        $other = BookFormat::factory()->create(['name' => 'Theirs', 'forms' => 8]);

        $this->actingAs(User::factory()->create())->put(route('books.update', $book), [
            'name' => $book->name,
            'formats' => [['id' => $other->id, 'name' => 'Hijacked', 'forms' => 1]],
        ]);

        $this->assertSame('Theirs', $other->refresh()->name);
    }

    public function test_delete_removes_book_and_formats(): void
    {
        $format = BookFormat::factory()->create();

        $this->actingAs(User::factory()->create())->delete(route('books.destroy', $format->book))->assertRedirect(route('books.index'));

        $this->assertModelMissing($format->book);
        $this->assertModelMissing($format);
    }

    public function test_folding_amount_follows_the_client_formula(): void
    {
        $format = BookFormat::factory()->make(['forms' => 10, 'folding_rate' => 35]);

        // (10 forms × 12,000 copies ÷ 1000) × Rs 35 = Rs 4,200
        $this->assertSame(4200, $format->foldingAmount(12000));
    }
}
