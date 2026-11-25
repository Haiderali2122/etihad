<?php

namespace Tests\Feature;

use App\Enums\ApprovalStatus;
use App\Enums\PartyKind;
use App\Enums\PrintMode;
use App\Enums\RimMove;
use App\Enums\WorkOrderStage;
use App\Models\CashFloat;
use App\Models\Expense;
use App\Models\Godown;
use App\Models\PaperType;
use App\Models\Party;
use App\Models\RimLoading;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\YounusPayment;
use App\Support\PaperStock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Younus ka khata: all his work entered on the khata (an order only when it belongs to one), rims into the godown
 * going into stock, loading to folding moving the order on, and his weekly pay: advances, then the rest on Saturday.
 */
class YounusKhataTest extends TestCase
{
    use RefreshDatabase;

    private function approved(array $attributes = []): WorkOrder
    {
        $order = WorkOrder::factory()->create($attributes);
        $order->forceFill(['status' => ApprovalStatus::Approved])->save();

        return $order->refresh();
    }

    private function line(User $by, array $data): TestResponse
    {
        return $this->actingAs($by)->post(route('rim-loadings.store-line'), $data + ['loaded_on' => today()->toDateString(), 'unit' => 'rims']);
    }

    public function test_an_in_house_order_gets_its_younus_work_from_the_khata_and_loading_to_folding_moves_it_on(): void
    {
        $owner = User::factory()->owner()->create();
        $order = $this->approved(['rims_needed' => 206]);

        // The work order sends you to the khata with the order filled in.
        $this->actingAs($owner)->get(route('work-orders.show', $order))->assertOk()
            ->assertSee('Add Younus work: godown → our press')->assertSee(route('rim-loadings.index', ['order' => $order->id, 'kind' => 'to_press']));
        $this->actingAs($owner)->get(route('rim-loadings.index', ['order' => $order->id, 'kind' => 'to_press']))->assertOk()
            ->assertSee('value="'.$order->number.' · '.$order->bookName().'"', false)->assertSee('value="Godown → our press"', false);

        $this->line($owner, ['work_type' => 'Godown → our press', 'details' => 'Paper for '.$order->number, 'work_order' => $order->number, 'quantity' => 206, 'rate' => 5])
            ->assertSessionHasNoErrors()->assertSessionHas('status', fn ($s) => str_contains($s, 'Linked to '.$order->number));
        $this->assertSame(1030, $order->rimLoadings()->where('kind', RimMove::ToPress)->sole()->amount);

        $this->actingAs($owner)->patch(route('work-orders.advance', $order), ['printed_copies' => 10000, 'printed_on' => today()->toDateString()])->assertSessionHasNoErrors();
        $this->assertSame(WorkOrderStage::ToFolding, $order->refresh()->stage);
        $this->actingAs($owner)->get(route('work-orders.show', $order))->assertSee('Add Younus work: our press → folding');

        // Loading to folding entered on the khata finishes the order's step by itself.
        $this->line($owner, ['work_type' => 'Our press → folding', 'details' => '4C book 16 forme', 'work_order' => $order->number.' · '.$order->bookName(), 'quantity' => 200, 'rate' => 6])
            ->assertSessionHasNoErrors()->assertSessionHas('status', fn ($s) => str_contains($s, 'moved to Folding'));
        $this->assertSame(1200, $order->rimLoadings()->where('kind', RimMove::ToFolding)->sole()->amount);
        $this->assertSame(WorkOrderStage::Folding, $order->refresh()->stage);
    }

    public function test_an_outsourced_order_gets_the_trip_the_printing_charge_and_the_trip_back(): void
    {
        $owner = User::factory()->owner()->create();
        $press = Party::factory()->kind(PartyKind::Press)->create(['name' => 'Al-Noor Press']);
        $order = $this->approved(['mode' => PrintMode::Outsource, 'machine_id' => null, 'press_id' => $press->id, 'printing_rate' => 40, 'rims_needed' => 100]);

        $this->actingAs($owner)->get(route('work-orders.show', $order))->assertSee('Add Younus work: godown → outside press');
        $this->line($owner, ['work_type' => 'Godown → outside press', 'work_order' => $order->number, 'quantity' => 100, 'rate' => 6])->assertSessionHasNoErrors();
        $this->line($owner, ['work_type' => 'Outside printing charge', 'work_order' => $order->number, 'quantity' => 100, 'rate' => 40])->assertSessionHasNoErrors();
        $this->assertSame(600, $order->rimLoadings()->where('kind', RimMove::ToOutside)->sole()->amount);
        $this->assertSame(4000, $order->rimLoadings()->where('kind', RimMove::Printing)->sole()->amount);

        $this->actingAs($owner)->post(route('work-orders.outside-receipts.store', $order), [
            'received_on' => today()->toDateString(), 'printed_copies' => 9500, 'leftover_rims' => 10, 'godown_id' => Godown::factory()->create()->id,
        ])->assertSessionHasNoErrors();
        $this->actingAs($owner)->patch(route('work-orders.advance', $order))->assertSessionHasNoErrors();
        $this->assertSame(WorkOrderStage::ToFolding, $order->refresh()->stage);

        $this->line($owner, ['work_type' => 'Outside press → back for folding', 'work_order' => $order->number, 'quantity' => 90, 'rate' => 6])->assertSessionHasNoErrors();
        $this->assertSame(540, $order->rimLoadings()->where('kind', RimMove::FromOutside)->sole()->amount);
        $this->assertSame(WorkOrderStage::Folding, $order->refresh()->stage);
    }

