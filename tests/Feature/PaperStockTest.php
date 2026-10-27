<?php

namespace Tests\Feature;

use App\Enums\ApprovalStatus;
use App\Models\BookFormat;
use App\Models\Godown;
use App\Models\PaperIssue;
use App\Models\PaperReceipt;
use App\Models\PaperType;
use App\Models\Party;
use App\Models\User;
use App\Support\PaperStock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaperStockTest extends TestCase
{
    use RefreshDatabase;

    public function test_balance_is_receipts_minus_issues_per_party(): void
    {
        $type = PaperType::factory()->create();
        [$imran, $qadri] = Party::factory()->count(2)->create();

        PaperReceipt::factory()->for($type)->for($imran)->create(['quantity' => 776]);
        PaperReceipt::factory()->for($type)->for($imran)->create(['quantity' => 1552]);
        PaperReceipt::factory()->for($type)->for($qadri)->create(['quantity' => 500]);
        PaperIssue::factory()->for($type)->for($imran)->create(['quantity' => 300]);

        $stock = app(PaperStock::class);

        $this->assertSame(2028, $stock->available($type->id, $imran->id));
        $this->assertSame(500, $stock->available($type->id, $qadri->id));
    }

    public function test_rejected_receipts_do_not_count(): void
    {
        $type = PaperType::factory()->create();
        $party = Party::factory()->create();

        PaperReceipt::factory()->for($type)->for($party)->create(['quantity' => 776]);
        PaperReceipt::factory()->for($type)->for($party)->create(['quantity' => 999, 'status' => ApprovalStatus::Rejected]);

        $this->assertSame(776, app(PaperStock::class)->available($type->id, $party->id));
    }

    public function test_reorder_level_flags_low_and_critical(): void
    {
        $type = PaperType::factory()->create(['reorder_level' => 100]);
        $stock = app(PaperStock::class);

        $this->assertSame('ok', $stock->levelFor($type, 150));
        $this->assertSame('low', $stock->levelFor($type, 80));
        $this->assertSame('critical', $stock->levelFor($type, 40));
    }

    public function test_manager_receives_a_truck_and_it_waits_for_approval(): void
    {
        $manager = User::factory()->create();
        $type = PaperType::factory()->create();
        $party = Party::factory()->create();

        $response = $this->actingAs($manager)->post(route('paper-receipts.store'), [
            'slip_no' => '5531',
            'received_at' => now()->format('Y-m-d\TH:i'),
            'party_id' => $party->id,
            'paper_type_id' => $type->id,
            'quantity' => 1552,
            'truck_no' => 'LRL-1819',
            'godown_id' => Godown::factory()->create()->id,
            'received_by' => 'Faisal',
        ]);

        $receipt = PaperReceipt::query()->sole();
        $response->assertRedirect(route('paper-receipts.show', $receipt));
        $this->assertSame(ApprovalStatus::Pending, $receipt->status);
        $this->assertSame(1552, app(PaperStock::class)->available($type->id, $party->id));
    }

    public function test_cannot_issue_more_than_the_party_balance(): void
    {
        $manager = User::factory()->create();
        $type = PaperType::factory()->create();
        $party = Party::factory()->create();
        PaperReceipt::factory()->for($type)->for($party)->create(['quantity' => 100]);

        $payload = [
            'issued_on' => today()->toDateString(),
            'kind' => 'in_house',
            'party_id' => $party->id,
            'paper_type_id' => $type->id,
            'issued_to' => 'Machine P-01',
        ];

        $this->actingAs($manager)->post(route('paper-issues.store'), $payload + ['quantity' => 101])
            ->assertSessionHasErrors('quantity');

        $this->actingAs($manager)->post(route('paper-issues.store'), $payload + ['quantity' => 100])
            ->assertSessionHasNoErrors();

        $this->assertSame(0, app(PaperStock::class)->available($type->id, $party->id));
    }

    public function test_issuing_for_a_book_format_records_the_book_and_copies(): void
    {
        $manager = User::factory()->create();
        $type = PaperType::factory()->create();
        $party = Party::factory()->create();
        PaperReceipt::factory()->for($type)->for($party)->create(['quantity' => 1000]);
        $format = BookFormat::factory()->create(['forms' => 12, 'paper_type_id' => $type->id]);
        $format->book->update(['name' => 'Urdu Reader Class 5']);

        $this->actingAs($manager)->get(route('paper-issues.create'))
            ->assertOk()
            ->assertSee('data-forms="12"', false)
            ->assertSee('data-paper="'.$type->id.'"', false);

        $this->actingAs($manager)->post(route('paper-issues.store'), [
            'issued_on' => today()->toDateString(),
            'kind' => 'in_house',
            'party_id' => $party->id,
            'paper_type_id' => $type->id,
            'issued_to' => 'Machine P-01',
            'book_format_id' => $format->id,
            'copies' => 20000,
            'wastage_percent' => 3,
            'quantity' => 494,
        ])->assertSessionHasNoErrors();

        $issue = PaperIssue::query()->sole();
        $this->assertSame('Urdu Reader Class 5', $issue->book_name);
        $this->assertSame(20000, $issue->copies);
        $this->assertTrue($issue->bookFormat->is($format));
    }

    public function test_a_return_needs_only_the_quantity_and_drops_book_and_wastage(): void
    {
        $manager = User::factory()->create();
        $type = PaperType::factory()->create();
        $party = Party::factory()->create(['name' => 'Muhammad Sahib']);
        PaperReceipt::factory()->for($type)->for($party)->create(['quantity' => 500]);
        $format = BookFormat::factory()->create();

        $this->actingAs($manager)->post(route('paper-issues.store'), [
            'issued_on' => today()->toDateString(),
            'kind' => 'return',
            'party_id' => $party->id,
            'paper_type_id' => $type->id,
            'issued_to' => 'Muhammad Sahib',
            'quantity' => 40,
            // Stale values from before the user switched to "Return" must not be saved.
            'book_format_id' => $format->id,
            'copies' => 1000,
            'wastage_percent' => 3,
        ])->assertSessionHasNoErrors();

        $issue = PaperIssue::query()->sole();
        $this->assertSame(40, $issue->quantity);
        $this->assertNull($issue->book_format_id);
        $this->assertNull($issue->book_name);
        $this->assertNull($issue->copies);
        $this->assertSame('0.00', $issue->wastage_percent);
        $this->assertSame(460, app(PaperStock::class)->available($type->id, $party->id));
    }
}
