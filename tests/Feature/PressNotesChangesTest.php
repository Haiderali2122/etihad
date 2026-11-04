<?php

namespace Tests\Feature;

use App\Enums\AdvanceKind;
use App\Enums\ApprovalStatus;
use App\Enums\PartyKind;
use App\Enums\WorkOrderStage;
use App\Http\Controllers\ApprovalController;
use App\Models\BookFormat;
use App\Models\CashFloat;
use App\Models\Contractor;
use App\Models\ContractorJob;
use App\Models\ContractorPayment;
use App\Models\Dispatch;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\ExpenseHead;
use App\Models\ForemanSheet;
use App\Models\Godown;
use App\Models\InventoryCategory;
use App\Models\InventoryIssue;
use App\Models\InventoryItem;
use App\Models\Loan;
use App\Models\LoanRepayment;
use App\Models\MachineRepair;
use App\Models\PaperIssue;
use App\Models\PaperReceipt;
use App\Models\PaperType;
use App\Models\Party;
use App\Models\PayrollRun;
use App\Models\PlateJob;
use App\Models\Purchase;
use App\Models\RimLoading;
use App\Models\SalaryAdvance;
use App\Models\User;
use App\Models\WorkOrder;
use App\Support\OtherOption;
use App\Support\PaperStock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The changes asked for in the 4 November 2026 press notes.
 */
class PressNotesChangesTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->owner()->create();
        $this->manager = User::factory()->create();
    }

    // 1 & 6: "Other…" on dropdowns

    public function test_other_godown_is_typed_in_saved_to_the_list_and_not_duplicated(): void
    {
        $type = PaperType::factory()->create();
        $party = Party::factory()->kind(PartyKind::PaperParty)->create();
        $slip = fn (string $no, string $godown) => [
            'slip_no' => $no, 'received_at' => now()->format('Y-m-d H:i'), 'party_id' => $party->id, 'paper_type_id' => $type->id,
            'quantity' => 100, 'godown_id' => OtherOption::Value, 'godown_id_other' => $godown, 'received_by' => 'Faisal',
        ];

        $this->actingAs($this->owner)->get(route('paper-receipts.create'))->assertOk()->assertSee('Other… (type a new one)');
        $this->actingAs($this->owner)->post(route('paper-receipts.store'), $slip('901', 'Back  Yard'))->assertSessionHasNoErrors();
        $this->actingAs($this->owner)->post(route('paper-receipts.store'), $slip('902', 'back yard'))->assertSessionHasNoErrors();

        $godown = Godown::query()->sole();
        $this->assertSame('Back Yard', $godown->name);
        $this->assertSame(2, PaperReceipt::query()->where('godown_id', $godown->id)->count());

        // Picking Other but typing nothing is the usual "required" error.
        $this->actingAs($this->owner)->post(route('paper-receipts.store'), $slip('903', ''))->assertSessionHasErrors('godown_id');
    }

    public function test_other_designation_and_expense_head_are_added_from_their_forms(): void
    {
        $this->actingAs($this->owner)->post(route('employees.store'), [
            'code' => 'E-77', 'name' => 'Naeem', 'designation' => OtherOption::Value, 'designation_other' => 'Cutter man', 'monthly_salary' => 30000, 'is_active' => 1,
        ])->assertSessionHasNoErrors();
        $this->assertSame('Cutter man', Employee::query()->sole()->designation);

        $this->actingAs($this->owner)->post(route('expenses.store'), [
            'spent_on' => today()->toDateString(), 'expense_head_id' => OtherOption::Value, 'expense_head_id_other' => 'Courier', 'paid_to' => 'TCS',
            'items' => [['description' => 'Parcel to Multan', 'amount' => 450]],
        ])->assertSessionHasNoErrors();
        $this->assertSame('Courier', Expense::query()->sole()->head->name);
    }

    // 2: Edit instead of delete on truck receipts

    public function test_truck_receipt_is_edited_and_a_manager_edit_waits_for_the_owner(): void
    {
        $receipt = PaperReceipt::factory()->create(['slip_no' => '501', 'quantity' => 300, 'created_by' => $this->owner->id]);
        $payload = [
            'slip_no' => '501', 'received_at' => now()->format('Y-m-d H:i'), 'party_id' => $receipt->party_id, 'paper_type_id' => $receipt->paper_type_id,
            'quantity' => 280, 'godown_id' => $receipt->godown_id, 'received_by' => 'Faisal',
        ];

        $this->actingAs($this->manager)->get(route('paper-receipts.show', $receipt))->assertSee(route('paper-receipts.edit', $receipt), false);
        $this->actingAs($this->manager)->put(route('paper-receipts.update', $receipt), $payload)->assertRedirect(route('paper-receipts.show', $receipt));

        $receipt->refresh();
        $this->assertSame(280, $receipt->quantity);
        $this->assertSame(ApprovalStatus::Pending, $receipt->status);
    }

    public function test_a_receipt_cannot_drop_below_the_paper_already_issued_from_it(): void
    {
        $receipt = PaperReceipt::factory()->create(['quantity' => 300, 'created_by' => $this->owner->id]);
        PaperIssue::factory()->create(['paper_type_id' => $receipt->paper_type_id, 'party_id' => $receipt->party_id, 'quantity' => 250, 'created_by' => $this->owner->id]);

        $this->actingAs($this->owner)->put(route('paper-receipts.update', $receipt), [
            'slip_no' => $receipt->slip_no, 'received_at' => now()->format('Y-m-d H:i'), 'party_id' => $receipt->party_id, 'paper_type_id' => $receipt->paper_type_id,
            'quantity' => 200, 'godown_id' => $receipt->godown_id, 'received_by' => 'Faisal',
        ])->assertSessionHasErrors('quantity');

        $this->assertSame(300, $receipt->refresh()->quantity);
        $this->assertSame(50, app(PaperStock::class)->available($receipt->paper_type_id, $receipt->party_id));
    }

    // 3, 4, 7: Kharadia

    public function test_machine_parts_go_to_a_kharadia_and_repairs_name_a_kharadia(): void
    {
        $parts = InventoryCategory::query()->firstOrCreate(['name' => 'Machine parts']);
        $parts->update(['for_machines' => true, 'tracks_stock' => true]);
        $bearing = InventoryItem::factory()->create(['inventory_category_id' => $parts->id, 'unit' => 'pcs']);
        Purchase::factory()->create(['inventory_item_id' => $bearing->id, 'quantity' => 4, 'created_by' => $this->owner->id]);
        $kharadia = Party::factory()->kind(PartyKind::Kharadia)->create(['name' => 'Ustad Rafiq']);
        $employee = Employee::factory()->create();
        $base = ['issued_on' => today()->toDateString(), 'inventory_item_id' => $bearing->id, 'quantity' => 1];

        $this->actingAs($this->owner)->post(route('inventory-issues.store'), $base + ['employee_id' => $employee->id])->assertSessionHasErrors('party_id');
        $this->actingAs($this->owner)->post(route('inventory-issues.store'), $base + ['party_id' => $kharadia->id])->assertSessionHasNoErrors();

        $issue = InventoryIssue::query()->sole();
        $this->assertSame($kharadia->id, $issue->party_id);
        $this->assertNull($issue->employee_id);
        $this->assertSame('Ustad Rafiq', $issue->issued_to);

        $this->actingAs($this->owner)->get(route('machine-repairs.create'))->assertSee('Ustad Rafiq')->assertSee('Kharadia');
    }

    // 5: Petrol, diesel, glue and ink tabs with payments

    public function test_material_tabs_record_payments_that_come_off_the_managers_cash(): void
    {
        $diesel = InventoryCategory::query()->where('name', 'Diesel')->sole();
        $this->assertTrue($diesel->show_as_tab);
        CashFloat::factory()->create(['user_id' => $this->manager->id, 'amount' => 50000]);

        $this->actingAs($this->owner)->get(route('inventory.index'))->assertSee('Petrol')->assertSee('Diesel')->assertSee('Glue')->assertSee('Ink');
        $this->actingAs($this->manager)->get(route('expenses.create', ['category' => $diesel->id]))->assertOk()->assertSee('Payment for Diesel');

        $this->actingAs($this->manager)->post(route('expenses.store'), [
            'spent_on' => today()->toDateString(), 'expense_head_id' => ExpenseHead::query()->where('name', 'Diesel')->value('id'),
            'inventory_category_id' => $diesel->id, 'paid_to' => 'PSO Ravi Road', 'items' => [['description' => 'Generator, 40 litres', 'amount' => 12000]],
        ])->assertSessionHasNoErrors();

        $this->actingAs($this->owner)->get(route('inventory.index', ['tab' => 'material-'.$diesel->id]))->assertOk()
            ->assertSee('PSO Ravi Road')->assertSee('Rs 12,000');
        $this->assertSame(38000, $this->manager->cashInHand());
    }

    // 8: Rim loading to folding (Younus ka khata) and the truck loading with photos

    public function test_rim_loading_is_paid_from_the_ledger_and_shows_in_cash_out(): void
    {
        $order = WorkOrder::factory()->create(['stage' => WorkOrderStage::ToFolding, 'paper_issue_id' => PaperIssue::factory()->create()->id]);

        $this->actingAs($this->owner)->post(route('rim-loadings.store', $order), ['loaded_on' => today()->toDateString(), 'rims' => 120, 'rate_per_rim' => 6])
            ->assertSessionHasNoErrors();
        $loading = RimLoading::query()->sole();
        $this->assertSame(720, $loading->amount);
        $this->assertSame('Younus', $loading->loaded_by);
        $this->assertNull($loading->cashOutExpense);

        $this->actingAs($this->owner)->get(route('rim-loadings.index'))->assertOk()->assertSee($order->number)->assertSee('Rs 720');
        $this->actingAs($this->owner)->patch(route('rim-loadings.pay', $loading), ['paid_on' => today()->toDateString()])->assertSessionHasNoErrors();

        $expense = $loading->refresh()->cashOutExpense;
        $this->assertSame(720, $expense->amount);
        $this->assertSame('Loading labour', $expense->head->name);
        $this->actingAs($this->owner)->delete(route('rim-loadings.destroy', $loading))->assertStatus(409);
    }

    public function test_rims_cannot_be_loaded_while_the_order_is_still_printing(): void
    {
        $order = WorkOrder::factory()->create(['stage' => WorkOrderStage::Printing, 'paper_issue_id' => PaperIssue::factory()->create()->id]);

        $this->actingAs($this->owner)->post(route('rim-loadings.store', $order), ['loaded_on' => today()->toDateString(), 'rims' => 10, 'rate_per_rim' => 5])->assertStatus(409);
    }

    public function test_truck_loading_keeps_photos_and_files_and_can_be_edited(): void
    {
        Storage::fake('public');
        $buyer = Party::factory()->kind(PartyKind::Buyer)->create();
        $format = BookFormat::factory()->create();
        $payload = [
            'dispatched_on' => today()->toDateString(), 'buyer_id' => $buyer->id, 'book_format_id' => $format->id,
            'bundles' => 10, 'copies_per_bundle' => 50, 'labour_per_bundle' => 4,
        ];

        $this->actingAs($this->owner)->post(route('dispatches.store'), $payload + [
            'truck_front' => UploadedFile::fake()->image('front.jpg'), 'truck_back' => UploadedFile::fake()->image('back.jpg'),
            'files' => [UploadedFile::fake()->create('bilty.pdf', 20, 'application/pdf')],
        ])->assertSessionHasNoErrors();

        $dispatch = Dispatch::query()->sole();
        Storage::disk('public')->assertExists([$dispatch->truck_front, $dispatch->truck_back, $dispatch->files[0]]);
        $this->assertSame(40, $dispatch->labour_amount);
        $this->actingAs($this->owner)->get(route('dispatches.show', $dispatch))->assertOk()->assertSee('Truck, front')->assertSee(route('dispatches.edit', $dispatch), false);

        $front = $dispatch->truck_front;
        $this->actingAs($this->owner)->put(route('dispatches.update', $dispatch), ['bundles' => 12] + $payload)->assertRedirect(route('dispatches.show', $dispatch));
        $dispatch->refresh();
        $this->assertSame(600, $dispatch->copies);
        $this->assertSame($front, $dispatch->truck_front, 'An empty photo field keeps the photo already attached.');
        $this->assertSame(48, $dispatch->cashOutExpense->amount);
    }

    // 10 & 11: Processors and plates, one shared payment record

    public function test_processor_page_shows_the_same_plate_records_and_payment_counts_once(): void
    {
        $ali = Party::factory()->kind(PartyKind::PlateProcessor)->create(['name' => 'Ali Processor']);
        $job = PlateJob::factory()->create(['party_id' => $ali->id, 'plates' => 12, 'rate' => 125, 'amount' => 1500, 'created_by' => $this->owner->id]);

        $this->actingAs($this->owner)->patch(route('plate-jobs.return', $job), ['returned_plates' => 9, 'returned_on' => today()->toDateString()])->assertSessionHasNoErrors();
        $this->actingAs($this->owner)->patch(route('plate-jobs.pay', $job), ['paid_on' => today()->toDateString()])->assertSessionHasNoErrors();

        $this->assertSame(3, $job->refresh()->platesHeld());
        $this->assertSame(1, Expense::query()->where('source_type', $job->getMorphClass())->count());
        $this->actingAs($this->owner)->get(route('processors.index'))->assertOk()->assertSee('Ali Processor')->assertSee('Rs 1,500');
        $this->actingAs($this->owner)->get(route('processors.show', $ali))->assertOk()->assertSee('Paid');

        // Editing the payment from either tab changes the one record.
        $this->actingAs($this->owner)->put(route('plate-jobs.update', $job), [
            'party_id' => $ali->id, 'job_on' => today()->toDateString(), 'plates' => 12, 'rate' => 150, 'returned_plates' => 9, 'paid_on' => today()->toDateString(),
        ])->assertSessionHasNoErrors();
        $this->assertSame(1800, $job->refresh()->amount);
        $this->assertSame(1800, Expense::query()->where('source_type', $job->getMorphClass())->sole()->amount);

        $this->actingAs($this->owner)->delete(route('processors.destroy', $ali))->assertSessionHasErrors('processor');
        $this->assertModelExists($ali);
    }

    public function test_processors_are_added_and_deleted_while_unused(): void
    {
        $this->actingAs($this->owner)->post(route('processors.store'), ['name' => 'Bilal Plates', 'is_active' => 1])->assertSessionHasNoErrors();
        $processor = Party::query()->where('name', 'Bilal Plates')->sole();
        $this->assertSame(PartyKind::PlateProcessor, $processor->kind);

        $this->actingAs($this->owner)->delete(route('processors.destroy', $processor))->assertRedirect(route('processors.index'));
        $this->assertModelMissing($processor);
    }

    // 14: Every payment shows in Cash-out

    public function test_payments_on_other_tabs_mirror_into_cash_out_and_follow_approval(): void
    {
        CashFloat::factory()->create(['user_id' => $this->manager->id, 'amount' => 100000]);
        $purchase = Purchase::factory()->create(['amount' => 18500, 'created_by' => $this->manager->id]);
        $repair = MachineRepair::factory()->create(['amount_paid' => 4000, 'created_by' => $this->manager->id]);
        $advance = SalaryAdvance::factory()->create(['amount' => 3000, 'created_by' => $this->manager->id]);

        $this->assertSame(18500 + 4000 + 3000, 100000 - $this->manager->cashInHand());
        $this->assertSame(ApprovalStatus::Pending, $purchase->cashOutExpense->status);
        $this->assertSame(0, app(ApprovalController::class)->pendingItems()->where('type', 'expense')->count(), 'Mirrored expenses are decided with their payment.');

        $purchase->reject($this->owner, 'Wrong bill');
        $this->assertSame(ApprovalStatus::Rejected, $purchase->cashOutExpense()->sole()->status);
        $this->assertSame(4000 + 3000, 100000 - $this->manager->cashInHand());

        $repair->delete();
        $this->assertSame(0, Expense::query()->where('source_type', $repair->getMorphClass())->count());
        $this->actingAs($this->owner)->get(route('expenses.edit', $advance->cashOutExpense))->assertStatus(409);
    }

    public function test_finalized_payroll_shows_the_salaries_in_cash_out_until_reopened(): void
    {
        Employee::factory()->create(['monthly_salary' => 30000]);
        $this->actingAs($this->owner)->post(route('payroll.store'), ['month' => '2026-09']);
        $run = PayrollRun::query()->sole();

        $this->actingAs($this->owner)->post(route('payroll.finalize', $run));
        $this->assertSame((int) $run->lines()->sum('net_pay'), $run->refresh()->cashOutExpense->amount);

        $this->actingAs($this->owner)->post(route('payroll.reopen', $run));
        $this->assertNull($run->refresh()->cashOutExpense);
    }

    // 15: Several items in one expense, with a receipt

    public function test_expense_with_several_items_totals_them_and_prints_a_receipt(): void
    {
        $head = ExpenseHead::factory()->create();

        $this->actingAs($this->owner)->post(route('expenses.store'), [
            'spent_on' => today()->toDateString(), 'expense_head_id' => $head->id, 'paid_to' => 'Bazaar',
            'items' => [['description' => 'Tea', 'amount' => 300], ['description' => '', 'amount' => ''], ['description' => 'Rope', 'amount' => 450]],
        ])->assertSessionHasNoErrors();

        $expense = Expense::query()->sole();
        $this->assertSame(750, $expense->amount);
        $this->assertSame(['Tea', 'Rope'], $expense->items->pluck('description')->all());
        $this->actingAs($this->owner)->get(route('expenses.show', $expense))->assertOk()
            ->assertSee($expense->receiptNumber())->assertSee('Rope')->assertSee('Rs 750');
    }

    // 16: Folding & binding payments and jobs can be recorded and changed

    public function test_contractor_payments_and_jobs_are_edited_and_deleted(): void
    {
        $contractor = Contractor::factory()->create();
        $payment = ContractorPayment::factory()->create(['contractor_id' => $contractor->id, 'amount' => 5000, 'created_by' => $this->owner->id]);
        $job = ContractorJob::factory()->create(['contractor_id' => $contractor->id, 'forms' => 10, 'copies' => 12000, 'folding_rate' => 35, 'amount' => 4200]);

        $this->actingAs($this->owner)->get(route('contractors.index'))->assertSee(route('contractors.show', ['contractor' => $contractor, 'kind' => 'payment']), false);

        $this->actingAs($this->manager)->put(route('contractor-payments.update', $payment), ['paid_on' => today()->toDateString(), 'amount' => 6000, 'kind' => 'payment'])
            ->assertRedirect(route('contractors.show', $contractor));
        $this->assertSame(ApprovalStatus::Pending, $payment->refresh()->status);
        $this->assertSame(6000, $payment->cashOutExpense->amount);

        $this->actingAs($this->owner)->put(route('contractor-jobs.update', $job), ['job_on' => today()->toDateString(), 'forms' => 10, 'copies' => 10000, 'folding_rate' => 35])
            ->assertSessionHasNoErrors();
        $this->assertSame(3500, $job->refresh()->amount);

        $this->actingAs($this->owner)->delete(route('contractor-payments.destroy', $payment));
        $this->actingAs($this->owner)->delete(route('contractor-jobs.destroy', $job));
        $this->assertModelMissing($payment);
        $this->assertModelMissing($job);
    }

    // Edit buttons on slips

    public function test_advance_loan_and_foreman_sheet_can_be_edited_until_payroll_or_repayments_use_them(): void
    {
        $employee = Employee::factory()->create();
        $advance = SalaryAdvance::factory()->create(['employee_id' => $employee->id, 'amount' => 2000, 'kind' => AdvanceKind::AdHoc, 'created_by' => $this->owner->id]);
        $this->actingAs($this->manager)->put(route('salary-advances.update', $advance), ['employee_id' => $employee->id, 'given_on' => today()->toDateString(), 'amount' => 2500])
            ->assertRedirect(route('salary-advances.show', $advance));
        $this->assertSame(2500, $advance->refresh()->amount);
        $this->assertSame(ApprovalStatus::Pending, $advance->status);

        $advance->forceFill(['payroll_run_id' => PayrollRun::factory()->create()->id])->save();
        $this->actingAs($this->owner)->get(route('salary-advances.edit', $advance))->assertRedirect(route('salary-advances.show', $advance))->assertSessionHasErrors('advance');

        $loan = Loan::factory()->create(['employee_id' => $employee->id, 'created_by' => $this->owner->id]);
        $this->actingAs($this->owner)->get(route('loans.edit', $loan))->assertOk();
        LoanRepayment::factory()->create(['loan_id' => $loan->id, 'amount' => 100]);
        $this->actingAs($this->owner)->get(route('loans.edit', $loan))->assertRedirect(route('loans.show', $loan));

        $foreman = Employee::factory()->foreman()->create();
        $worker = Employee::factory()->create(['foreman_id' => $foreman->id]);
        $this->actingAs($this->owner)->post(route('foreman-sheets.store'), [
            'foreman_id' => $foreman->id, 'given_on' => today()->toDateString(), 'amount' => 5000, 'lines' => [['employee_id' => $worker->id, 'amount' => 3000]],
        ])->assertSessionHasNoErrors();
        $sheet = ForemanSheet::query()->sole();
        $this->actingAs($this->owner)->put(route('foreman-sheets.update', $sheet), [
            'foreman_id' => $foreman->id, 'given_on' => today()->toDateString(), 'amount' => 6000, 'lines' => [['employee_id' => $worker->id, 'amount' => 4000]],
        ])->assertRedirect(route('foreman-sheets.show', $sheet));
        $this->assertSame(4000, (int) $sheet->advances()->where('employee_id', $worker->id)->sum('amount'));
        $this->assertSame(6000, (int) $sheet->advances()->sum('amount'));
    }

    public function test_every_new_and_changed_form_opens(): void
    {
        $contractor = Contractor::factory()->create();
        $job = ContractorJob::factory()->create(['contractor_id' => $contractor->id]);
        $payment = ContractorPayment::factory()->create(['contractor_id' => $contractor->id, 'created_by' => $this->owner->id]);
        $plate = PlateJob::factory()->create();
        $advance = SalaryAdvance::factory()->create(['kind' => AdvanceKind::AdHoc, 'created_by' => $this->owner->id]);
        $receipt = PaperReceipt::factory()->create(['created_by' => $this->owner->id]);
        $dispatch = Dispatch::factory()->create();
        $category = InventoryCategory::query()->where('name', 'Ink')->sole();

        $this->actingAs($this->owner);
        foreach ([
            route('contractor-jobs.edit', $job), route('contractor-payments.edit', $payment), route('plate-jobs.edit', $plate),
            route('plate-jobs.create', ['processor' => $plate->party_id]), route('salary-advances.edit', $advance), route('paper-receipts.edit', $receipt),
            route('dispatches.create'), route('dispatches.edit', $dispatch), route('inventory-issues.create'), route('inventory-categories.edit', $category),
            route('expenses.create'), route('processors.create'), route('processors.edit', $plate->party_id), route('rim-loadings.index', ['show' => 'all']),
            route('machine-repairs.create'), route('inventory-items.create'), route('employees.create'),
        ] as $url) {
            $this->get($url)->assertOk();
        }

        // The same screens in Urdu.
        $this->owner->update(['locale' => 'ur']);
        $this->get(route('processors.index'))->assertOk()->assertSee('پروسیسرز');
        $this->get(route('rim-loadings.index'))->assertOk()->assertSee('یونس کا کھاتہ');
    }

    // 9: Binding rate no longer asked for on books

    public function test_book_form_no_longer_asks_for_a_binding_rate(): void
    {
        $this->actingAs($this->owner)->get(route('books.create'))->assertOk()->assertSee('Folding rate')->assertDontSee('Binding rate');
    }
}