    public function test_rims_brought_into_the_godown_go_into_stock_and_any_typed_work_is_remembered(): void
    {
        $owner = User::factory()->owner()->create();
        $paper = PaperType::factory()->create();
        $godown = Godown::factory()->create(['name' => 'Main godown']);

        // Rims into the godown need the paper, whose it is and where it is unloaded.
        $this->line($owner, ['work_type' => 'Rims into godown (from outside)', 'quantity' => 40, 'rate' => 6])->assertSessionHasErrors(['rows.0.paper_type_id', 'rows.0.party_name', 'rows.0.godown_id']);
        $this->line($owner, ['work_type' => 'Rims into godown (from outside)', 'details' => 'AA paper Imran Ajmal 40 rims', 'quantity' => 40, 'rate' => 6,
            'paper_type_id' => $paper->id, 'party_name' => 'Imran Ajmal', 'godown_id' => $godown->id])
            ->assertSessionHasNoErrors()->assertSessionHas('status', fn ($s) => str_contains($s, '40 rims added to the') && str_contains($s, 'Main godown'));
        $party = Party::query()->where('name', 'Imran Ajmal')->sole();
        $this->assertSame(40, app(PaperStock::class)->available($paper->id, $party->id));

        // Anything else is typed and shows next time, with its rate.
        $this->line($owner, ['work_type' => 'Unloading reel truck', 'details' => '2 trips', 'quantity' => 2, 'unit' => 'trips', 'rate' => 300])->assertSessionHasNoErrors();
        $this->actingAs($owner)->get(route('rim-loadings.index'))->assertOk()
            ->assertSee('<option value="Unloading reel truck">', false)->assertSee('2 trips')
            ->assertSee('\u0022unloading reel truck|trips\u0022:300', false);

        // The work and the work order can both be left empty: the details say what it was.
        $this->line($owner, ['details' => 'Cleaned the godown', 'quantity' => 1, 'unit' => 'trips', 'rate' => 500])->assertSessionHasNoErrors();
        $loose = RimLoading::query()->where('details', 'Cleaned the godown')->sole();
        $this->assertSame(RimMove::Other, $loose->kind);
        $this->assertNull($loose->work_order_id);
        $this->assertSame('Other work', $loose->workLabel());

        // Deleting the godown line takes its rims back out of stock.
        $this->actingAs($owner)->delete(route('rim-loadings.destroy', RimLoading::query()->where('kind', RimMove::IntoGodown)->sole()))->assertSessionHasNoErrors();
        $this->assertSame(0, app(PaperStock::class)->available($paper->id, $party->id));
    }

    public function test_younus_gets_advances_in_the_week_and_the_rest_on_saturday_off_the_managers_cash(): void
    {
        $owner = User::factory()->owner()->create();
        $manager = User::factory()->create();
        CashFloat::factory()->create(['user_id' => $manager->id, 'amount' => 50000]);
        $this->line($owner, ['work_type' => 'Truck loading (dispatch)', 'quantity' => 500, 'unit' => 'books', 'rate' => 2])->assertSessionHasNoErrors();
        $this->line($owner, ['work_type' => 'Godown → our press', 'quantity' => 100, 'rate' => 5])->assertSessionHasNoErrors();

        $this->actingAs($owner)->post(route('rim-loadings.advance'), ['amount' => 600, 'paid_on' => today()->toDateString()])->assertSessionHasNoErrors();
        $this->actingAs($owner)->get(route('rim-loadings.index'))->assertOk()
            ->assertSee('Rs 1,500')->assertSee('Rs 600')->assertSee('Pay Rs 900');

        // Saturday: the rest. No more than he is owed.
        $this->actingAs($owner)->post(route('rim-loadings.settle'), ['amount' => 5000, 'paid_on' => today()->toDateString()])->assertSessionHasErrors('amount');
        $this->actingAs($owner)->post(route('rim-loadings.settle'), ['paid_on' => today()->toDateString()])->assertSessionHasNoErrors();
        $this->assertSame([600, 900], YounusPayment::query()->orderBy('id')->pluck('amount')->all());
        // Both come off the manager's cash.
        $this->assertSame(1500, (int) Expense::query()->where('user_id', $manager->id)->sum('amount'));
        $this->actingAs($owner)->get(route('rim-loadings.index'))->assertDontSee('Pay the rest (Saturday)');
    }

