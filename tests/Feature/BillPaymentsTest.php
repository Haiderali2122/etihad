<?php

namespace Tests\Feature;

use App\Models\BillPayment;
use App\Models\CashFloat;
use App\Models\Expense;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\MaterialBill;
use App\Models\PlateJob;
use App\Models\PlatePurchase;
use App\Models\ProcessorPayment;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\SaleReceipt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Diesel, Glue and Ink bills and plate bills are paid later: any amount on a date, or everything at once. Each
 * payment is kept with its date, what is not paid stays unpaid, and only payments come off the manager's cash.
 */
class BillPaymentsTest extends TestCase
{
    use RefreshDatabase;

    private function category(string $name): InventoryCategory
    {
        return InventoryCategory::query()->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->sole();
    }

    public function test_a_diesel_bill_is_paid_in_parts_and_only_payments_reach_cash_out(): void
    {
        $owner = User::factory()->owner()->create();
        $manager = User::factory()->create();
        CashFloat::factory()->create(['user_id' => $manager->id]);
        $diesel = $this->category('Diesel');

        // The tab's Add button opens the bill form.
        $this->actingAs($owner)->get(route('expenses.create', ['category' => $diesel->id]))->assertRedirect(route('material-bills.create', $diesel));
        $this->actingAs($owner)->get(route('material-bills.create', $diesel))->assertOk()->assertSee('Paid now');

        $this->actingAs($owner)->post(route('material-bills.store'), [
            'inventory_category_id' => $diesel->id, 'spent_on' => today()->subDays(5)->toDateString(), 'supplier' => 'Shell Pump',
            'items' => [['item' => 'Diesel', 'quantity' => '100', 'rate' => '280'], ['item' => 'Mitti ka tel', 'quantity' => '10', 'rate' => '150']],
        ])->assertSessionHasNoErrors();

        $bill = MaterialBill::query()->sole();
        $this->assertSame(29500, $bill->total);
        $this->assertSame(29500, $bill->unpaid());
        $this->assertSame(0, Expense::query()->count(), 'A bill is not cash out until it is paid.');

        $this->actingAs($owner)->patch(route('material-bills.pay', $bill), ['amount' => 10000, 'paid_on' => today()->subDays(2)->toDateString()])->assertSessionHasNoErrors();
        $this->actingAs($owner)->patch(route('material-bills.pay', $bill), ['amount' => 20000, 'paid_on' => today()->toDateString()])->assertSessionHasErrors('amount');
        $this->actingAs($owner)->patch(route('material-bills.pay', $bill), ['amount' => 5000, 'paid_on' => today()->toDateString()])->assertSessionHasNoErrors();

        $bill->refresh();
        $this->assertSame(15000, $bill->paid_amount);
        $this->assertSame(14500, $bill->unpaid());
        $expenses = Expense::query()->orderBy('spent_on')->get();
        $this->assertSame([10000, 5000], $expenses->pluck('amount')->all());
        // Off the manager's cash, filed under Diesel.
        $this->assertTrue($expenses->every(fn (Expense $e) => $e->user_id === $manager->id && $e->inventory_category_id === $diesel->id));

        $this->actingAs($owner)->get(route('inventory.index', ['tab' => 'material-'.$diesel->id]))->assertOk()
            ->assertSee('Shell Pump')->assertSee('Rs 14,500 unpaid')->assertSee(today()->subDays(2)->format('j M').': Rs 10,000')->assertSee('Pay all (Rs 14,500)');

        // Pay all clears the rest; deleting a payment brings it back as unpaid and takes it out of Cash-out.
        $this->actingAs($owner)->post(route('material-bills.pay-all', $diesel))->assertSessionHasNoErrors();
        $this->assertTrue($bill->refresh()->isFullyPaid());
        $this->assertSame(29500, (int) Expense::query()->sum('amount'));

        $last = BillPayment::query()->latest('id')->first();
        $this->actingAs($owner)->delete(route('bill-payments.destroy', $last))->assertSessionHasNoErrors();
        $this->assertSame(14500, $bill->refresh()->unpaid());
        $this->assertModelMissing($last);
        $this->assertSame(15000, (int) Expense::query()->sum('amount'));

        // Deleting the bill removes its payments from Cash-out too.
        $this->actingAs($owner)->delete(route('records.destroy', ['material-bill', $bill->id]))->assertSessionHasNoErrors();
        $this->assertSame(0, Expense::query()->count());
        $this->assertSame(0, BillPayment::query()->count());
    }

