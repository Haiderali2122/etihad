<?php

namespace Tests\Feature;

use App\Enums\PayrollStatus;
use App\Enums\WorkOrderStage;
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
use App\Models\User;
use App\Models\WorkOrder;
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

        $this->actingAs($owner)->get(route('expenses.create'))->assertOk()->assertDontSee('What for')->assertSee('Paid to (optional)');
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

    public function test_petrol_diesel_glue_and_ink_have_their_own_sidebar_entries(): void
    {
        $owner = User::factory()->owner()->create();
        $petrol = InventoryCategory::query()->where('name', 'Petrol')->sole();

        $this->actingAs($owner)->get(route('dashboard'))->assertOk()
            ->assertSee(route('inventory.index', ['tab' => 'material-'.$petrol->id]), false)
            ->assertSeeInOrder(['Diesel', 'Glue', 'Ink', 'Petrol']);
    }
}