    public function test_several_entries_are_saved_in_one_go_and_the_receipts_list_the_week(): void
    {
        $owner = User::factory()->owner()->create();
        $order = $this->approved(['rims_needed' => 120]);

        // The form opens with a single entry; the next appears only once the first is being filled.
        $this->actingAs($owner)->get(route('rim-loadings.index'))->assertOk()->assertSee('name="rows[0][details]"', false)->assertDontSee('name="rows[1][details]"', false);

        // Two entries and the empty one the form always shows: the empty one is dropped.
        $this->actingAs($owner)->post(route('rim-loadings.store-line'), ['loaded_on' => today()->toDateString(), 'rows' => [
            ['work_type' => 'Godown → our press', 'details' => 'Paper for '.$order->number, 'work_order' => $order->number, 'quantity' => 120, 'unit' => 'rims', 'rate' => 5],
            ['work_type' => '', 'details' => 'Truck of books to Lahore', 'work_order' => '', 'quantity' => 800, 'unit' => 'books', 'rate' => 1.5],
            ['work_type' => '', 'details' => '', 'work_order' => '', 'quantity' => '', 'unit' => 'rims', 'rate' => ''],
        ]])->assertSessionHasNoErrors()->assertSessionHas('status', fn ($s) => str_contains($s, '2 entries, Rs 1,800') && str_contains($s, 'Linked to '.$order->number));
        $this->assertSame(2, RimLoading::query()->count());

        // A wrong entry stops the save and the entries come back to fix.
        $this->actingAs($owner)->post(route('rim-loadings.store-line'), ['loaded_on' => today()->toDateString(), 'rows' => [
            ['details' => 'Missing the rate', 'quantity' => 10, 'unit' => 'rims', 'rate' => ''],
        ]])->assertSessionHasErrors('rows.0.rate');
        $this->assertSame(2, RimLoading::query()->count());

        $this->actingAs($owner)->post(route('rim-loadings.advance'), ['amount' => 700, 'paid_on' => today()->toDateString(), 'note' => 'Monday'])->assertSessionHasNoErrors();

        // Work receipt: the week's work and its total only. Advance receipt: dates, amounts and the total.
        $week = YounusPayment::weekStart(today())->toDateString();
        $this->actingAs($owner)->get(route('rim-loadings.receipt', ['week' => $week, 'kind' => 'work']))->assertOk()
            ->assertSee('Truck of books to Lahore')->assertSee('800 books')->assertSee($order->number)->assertSee('Rs 1,800')
            ->assertDontSee('Less advances')->assertDontSee('Monday')->assertDontSee('YOUNUS ADVANCE');
        $this->actingAs($owner)->get(route('rim-loadings.receipt', ['week' => $week, 'kind' => 'advance']))->assertOk()
            ->assertSee('YOUNUS ADVANCE')->assertSee('Monday')->assertSee('Rs 700')->assertSee(today()->format('j M Y'))->assertDontSee('Truck of books');

        // Week receipt: the week's bill, each advance with its date, and what remains (1,800 − 700 = 1,100).
        $this->actingAs($owner)->get(route('rim-loadings.receipt', ['week' => $week, 'kind' => 'week']))->assertOk()
            ->assertSee('YOUNUS WEEK')->assertSeeInOrder(['Total week bill', 'Rs 1,800', 'Monday', 'Rs 700', 'Remaining', 'Rs 1,100']);
        // The week receipt button shows only once he has been paid for the week.
        $this->actingAs($owner)->get(route('rim-loadings.index'))->assertDontSee(route('rim-loadings.receipt', ['week' => $week, 'kind' => 'week']));
        $this->actingAs($owner)->post(route('rim-loadings.settle'), ['paid_on' => today()->toDateString()])->assertSessionHasNoErrors();
        $this->actingAs($owner)->get(route('rim-loadings.index'))->assertSee(route('rim-loadings.receipt', ['week' => $week, 'kind' => 'week']))
            ->assertSee('class="week-split"', false)->assertSee('Work by week');
    }
}