    public function test_ink_bills_add_stock_and_can_be_paid_at_once(): void
    {
        $owner = User::factory()->owner()->create();
        $ink = $this->category('Ink');

        $this->actingAs($owner)->post(route('material-bills.store'), [
            'inventory_category_id' => $ink->id, 'spent_on' => today()->toDateString(),
            'items' => [['item' => 'Black ink', 'quantity' => '5', 'rate' => '1200']],
        ])->assertSessionHasErrors('supplier');

        $this->actingAs($owner)->post(route('material-bills.store'), [
            'inventory_category_id' => $ink->id, 'spent_on' => today()->toDateString(), 'supplier' => 'Ink House', 'paid_now' => 6000,
            'items' => [['item' => 'Black ink', 'quantity' => '5', 'rate' => '1200']],
        ])->assertSessionHasNoErrors();

        $bill = MaterialBill::query()->sole();
        $this->assertTrue($bill->isFullyPaid());
        $this->assertSame(6000, (int) Expense::query()->sum('amount'));
        $this->assertEqualsWithDelta(5.0, $ink->items()->where('name', 'Black ink')->sole()->balance(), 0.001);

        // The total cannot go below what is already paid.
        $this->actingAs($owner)->put(route('material-bills.update', $bill), [
            'inventory_category_id' => $ink->id, 'spent_on' => today()->toDateString(), 'supplier' => 'Ink House',
            'items' => [['item' => 'Black ink', 'quantity' => '2', 'rate' => '1200']],
        ])->assertSessionHasErrors('total');
    }

    public function test_plate_bills_are_paid_in_parts_and_a_processor_payment_clears_the_oldest_first(): void
    {
        $owner = User::factory()->owner()->create();
        $old = PlateJob::factory()->create(['job_on' => today()->subDays(20), 'amount' => 3000]);
        $new = PlateJob::factory()->create(['party_id' => $old->party_id, 'job_on' => today()->subDays(3), 'amount' => 2000]);

        $this->actingAs($owner)->patch(route('plate-jobs.pay', $old), ['amount' => 1000, 'paid_on' => today()->subDays(10)->toDateString()])->assertSessionHasNoErrors();
        $this->assertSame(2000, $old->refresh()->unpaid());
        $this->assertFalse($old->isPaid());

        // Rs 3,000 to the processor: the old bill's Rs 2,000 first, then Rs 1,000 on the newer one.
        $this->actingAs($owner)->post(route('processors.pay', $old->party_id), ['amount' => 3000, 'paid_on' => today()->toDateString()])
            ->assertSessionHasNoErrors()->assertSessionHas('status', fn ($s) => str_contains($s, 'Rs 1,000 still to pay'));
        $this->assertTrue($old->refresh()->isPaid());
        $this->assertSame(1000, $new->refresh()->unpaid());

        $this->actingAs($owner)->get(route('processors.show', $old->party_id))->assertOk()->assertSee('Rs 1,000');
        $this->actingAs($owner)->post(route('plate-jobs.pay-all'))->assertSessionHasNoErrors();
        $this->assertTrue($new->refresh()->isPaid());
        // In Cash-out: Rs 1,000 + Rs 3,000 given + Rs 1,000; the parts of the Rs 3,000 on each bill are not counted twice.
        $this->assertSame(5000, (int) Expense::query()->sum('amount'));
        $this->assertSame(4, BillPayment::query()->count());
        $this->assertSame('Plate bill', BillPayment::query()->first()->cashOutSource());
    }

