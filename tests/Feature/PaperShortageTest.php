<?php

namespace Tests\Feature;

use App\Enums\ApprovalStatus;
use App\Enums\PaperIssueKind;
use App\Models\PaperIssue;
use App\Models\PaperLoan;
use App\Models\PaperReceipt;
use App\Models\PaperType;
use App\Models\Party;
use App\Models\User;
use App\Support\PaperStock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * An order that needs more rims than its party has: the rest comes from another stock of the same paper in the
 * same save, and the order party owes those rims until it pays them back in rims or in money.
 */
class PaperShortageTest extends TestCase
{
    use RefreshDatabase;

    private function issue(PaperType $type, Party $party, array $extra = []): array
    {
        return $extra + [
            'issued_on' => today()->toDateString(), 'kind' => PaperIssueKind::InHouse->value, 'party_id' => $party->id,
            'paper_type_id' => $type->id, 'quantity' => 765, 'issued_to' => 'Press men', 'wastage_percent' => 3, 'reference' => 'F1',
        ];
    }

    public function test_a_shortage_is_covered_from_another_stock_and_owed_until_settled(): void
    {
        $owner = User::factory()->owner()->create();
        $type = PaperType::factory()->create();
        $shahrukh = Party::factory()->create(['name' => 'Shahrukh AA Paper']);
        $press = Party::factory()->create(['name' => 'Etihad press stock']);
        $approved = ['status' => ApprovalStatus::Approved, 'paper_type_id' => $type->id];
        PaperReceipt::factory()->create($approved + ['party_id' => $shahrukh->id, 'quantity' => 760]);
        PaperReceipt::factory()->create($approved + ['party_id' => $press->id, 'quantity' => 100]);
        $stock = app(PaperStock::class);

        // Without picking where the rest comes from, it is refused as before.
        $this->actingAs($owner)->post(route('paper-issues.store'), $this->issue($type, $shahrukh))->assertSessionHasErrors('quantity');
        $this->actingAs($owner)->get(route('paper-issues.create'))->assertOk()->assertSee('Take the rest from');

        $this->actingAs($owner)->post(route('paper-issues.store'), $this->issue($type, $shahrukh, ['shortage_stock' => $type->id.'-'.$press->id]))
            ->assertSessionHasNoErrors()->assertSessionHas('status', fn ($s) => str_contains($s, '5 rims were short'));

        $this->assertSame([760, 5], PaperIssue::query()->orderBy('id')->pluck('quantity')->all());
        $this->assertSame([$shahrukh->id, $press->id], PaperIssue::query()->orderBy('id')->pluck('party_id')->all());
        $this->assertSame(0, $stock->available($type->id, $shahrukh->id));
        $this->assertSame(95, $stock->available($type->id, $press->id));
        $loan = PaperLoan::query()->sole();
        $this->assertSame(5, $loan->rims);
        $this->assertTrue($loan->isOpen());
        $this->actingAs($owner)->get(route('paper.index', ['tab' => 'owed']))->assertOk()->assertSee('Shahrukh AA Paper')->assertSee('Etihad press stock')->assertSee('Owed');

        // Rims back needs the order party to have them; once their paper arrives, 5 move back to the press.
        $this->actingAs($owner)->patch(route('paper-loans.settle', $loan), ['settled_how' => 'rims', 'settled_on' => today()->toDateString()])->assertSessionHasErrors('settled_how');
        PaperReceipt::factory()->create($approved + ['party_id' => $shahrukh->id, 'quantity' => 50]);
        $this->actingAs($owner)->patch(route('paper-loans.settle', $loan), ['settled_how' => 'rims', 'settled_on' => today()->toDateString()])->assertSessionHasNoErrors();
        $this->assertSame(45, $stock->available($type->id, $shahrukh->id));
        $this->assertSame(100, $stock->available($type->id, $press->id));
        $this->assertFalse($loan->refresh()->isOpen());
    }

