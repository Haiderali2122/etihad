<?php

namespace Tests\Feature;

use App\Enums\PayrollStatus;
use App\Enums\WorkOrderStage;
use App\Models\Contractor;
use App\Models\ContractorPayment;
use App\Models\Deletion;
use App\Models\Dispatch;
use App\Models\Expense;
use App\Models\ExpenseHead;
use App\Models\ForemanSheet;
use App\Models\InventoryCategory;
use App\Models\InventoryIssue;
use App\Models\PaperIssue;
use App\Models\PaperReceipt;
use App\Models\Party;
use App\Models\PayrollRun;
use App\Models\Purchase;
use App\Models\Role;
use App\Models\SalaryAdvance;
use App\Models\Sale;
use App\Models\User;
use App\Models\WorkOrder;
use App\Support\OtherOption;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class RecordDeletionTest extends TestCase
{
    use RefreshDatabase;

    private function deleteRecord(User $user, string $type, int $id, string $reason = 'Entered by mistake'): TestResponse
    {
        return $this->actingAs($user)->from(route('dashboard'))
            ->delete(route('records.destroy', [$type, $id]), ['delete_note' => $reason]);
    }

    public function test_a_truck_slip_is_deleted_and_logged_but_not_once_its_rims_are_issued(): void
    {
        $owner = User::factory()->owner()->create();
        $receipt = PaperReceipt::factory()->create(['quantity' => 50]);

        $this->actingAs($owner)->get(route('paper-receipts.show', $receipt))->assertSee(route('records.destroy', ['paper-receipt', $receipt->id]), false);
        $this->deleteRecord($owner, 'paper-receipt', $receipt->id)->assertSessionHasNoErrors();
        $this->assertModelMissing($receipt);
        $this->assertSame('Paper receipt', Deletion::query()->sole()->kind);

        $used = PaperReceipt::factory()->create(['quantity' => 50]);
        PaperIssue::factory()->create(['party_id' => $used->party_id, 'paper_type_id' => $used->paper_type_id, 'quantity' => 20]);
        $this->deleteRecord($owner, 'paper-receipt', $used->id)->assertSessionHasErrors('delete');
        $this->assertModelExists($used);
    }

    public function test_a_purchase_whose_stock_was_moved_out_cannot_be_deleted(): void
    {
        $owner = User::factory()->owner()->create();
        $purchase = Purchase::factory()->create(['quantity' => 10]);
        InventoryIssue::factory()->create(['inventory_item_id' => $purchase->inventory_item_id, 'quantity' => 4]);

        $this->deleteRecord($owner, 'purchase', $purchase->id)->assertSessionHasErrors('delete');
        $this->assertModelExists($purchase);

        $unused = Purchase::factory()->create(['quantity' => 5]);
        $this->deleteRecord($owner, 'purchase', $unused->id)->assertSessionHasNoErrors();
        $this->assertModelMissing($unused);
        $this->assertNull(Expense::query()->where('source_type', $unused->getMorphClass())->where('source_id', $unused->id)->first());
    }

    public function test_a_party_with_records_is_kept_and_an_unused_one_can_go(): void
    {
        $owner = User::factory()->owner()->create();
        $used = PaperReceipt::factory()->create()->party;
        $unused = Party::factory()->create();

        $this->deleteRecord($owner, 'party', $used->id)->assertSessionHasErrors('delete');
        $this->deleteRecord($owner, 'party', $unused->id)->assertSessionHasNoErrors();
        $this->assertModelExists($used);
        $this->assertModelMissing($unused);
    }

    public function test_deleting_a_truck_loading_reopens_its_order(): void
    {
        $owner = User::factory()->owner()->create();
        $order = WorkOrder::factory()->create(['copies' => 2500, 'stage' => WorkOrderStage::Dispatched]);
        $dispatch = Dispatch::factory()->create(['work_order_id' => $order->id, 'copies' => 2500]);

        $this->deleteRecord($owner, 'dispatch', $dispatch->id)->assertSessionHasNoErrors();

        $this->assertModelMissing($dispatch);
        $this->assertSame(WorkOrderStage::Ready, $order->refresh()->stage);
    }

    public function test_a_foreman_sheet_takes_its_advances_with_it_unless_they_were_deducted(): void
    {
        $owner = User::factory()->owner()->create();
        $sheet = ForemanSheet::factory()->create();
        $advance = SalaryAdvance::factory()->create(['foreman_sheet_id' => $sheet->id]);

        $this->deleteRecord($owner, 'salary-advance', $advance->id)->assertSessionHasErrors('delete');
        $this->deleteRecord($owner, 'foreman-sheet', $sheet->id)->assertSessionHasNoErrors();
        $this->assertModelMissing($sheet);
        $this->assertModelMissing($advance);

        $kept = ForemanSheet::factory()->create();
        SalaryAdvance::factory()->create(['foreman_sheet_id' => $kept->id, 'payroll_run_id' => PayrollRun::factory()->create()->id]);
        $this->deleteRecord($owner, 'foreman-sheet', $kept->id)->assertSessionHasErrors('delete');
        $this->assertModelExists($kept);
    }

    public function test_only_a_draft_payroll_can_be_deleted(): void
    {
        $owner = User::factory()->owner()->create();
        $final = PayrollRun::factory()->create(['status' => PayrollStatus::Finalized]);
        $draft = PayrollRun::factory()->create(['month' => today()->subMonth()->startOfMonth()]);

        $this->deleteRecord($owner, 'payroll-run', $final->id)->assertSessionHasErrors('delete');
        $this->deleteRecord($owner, 'payroll-run', $draft->id)->assertSessionHasNoErrors();
        $this->assertModelExists($final);
        $this->assertModelMissing($draft);
    }

    public function test_the_section_permission_is_needed_but_a_reason_is_not(): void
    {
        $clerk = User::factory()->create(['role_id' => Role::factory()->create(['permissions' => ['records.delete', 'paper.view']])->id]);
        $receipt = PaperReceipt::factory()->create();

        $this->deleteRecord($clerk, 'paper-receipt', $receipt->id)->assertForbidden();
        $this->deleteRecord(User::factory()->owner()->create(), 'unknown', 1)->assertNotFound();
        $this->assertModelExists($receipt);

        $this->actingAs(User::factory()->owner()->create())->from(route('dashboard'))
            ->delete(route('records.destroy', ['paper-receipt', $receipt->id]))->assertSessionHasNoErrors();
        $this->assertModelMissing($receipt);
    }

    public function test_a_cash_out_row_from_another_tab_deletes_the_payment_there_too(): void
    {
        $owner = User::factory()->owner()->create();
        $payment = ContractorPayment::factory()->create(['created_by' => $owner->id]);
        $mirror = $payment->cashOutExpense()->sole();

        $this->actingAs($owner)->get(route('expenses.index'))->assertSee(route('expenses.destroy', $mirror), false)->assertSee('value="DELETE"', false);
        $this->actingAs($owner)->delete(route('expenses.destroy', $mirror), ['delete_note' => 'Paid twice'])->assertRedirect(route('expenses.index'));

        $this->assertModelMissing($payment);
        $this->assertModelMissing($mirror);
        $this->assertSame('Paid twice', Deletion::query()->sole()->note);
    }

    public function test_an_expense_needs_only_a_head_and_an_amount(): void
    {
        $owner = User::factory()->owner()->create();
        $head = ExpenseHead::factory()->create(['name' => 'Tea']);

        $this->actingAs($owner)->get(route('expenses.create'))->assertOk()->assertDontSee('What for')->assertDontSee('Paid to')->assertSee('Price')->assertSee('<option value="Tea">', false);
        $this->actingAs($owner)->post(route('expenses.store'), [
            'spent_on' => today()->toDateString(),
            'items' => [['expense_head_id' => $head->id, 'paid_to' => '', 'amount' => 350]],
        ])->assertSessionHasNoErrors();

        $expense = Expense::query()->sole();
        $this->assertSame(350, $expense->amount);
        $this->assertSame('—', $expense->paid_to);
        $this->assertNull($expense->items()->sole()->paid_to);
        $this->actingAs($owner)->get(route('expenses.show', $expense))->assertOk()->assertSee('Tea');
    }

    public function test_a_party_of_type_other_takes_a_typed_type_that_is_offered_next_time(): void
    {
        $owner = User::factory()->owner()->create();
        $save = fn (string $name, array $kind) => $this->actingAs($owner)->post(route('parties.store'), ['name' => $name, 'is_active' => 1] + $kind);

        $save('Bilal', ['kind' => 'other'])->assertSessionHasErrors('kind_other');
        $save('Bilal', ['kind' => 'other', 'kind_other' => '  Electrician '])->assertSessionHasNoErrors();
        $this->assertSame('Electrician', Party::query()->where('name', 'Bilal')->sole()->typeLabel());

        $this->actingAs($owner)->get(route('parties.create'))->assertSee('value="custom:Electrician"', false)->assertSee('Other… (type a new one)');
        $this->actingAs($owner)->get(route('parties.index'))->assertSee('Electrician');

        // Picking the saved type, or typing it again in any case, reuses the same spelling.
        $save('Kashif', ['kind' => 'custom:Electrician'])->assertSessionHasNoErrors();
        $save('Nadeem', ['kind' => 'other', 'kind_other' => 'electrician'])->assertSessionHasNoErrors();
        $this->assertSame(['Electrician'], Party::customTypes());
        $this->assertSame(3, Party::query()->where('kind_other', 'Electrician')->count());
    }

    public function test_a_new_item_like_mitti_ka_tel_can_be_typed_in_on_the_diesel_tab(): void
    {
        $owner = User::factory()->owner()->create();
        $diesel = InventoryCategory::query()->where('name', 'Diesel')->sole();
        $supplier = Party::factory()->create();

        $this->actingAs($owner)->get(route('materials.purchase', $diesel))->assertOk()->assertSee('Other… (type a new one)');
        $buy = fn (string $item) => $this->actingAs($owner)->post(route('purchases.store'), [
            'purchased_on' => today()->toDateString(), 'party_id' => $supplier->id, 'material_category_id' => $diesel->id,
            'inventory_item_id' => OtherOption::Value, 'inventory_item_id_other' => $item, 'quantity' => 10, 'rate' => 300,
        ])->assertRedirect(route('inventory.index', ['tab' => 'material-'.$diesel->id]));

        $buy('Mitti ka tel');
        $buy('  mitti KA tel ');
        $mitti = $diesel->items()->where('name', 'Mitti ka tel')->sole();
        $this->assertSame('litre', $mitti->unit);
        $this->assertSame(2, Purchase::query()->where('inventory_item_id', $mitti->id)->count());

        $this->actingAs($owner)->get(route('materials.purchase', $diesel))->assertSee('Mitti ka tel (litre)');
        $this->actingAs($owner)->get(route('inventory.index', ['tab' => 'material-'.$diesel->id]))->assertSee('Mitti ka tel')->assertSee('Rs 3,000');
    }

    public function test_one_diesel_bill_holds_several_items_and_one_receipt_lists_them_all(): void
    {
        $owner = User::factory()->owner()->create();
        $diesel = InventoryCategory::query()->where('name', 'Diesel')->sole();
        $this->actingAs($owner)->get(route('materials.purchase', $diesel))->assertOk()->assertSee('id="addItem"', false);
        $dieselItem = $diesel->items()->sole();

        $this->actingAs($owner)->post(route('materials.purchase.store', $diesel), [
            'purchased_on' => today()->toDateString(), 'party_id' => OtherOption::Value, 'party_id_other' => 'Shell Ravi Road', 'bill_no' => 'B-77',
            'items' => [
                ['inventory_item_id' => $dieselItem->id, 'quantity' => 20, 'rate' => 280],
                ['inventory_item_id' => OtherOption::Value, 'item_other' => 'Mitti ka tel', 'quantity' => 5, 'rate' => 200],
                ['inventory_item_id' => '', 'quantity' => '', 'rate' => ''],
            ],
        ])->assertSessionHasNoErrors();

        $purchases = Purchase::query()->orderBy('id')->get();
        $this->assertCount(2, $purchases);
        $this->assertSame([5600, 1000], $purchases->pluck('amount')->all());
        $receiptNo = $purchases->first()->receipt_no;
        $this->assertNotNull($receiptNo);
        $this->assertSame($receiptNo, $purchases->last()->receipt_no);

        $this->actingAs($owner)->get(route('materials.receipt', $purchases->first()))->assertOk()
            ->assertSeeInOrder(['Shell Ravi Road', 'Diesel', 'Mitti ka tel', 'Rs 6,600']);
        // Both lines come off the manager's cash, and the tab's Receipt opens the combined receipt.
        $this->assertSame(6600, (int) Expense::query()->where('inventory_category_id', $diesel->id)->sum('amount'));
        $this->actingAs($owner)->get(route('inventory.index', ['tab' => 'material-'.$diesel->id]))->assertSee(route('materials.receipt', $purchases->first()), false);

        $this->actingAs($owner)->post(route('materials.purchase.store', $diesel), ['purchased_on' => today()->toDateString(), 'party_id' => $purchases->first()->party_id, 'items' => []])
            ->assertSessionHasErrors('items');
    }

    public function test_diesel_glue_ink_and_raddi_have_their_own_sidebar_entries_and_petrol_does_not(): void
    {
        $owner = User::factory()->owner()->create();
        $raddi = InventoryCategory::query()->where('name', 'Raddi')->sole();
        $petrol = InventoryCategory::query()->where('name', 'Petrol')->sole();

        $this->actingAs($owner)->get(route('dashboard'))->assertOk()
            ->assertSee(route('inventory.index', ['tab' => 'material-'.$raddi->id]), false)
            ->assertDontSee(route('inventory.index', ['tab' => 'material-'.$petrol->id]), false)
            ->assertSeeInOrder(['Diesel', 'Glue', 'Ink', 'Raddi']);

        // Raddi is sold: its tab has no Add item button, its button opens the sale form, and an expense cannot be filed under it.
        $this->actingAs($owner)->get(route('inventory.index', ['tab' => 'material-'.$raddi->id]))->assertOk()
            ->assertDontSee(route('inventory-items.create'), false)->assertSee(route('sales.create', $raddi), false);
        $this->actingAs($owner)->get(route('expenses.create', ['category' => $raddi->id]))->assertRedirect(route('sales.create', $raddi));
    }

    public function test_raddi_sales_are_money_in_kept_on_the_raddi_tab_only(): void
    {
        $owner = User::factory()->owner()->create();
        $raddi = InventoryCategory::query()->where('name', 'Raddi')->sole();
        Sale::factory()->create(['sold_on' => now()->subMonthNoOverflow()->startOfMonth()->addDays(3), 'total' => 7000]);

        $this->actingAs($owner)->get(route('sales.create', $raddi))->assertOk()->assertSee('Sell Raddi')->assertSee('Price per quantity');
        $this->actingAs($owner)->post(route('sales.store', $raddi), [
            'sold_on' => today()->toDateString(),
            'items' => [
                ['item' => 'Raddi', 'quantity' => 120, 'rate' => 50],
                ['item' => 'Cut pieces', 'quantity' => 10.5, 'rate' => 40],
                ['item' => '', 'quantity' => '', 'rate' => ''],
            ],
        ])->assertSessionHasNoErrors();

        $sale = Sale::query()->latest('id')->first();
        $this->assertSame(6420, $sale->total);
        $this->assertSame(['Raddi', 'Cut pieces'], $sale->items->pluck('description')->all());
        $this->assertSame(0, Expense::query()->count());

        $this->actingAs($owner)->get(route('sales.show', $sale))->assertOk()->assertSeeInOrder(['Raddi', '120', '50', '6,000', 'Cut pieces', '10.5', '40', '420', 'Rs 6,420']);
        $this->actingAs($owner)->get(route('inventory.index', ['tab' => 'material-'.$raddi->id]))->assertOk()
            ->assertViewHas('soldThisMonth', 6420)->assertViewHas('soldLastMonth', 7000)->assertSee('Cut pieces');

        $this->deleteRecord($owner, 'sale', $sale->id)->assertSessionHasNoErrors();
        $this->assertModelMissing($sale);
        $this->assertSame('Sale', Deletion::query()->sole()->kind);
    }

    public function test_diesel_expense_rows_take_quantity_and_price_per_quantity(): void
    {
        $owner = User::factory()->owner()->create();
        $diesel = InventoryCategory::query()->where('name', 'Diesel')->sole();

        $this->actingAs($owner)->get(route('expenses.create', ['category' => $diesel->id]))->assertOk()
            ->assertSee('Diesel expense')->assertSee('Price per quantity')->assertDontSee('Paid to');
        $this->actingAs($owner)->post(route('expenses.store'), [
            'spent_on' => today()->toDateString(), 'inventory_category_id' => $diesel->id,
            'items' => [['item' => 'Diesel', 'quantity' => 20, 'rate' => 280], ['item' => 'Mitti ka tel', 'quantity' => 5, 'rate' => 200], ['item' => '', 'quantity' => '', 'rate' => '']],
        ])->assertSessionHasNoErrors();

        $expense = Expense::query()->sole();
        $this->assertSame(6600, $expense->amount);
        $this->assertSame('Diesel', $expense->head->name);
        $this->assertSame([5600, 1000], $expense->items->pluck('amount')->all());
        $this->actingAs($owner)->get(route('expenses.show', $expense))->assertSeeInOrder(['Diesel', '20', '280', '5,600', 'Mitti ka tel', '5', '200', '1,000', 'Rs 6,600']);
        $this->actingAs($owner)->get(route('expenses.create', ['category' => $diesel->id]))->assertSee('<option value="Mitti ka tel">', false);

        // A row without its quantity is refused on these tabs.
        $this->actingAs($owner)->post(route('expenses.store'), ['spent_on' => today()->toDateString(), 'inventory_category_id' => $diesel->id, 'items' => [['item' => 'Diesel', 'rate' => 280]]])
            ->assertSessionHasErrors('items.0.quantity');
    }

    public function test_each_contractor_row_can_be_opened_and_edited(): void
    {
        $owner = User::factory()->owner()->create();
        $contractor = Contractor::factory()->create(['name' => 'Azeem Daftri']);

        $this->actingAs($owner)->get(route('contractors.index'))->assertOk()
            ->assertSee('data-href="'.route('contractors.show', $contractor).'"', false)
            ->assertSee('href="'.route('contractors.edit', $contractor).'"', false);
        $this->actingAs($owner)->get(route('contractors.edit', $contractor))->assertOk()->assertSee('Azeem Daftri');
    }

    public function test_a_typed_item_in_cash_out_becomes_its_head(): void
    {
        $owner = User::factory()->owner()->create();

        $this->actingAs($owner)->post(route('expenses.store'), [
            'spent_on' => today()->toDateString(),
            'items' => [['item' => 'Rickshaw fare', 'amount' => 200], ['item' => 'tea', 'amount' => 150], ['item' => '', 'amount' => '']],
        ])->assertSessionHasNoErrors();

        $expense = Expense::query()->sole();
        $this->assertSame(350, $expense->amount);
        $this->assertSame(['Rickshaw fare', 'tea'], $expense->items->pluck('description')->all());
        $this->assertNotNull(ExpenseHead::query()->where('name', 'Rickshaw fare')->first());
        $this->actingAs($owner)->get(route('expenses.create'))->assertSee('<option value="Rickshaw fare">', false);
    }
}