    public function test_money_given_to_a_processor_beyond_the_bills_is_kept_as_advance_and_used_on_the_next_bill(): void
    {
        $owner = User::factory()->owner()->create();
        $manager = User::factory()->create(['name' => 'Faisal']);
        CashFloat::factory()->create(['user_id' => $manager->id, 'amount' => 100000]);
        $bill = PlateJob::factory()->create(['job_on' => today()->subDays(5), 'amount' => 4000]);
        $name = $bill->processor->name;

        // The Plates tab: stats, the payment form and the returns form, and rows without pay boxes.
        $this->actingAs($owner)->get(route('plate-jobs.index'))->assertOk()
            ->assertSee('Advance with processor')->assertSee('Remaining to pay')->assertSee('Pay processor / give advance')->assertSee('Plates returned')
            ->assertDontSee('name="amount" min="1" max=', false);

        // Rs 10,000: Rs 4,000 clears the bill, Rs 6,000 is kept as advance; Cash-out gets the Rs 10,000 once, off Faisal's cash.
        $this->actingAs($owner)->post(route('plate-jobs.payments.store'), ['processor_name' => mb_strtoupper($name), 'amount' => 10000, 'paid_on' => today()->subDays(2)->toDateString()])
            ->assertSessionHasNoErrors()->assertSessionHas('status', fn ($s) => str_contains($s, 'Rs 4,000 paid on plate bills') && str_contains($s, 'Rs 6,000 kept as advance'));
        $this->assertTrue($bill->refresh()->isPaid());
        $this->assertSame(90000, $manager->cashInHand());
        $this->assertSame(10000, (int) Expense::query()->sum('amount'));
        $this->actingAs($owner)->get(route('plate-jobs.index', ['processor' => $bill->party_id]))->assertOk()->assertSeeInOrder(['Advance with processor', 'Rs 6,000', 'Remaining to pay', 'Rs 0']);

        // The next bill (Rs 7,500) uses the Rs 6,000 straight away; Rs 1,500 is left to pay.
        $next = PlateJob::factory()->create(['party_id' => $bill->party_id, 'job_on' => today(), 'amount' => 7500]);
        $this->assertSame(1500, $next->refresh()->unpaid());
        $this->assertSame(10000, (int) Expense::query()->sum('amount'));

        // Deleting the money given: both bills are unpaid again and it leaves Cash-out.
        $payment = ProcessorPayment::query()->sole();
        $this->actingAs($owner)->delete(route('records.destroy', ['processor-payment', $payment->id]))->assertSessionHasNoErrors();
        $this->assertSame([4000, 7500], [$bill->refresh()->unpaid(), $next->refresh()->unpaid()]);
        $this->assertSame(0, (int) Expense::query()->sum('amount'));
        $this->assertSame(100000, $manager->cashInHand());

        // Plates returned from the separate form: one line per processor and size, the oldest job first.
        $bill->update(['plates' => 10, 'plate_size' => '28X40']);
        $next->update(['plates' => 2, 'plate_size' => '28x40']);
        $other = PlateJob::factory()->create(['party_id' => $bill->party_id, 'job_on' => today(), 'plates' => 5, 'plate_size' => '25X36']);
        $out = fn (int $n) => "\u{2066}{$n}\u{2069} plates still out";
        $this->actingAs($owner)->get(route('plate-jobs.index'))->assertOk()->assertSee($out(12), false)->assertSee($out(5), false)->assertDontSee($out(10), false);
        $this->actingAs($owner)->post(route('plate-jobs.returns.store'), ['group' => $bill->party_id.'|28x40', 'returned_plates' => 11, 'returned_on' => today()->toDateString()])->assertSessionHasNoErrors();
        $this->assertSame([0, 1, 5], [$bill->refresh()->platesHeld(), $next->refresh()->platesHeld(), $other->refresh()->platesHeld()]);
        $this->actingAs($owner)->post(route('plate-jobs.returns.store'), ['group' => $bill->party_id.'|28x40', 'returned_plates' => 2, 'returned_on' => today()->toDateString()])->assertSessionHasErrors('returned_plates');
    }

