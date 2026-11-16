<?php

namespace Tests\Feature;

use App\Enums\ApprovalStatus;
use App\Enums\PartyKind;
use App\Enums\PrintMode;
use App\Enums\RimMove;
use App\Enums\WorkOrderStage;
use App\Models\Godown;
use App\Models\Party;
use App\Models\RimLoading;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Younus ka khata: every rim Younus moves, and the outside printing charge he takes to the press, entered on the
 * work order when the rims move (rims × rate per rim), and paid line by line or all at once.
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

    public function test_an_in_house_order_enters_godown_to_press_at_printing_and_press_to_folding_at_loading(): void
    {
        $owner = User::factory()->owner()->create();
        RimLoading::factory()->create(['kind' => RimMove::ToPress, 'rims' => 1, 'rate_per_rim' => 5, 'amount' => 5, 'paid_on' => today()]);
        $order = $this->approved(['rims_needed' => 206]);
        $this->assertSame(0, $order->rimLoadings()->count());

        // Printing step: Younus carrying the paper, starting at the last rate used; no folding fields here.
        $this->actingAs($owner)->get(route('work-orders.show', $order))->assertOk()
            ->assertSee('Younus: godown → our press')->assertSee('To pay Younus')->assertDontSee('Younus: our press → folding');
        $this->actingAs($owner)->post(route('work-orders.younus-paper', $order), ['carried_on' => today()->toDateString(), 'younus_rims' => 206, 'younus_rate' => 5])->assertSessionHasNoErrors();
        $this->assertSame(1030, $order->rimLoadings()->where('kind', RimMove::ToPress)->sole()->amount);

        $this->actingAs($owner)->patch(route('work-orders.advance', $order), ['printed_copies' => 10000, 'printed_on' => today()->toDateString()])->assertSessionHasNoErrors();
        $this->assertSame(WorkOrderStage::ToFolding, $order->refresh()->stage);

        // Loading to folding step: Younus carrying the printed rims to folding.
        $this->actingAs($owner)->get(route('work-orders.show', $order))->assertSee('Younus: our press → folding')->assertSee('To pay Younus');
        $this->actingAs($owner)->patch(route('work-orders.advance', $order))->assertSessionHasErrors('stage');
        $this->actingAs($owner)->post(route('rim-loadings.store', $order), ['loaded_on' => today()->toDateString(), 'rims' => 200, 'rate_per_rim' => 6])->assertSessionHasNoErrors();
        $this->assertSame(1200, $order->rimLoadings()->where('kind', RimMove::ToFolding)->sole()->amount);
        $this->actingAs($owner)->patch(route('work-orders.advance', $order))->assertSessionHasNoErrors();
        $this->assertSame(WorkOrderStage::Folding, $order->refresh()->stage);
    }

    public function test_an_outsourced_order_enters_the_trip_out_with_the_printing_charge_and_the_trip_back(): void
    {
        $owner = User::factory()->owner()->create();
        $press = Party::factory()->kind(PartyKind::Press)->create(['name' => 'Al-Noor Press']);
        $order = $this->approved(['mode' => PrintMode::Outsource, 'machine_id' => null, 'press_id' => $press->id, 'printing_rate' => 40, 'rims_needed' => 100]);

        // The printing rate comes from the order: the box shows the worked-out charge, not a rate field.
        $this->actingAs($owner)->get(route('work-orders.show', $order))->assertSee('Younus: godown → Al-Noor Press')
            ->assertSee('Printing charge: rims × 40')->assertDontSee('name="printing_rate"', false);
        $this->actingAs($owner)->post(route('work-orders.younus-paper', $order), ['carried_on' => today()->toDateString(), 'younus_rims' => 100, 'younus_rate' => 6])->assertSessionHasNoErrors();
        $this->assertSame(600, $order->rimLoadings()->where('kind', RimMove::ToOutside)->sole()->amount);
        $this->assertSame(4000, $order->rimLoadings()->where('kind', RimMove::Printing)->sole()->amount);

        $this->actingAs($owner)->post(route('work-orders.outside-receipts.store', $order), [
            'received_on' => today()->toDateString(), 'printed_copies' => 9500, 'leftover_rims' => 10, 'godown_id' => Godown::factory()->create()->id,
        ])->assertSessionHasNoErrors();
        $this->actingAs($owner)->patch(route('work-orders.advance', $order))->assertSessionHasNoErrors();
        $this->assertSame(WorkOrderStage::ToFolding, $order->refresh()->stage);

        $this->actingAs($owner)->get(route('work-orders.show', $order))->assertSee('Younus: outside press → back for folding');
        $this->actingAs($owner)->post(route('rim-loadings.store', $order), ['loaded_on' => today()->toDateString(), 'rims' => 90, 'rate_per_rim' => 6])->assertSessionHasNoErrors();
        $this->assertSame(540, $order->rimLoadings()->where('kind', RimMove::FromOutside)->sole()->amount);
    }

    public function test_lines_are_paid_one_by_one_or_all_at_once_and_go_to_cash_out(): void
    {
        $owner = User::factory()->owner()->create();
        $a = RimLoading::factory()->create(['kind' => RimMove::ToPress, 'rims' => 100, 'rate_per_rim' => 5, 'amount' => 500]);
        $b = RimLoading::factory()->create(['kind' => RimMove::Printing, 'rims' => 100, 'rate_per_rim' => 40, 'amount' => 4000]);
        $c = RimLoading::factory()->create(['kind' => RimMove::FromOutside, 'rims' => 90, 'rate_per_rim' => 6, 'amount' => 540]);

        $this->actingAs($owner)->get(route('rim-loadings.index'))->assertOk()
            ->assertSee('Pay all (Rs 5,040)')->assertSee('Godown → our press')->assertSee('Outside printing charge');

        // Edit a line before paying it: the amount follows rims × rate.
        $this->actingAs($owner)->get(route('rim-loadings.edit', $c))->assertOk();
        $this->actingAs($owner)->put(route('rim-loadings.update', $c), ['kind' => 'from_outside', 'loaded_on' => today()->toDateString(), 'rims' => 95, 'rate_per_rim' => 6])->assertSessionHasNoErrors();
        $this->assertSame(570, $c->fresh()->amount);

        $this->actingAs($owner)->patch(route('rim-loadings.pay', $a))->assertSessionHasNoErrors();
        $this->assertTrue($a->fresh()->isPaid());
        $this->assertSame('Loading labour', $a->fresh()->cashOutExpense->head->name);

        $this->actingAs($owner)->post(route('rim-loadings.pay-all'))->assertSessionHas('status', 'Paid all 2 lines to Younus: Rs 4,570.');
        $this->assertTrue($b->fresh()->isPaid());
        $this->assertSame('Outside printing', $b->fresh()->cashOutExpense->head->name);
        $this->assertSame(0, RimLoading::query()->whereNull('paid_on')->count());

        // A paid line can no longer be changed.
        $this->actingAs($owner)->get(route('rim-loadings.edit', $a))->assertStatus(409);
    }
}
