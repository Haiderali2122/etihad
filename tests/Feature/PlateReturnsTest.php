<?php

namespace Tests\Feature;

use App\Enums\PartyKind;
use App\Models\Book;
use App\Models\Party;
use App\Models\PlateJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Plate jobs: the processor and the book are typed or picked (plates are made before the work order), the plate
 * size is kept, and plates come back in parts, each return saved with its date.
 */
class PlateReturnsTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_typed_processor_book_and_size_are_saved_and_offered_next_time(): void
    {
        $owner = User::factory()->owner()->create();
        Book::factory()->create(['name' => 'Urdu Qaida']);

        $this->actingAs($owner)->get(route('plate-jobs.create'))->assertOk()
            ->assertSee('name="processor_name"', false)->assertSee('For which book')->assertSee('<option value="Urdu Qaida">', false)->assertDontSee('Choose processor');
        $this->actingAs($owner)->post(route('plate-jobs.store'), [
            'processor_name' => '  Ali  Plates ', 'book_name' => 'urdu qaida', 'plate_size' => '20 × 30',
            'plates' => 12, 'rate' => 350, 'job_on' => today()->toDateString(),
        ])->assertSessionHasNoErrors();

        $job = PlateJob::query()->sole();
        $this->assertSame('Ali Plates', $job->processor->name);
        $this->assertSame(PartyKind::PlateProcessor, $job->processor->kind);
        $this->assertSame('Urdu Qaida', $job->book_name);
        $this->assertNull($job->work_order_id);
        $this->assertSame('20 × 30', $job->plate_size);

        // A book not in the list yet is kept as typed and offered next time, as are the processor and size.
        $this->actingAs($owner)->post(route('plate-jobs.store'), [
            'processor_name' => 'ali plates', 'book_name' => 'Islamiat Class 3', 'plates' => 4, 'rate' => 350, 'job_on' => today()->toDateString(),
        ])->assertSessionHasNoErrors();
        $this->assertSame(1, Party::query()->where('kind', PartyKind::PlateProcessor)->count());
        $this->actingAs($owner)->get(route('plate-jobs.create'))
            ->assertSee('<option value="Ali Plates">', false)->assertSee('<option value="20 × 30">', false)->assertSee('<option value="Islamiat Class 3">', false);
        $this->actingAs($owner)->get(route('plate-jobs.index', ['show' => 'all']))->assertSee('Islamiat Class 3');
    }

    public function test_plates_come_back_in_parts_each_with_its_date(): void
    {
        $owner = User::factory()->owner()->create();
        $job = PlateJob::factory()->create(['plates' => 12, 'job_on' => today()->subDays(10)]);

        $this->actingAs($owner)->patch(route('plate-jobs.return', $job), ['returned_plates' => 5, 'returned_on' => today()->subDays(4)->toDateString()])->assertSessionHasNoErrors();
        $this->actingAs($owner)->patch(route('plate-jobs.return', $job), ['returned_plates' => 4, 'returned_on' => today()->toDateString()])->assertSessionHasNoErrors();
        // No more can come back than are still with the processor.
        $this->actingAs($owner)->patch(route('plate-jobs.return', $job), ['returned_plates' => 4, 'returned_on' => today()->toDateString()])->assertSessionHasErrors('returned_plates');

        $job->refresh();
        $this->assertSame(9, $job->returned_plates);
        $this->assertSame(3, $job->platesHeld());
        $this->assertSame([5, 4], $job->returns->pluck('plates')->all());
        // The row shows the totals; its record page lists each return with its date.
        $this->actingAs($owner)->get(route('plate-jobs.index', ['show' => 'all']))->assertSeeInOrder(['12', '9', '3']);
        $this->actingAs($owner)->get(route('records.show', ['plate-job', $job->id]))->assertOk()->assertSeeInOrder([today()->subDays(4)->format('j M Y'), '5', today()->format('j M Y'), '4']);

        // Deleting a return puts those plates back with the processor.
        $this->actingAs($owner)->from(route('plate-jobs.edit', $job))->delete(route('records.destroy', ['plate-return', $job->returns->first()->id]))->assertSessionHasNoErrors();
        $this->assertSame(4, $job->fresh()->returned_plates);
    }
}