    public function test_pay_all_takes_a_date_and_bill_forms_fill_the_last_rate(): void
    {
        $owner = User::factory()->owner()->create();
        $glue = $this->category('Glue');
        $this->actingAs($owner)->post(route('material-bills.store'), [
            'inventory_category_id' => $glue->id, 'spent_on' => today()->subDays(6)->toDateString(), 'supplier' => 'Glue Co',
            'items' => [['item' => 'Binding glue', 'quantity' => '3', 'rate' => '455.5']],
        ])->assertSessionHasNoErrors();

        // The next bill offers the last price per quantity for the item.
        $this->actingAs($owner)->get(route('material-bills.create', $glue))->assertOk()->assertSee('\u0022binding glue\u0022:455.5', false);
        $this->actingAs($owner)->get(route('inventory.index', ['tab' => 'material-'.$glue->id]))->assertSee('name="paid_on"', false);

        $this->actingAs($owner)->post(route('material-bills.pay-all', $glue), ['paid_on' => today()->subDays(2)->toDateString()])->assertSessionHasNoErrors();
        $this->assertSame(today()->subDays(2)->toDateString(), BillPayment::query()->sole()->paid_on->toDateString());

        // A new plate job starts with the processor's last rate.
        PlateJob::factory()->create(['rate' => 340, 'plate_size' => '20x30']);
        $this->actingAs($owner)->get(route('plate-jobs.create'))->assertOk()->assertSee('\u002220x30\u0022:340', false);
    }

    public function test_the_owner_searches_spending_across_mixed_receipts(): void
    {
        $owner = User::factory()->owner()->create();
        $this->actingAs($owner)->post(route('expenses.store'), [
            'spent_on' => today()->toDateString(),
            'items' => [['item' => 'Nut bolts', 'amount' => 700], ['item' => 'Tea', 'amount' => 300]],
        ])->assertSessionHasNoErrors();
        $this->actingAs($owner)->post(route('expenses.store'), [
            'spent_on' => today()->toDateString(),
            'items' => [['item' => 'Nut bolts', 'amount' => 500], ['item' => 'Rickshaw', 'amount' => 5000]],
        ])->assertSessionHasNoErrors();

        // The dropdown lists the biggest spending first.
        $page = $this->actingAs($owner)->get(route('expenses.index', ['period' => 'today']))->assertOk();
        $page->assertSeeInOrder(['Rickshaw — Rs 5,000', 'Nut bolts — Rs 1,200', 'Tea — Rs 300']);

        $this->actingAs($owner)->get(route('expenses.index', ['q' => 'nut', 'period' => 'today']))->assertOk()
            ->assertSee('2 entries for &quot;nut&quot;: Rs 1,200', false);
        $this->actingAs($owner)->get(route('expenses.index', ['q' => 'nut', 'period' => 'yesterday']))->assertOk()
            ->assertSee('0 entries for &quot;nut&quot;: Rs 0', false);
    }

    public function test_raddi_money_is_received_in_parts_and_shown_on_the_dashboard_apart_from_cash_out(): void
    {
        $owner = User::factory()->owner()->create();
        $raddi = $this->category('Raddi');

        $this->actingAs($owner)->post(route('sales.store', $raddi), [
            'sold_on' => today()->subDays(4)->toDateString(), 'received_now' => 2000,
            'items' => [['item' => 'Raddi', 'quantity' => '100', 'rate' => '50']],
        ])->assertSessionHasNoErrors();
        $sale = Sale::query()->sole();
        $this->assertSame(3000, $sale->toReceive());

        $this->actingAs($owner)->patch(route('sales.receive', $sale), ['amount' => 1000, 'received_on' => today()->subDays(1)->toDateString()])->assertSessionHasNoErrors();
        $this->actingAs($owner)->patch(route('sales.receive', $sale), ['amount' => 5000, 'received_on' => today()->toDateString()])->assertSessionHasErrors('amount');
        $this->assertSame(2000, $sale->refresh()->toReceive());

        $this->actingAs($owner)->get(route('inventory.index', ['tab' => 'material-'.$raddi->id]))->assertOk()
            ->assertSee('Rs 2,000 to receive')->assertSee(today()->subDays(1)->format('j M').': Rs 1,000')->assertSee('Receive all (Rs 2,000)');
        $this->actingAs($owner)->get(route('dashboard'))->assertOk()->assertSee('Raddi still to be received')->assertSee('Rs 2,000');

        $this->actingAs($owner)->post(route('sales.receive-all', $raddi), ['received_on' => today()->toDateString()])->assertSessionHasNoErrors();
        $this->assertSame(0, $sale->refresh()->toReceive());
        $this->assertSame(3, SaleReceipt::query()->count());
        // Money in never reaches Cash-out.
        $this->assertSame(0, Expense::query()->count());

        $this->actingAs($owner)->delete(route('records.destroy', ['sale-receipt', SaleReceipt::query()->latest('id')->first()->id]))->assertSessionHasNoErrors();
        $this->assertSame(2000, $sale->refresh()->toReceive());
    }