    public function test_a_shortage_can_be_settled_in_money_and_the_cover_must_have_enough(): void
    {
        $owner = User::factory()->owner()->create();
        $type = PaperType::factory()->create();
        $a = Party::factory()->create();
        $b = Party::factory()->create();
        $approved = ['status' => ApprovalStatus::Approved, 'paper_type_id' => $type->id];
        PaperReceipt::factory()->create($approved + ['party_id' => $a->id, 'quantity' => 760]);
        PaperReceipt::factory()->create($approved + ['party_id' => $b->id, 'quantity' => 3]);

        $this->actingAs($owner)->post(route('paper-issues.store'), $this->issue($type, $a, ['shortage_stock' => $type->id.'-'.$b->id]))->assertSessionHasErrors('shortage_stock');
        PaperReceipt::factory()->create($approved + ['party_id' => $b->id, 'quantity' => 10]);
        $this->actingAs($owner)->post(route('paper-issues.store'), $this->issue($type, $a, ['shortage_stock' => $type->id.'-'.$b->id]))->assertSessionHasNoErrors();

        $loan = PaperLoan::query()->sole();
        $this->actingAs($owner)->patch(route('paper-loans.settle', $loan), ['settled_how' => 'money', 'settled_on' => today()->toDateString()])->assertSessionHasErrors('amount');
        $this->actingAs($owner)->patch(route('paper-loans.settle', $loan), ['settled_how' => 'money', 'settled_on' => today()->toDateString(), 'amount' => 25000])->assertSessionHasNoErrors();
        $this->assertSame(25000, $loan->refresh()->amount);
        // Money does not move stock.
        $this->assertSame(8, app(PaperStock::class)->available($type->id, $b->id));
        $this->actingAs($owner)->get(route('paper.index', ['tab' => 'owed']))->assertSee('Paid Rs 25,000');
    }

    public function test_a_shortage_can_come_from_a_different_paper(): void
    {
        $owner = User::factory()->owner()->create();
        $gsm63 = PaperType::factory()->create();
        $gsm65 = PaperType::factory()->create();
        $musa = Party::factory()->create(['name' => 'Musa Traders']);
        $shahrukh = Party::factory()->create(['name' => 'Shahrukh AA Paper']);
        PaperReceipt::factory()->create(['status' => ApprovalStatus::Approved, 'paper_type_id' => $gsm63->id, 'party_id' => $musa->id, 'quantity' => 838]);
        PaperReceipt::factory()->create(['status' => ApprovalStatus::Approved, 'paper_type_id' => $gsm65->id, 'party_id' => $shahrukh->id, 'quantity' => 6548]);

        // The form offers every other stock, other papers included.
        $this->actingAs($owner)->get(route('paper-issues.create'))->assertOk()->assertSee('Other paper')->assertSee('Shahrukh AA Paper');
        $this->actingAs($owner)->post(route('paper-issues.store'), $this->issue($gsm63, $musa, ['quantity' => 900, 'shortage_stock' => $gsm65->id.'-'.$shahrukh->id]))
            ->assertSessionHasNoErrors();

        $cover = PaperIssue::query()->latest('id')->first();
        $this->assertSame(62, $cover->quantity);
        $this->assertSame($gsm65->id, $cover->paper_type_id);
        $this->assertSame(6486, app(PaperStock::class)->available($gsm65->id, $shahrukh->id));
        $loan = PaperLoan::query()->sole();
        $this->assertSame($gsm65->id, $loan->paper_type_id);
        $this->assertSame($musa->id, $loan->borrower_party_id);
    }

    public function test_returned_rims_can_go_into_another_stock(): void
    {
        $owner = User::factory()->owner()->create();
        $type = PaperType::factory()->create();
        [$borrower, $lender, $elsewhere] = Party::factory()->count(3)->create();
        $approved = ['status' => ApprovalStatus::Approved, 'paper_type_id' => $type->id];
        PaperReceipt::factory()->create($approved + ['party_id' => $borrower->id, 'quantity' => 50]);
        $loan = PaperLoan::factory()->create(['paper_type_id' => $type->id, 'borrower_party_id' => $borrower->id, 'lender_party_id' => $lender->id, 'rims' => 5]);

        $this->actingAs($owner)->get(route('paper.index', ['tab' => 'owed']))->assertOk()->assertSee('(taken from here)')->assertSee('Add the 5 rims to');
        $this->actingAs($owner)->patch(route('paper-loans.settle', $loan), ['settled_how' => 'rims', 'settled_on' => today()->toDateString(), 'return_party_id' => $elsewhere->id])->assertSessionHasNoErrors();

        $stock = app(PaperStock::class);
        $this->assertSame(45, $stock->available($type->id, $borrower->id));
        $this->assertSame(5, $stock->available($type->id, $elsewhere->id));
        $this->assertSame(0, $stock->available($type->id, $lender->id));
        $this->actingAs($owner)->get(route('paper.index', ['tab' => 'owed']))->assertSee('Rims returned to '.$elsewhere->name);
    }
}