    public function test_inventory_purchases_are_paid_in_parts_and_only_payments_reach_cash_out(): void
    {
        $owner = User::factory()->owner()->create();
        $item = InventoryItem::factory()->create();

        $this->actingAs($owner)->post(route('purchases.store'), [
            'purchased_on' => today()->subDays(3)->toDateString(), 'supplier_name' => 'Ali Traders',
            'inventory_item_id' => $item->id, 'quantity' => 10, 'rate' => 500, 'paid_now' => 1000,
        ])->assertSessionHasNoErrors();
        $purchase = Purchase::query()->sole();
        $this->assertSame(4000, $purchase->unpaid());
        $this->assertSame(1000, (int) Expense::query()->sum('amount'));

        $this->actingAs($owner)->patch(route('purchases.pay', $purchase), ['amount' => 1500, 'paid_on' => today()->subDay()->toDateString()])->assertSessionHasNoErrors();
        $this->actingAs($owner)->get(route('inventory.index', ['tab' => 'purchases']))->assertOk()
            ->assertSee('Rs 2,500 unpaid')->assertSee('Pay all (Rs 2,500)')->assertSee(today()->subDay()->format('j M').': Rs 1,500');

        $this->actingAs($owner)->post(route('purchases.pay-all'), ['paid_on' => today()->toDateString()])->assertSessionHasNoErrors();
        $this->assertTrue($purchase->refresh()->isFullyPaid());
        $this->assertSame(5000, (int) Expense::query()->sum('amount'));
        $this->assertSame('Purchase bill', BillPayment::query()->first()->cashOutSource());
        // Stock counts the whole purchase whatever has been paid.
        $this->assertEqualsWithDelta(10.0, $item->refresh()->balance(), 0.001);
    }

    public function test_plates_are_bought_by_size_paid_in_parts_and_plate_jobs_take_from_stock(): void
    {
        $owner = User::factory()->owner()->create();

        $this->actingAs($owner)->get(route('plate-stock.create'))->assertOk();
        $this->actingAs($owner)->post(route('plate-stock.store'), [
            'purchased_on' => today()->subDays(2)->toDateString(), 'supplier' => 'Kodak House', 'plate_size' => '20x30',
            'plates' => 100, 'rate' => 150, 'paid_now' => 5000,
        ])->assertSessionHasNoErrors();
        $purchase = PlatePurchase::query()->sole();
        $this->assertSame(15000, $purchase->amount);
        $this->assertSame(10000, $purchase->unpaid());
        $this->assertSame(5000, (int) Expense::query()->sum('amount'));

        // A plate job of that size takes from stock; the size list shows what is in stock.
        PlateJob::factory()->create(['plate_size' => '20X30 ', 'plates' => 12]);
        $this->assertSame(88, PlatePurchase::stockBySize()->get('20x30')['stock']);
        $this->actingAs($owner)->get(route('plate-jobs.create'))->assertOk()->assertSee('88 in stock');
        $this->actingAs($owner)->get(route('plate-stock.index'))->assertOk()->assertSee('Plate stock')->assertSee('Kodak House')
            ->assertSee('Rs 10,000 unpaid')->assertSee('Pay all (Rs 10,000)');

        // The next purchase of that size offers the last price.
        $this->actingAs($owner)->get(route('plate-stock.create'))->assertSee('\u0022kodak house\u0022:150', false);

        $this->actingAs($owner)->post(route('plate-stock.pay-all'), ['paid_on' => today()->toDateString()])->assertSessionHasNoErrors();
        $this->assertTrue($purchase->refresh()->isFullyPaid());
        $this->assertSame('Plate purchase', BillPayment::query()->latest('id')->first()->cashOutSource());
    }
}
