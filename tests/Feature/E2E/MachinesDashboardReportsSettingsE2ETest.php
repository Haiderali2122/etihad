<?php

namespace Tests\Feature\E2E;

use App\Enums\ApprovalStatus;
use App\Enums\MachineStatus;
use App\Enums\PaperIssueKind;
use App\Enums\PartyKind;
use App\Enums\RepairKind;
use App\Models\CashFloat;
use App\Models\Expense;
use App\Models\ExpenseHead;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\Machine;
use App\Models\MachineRepair;
use App\Models\PaperIssue;
use App\Models\PaperReceipt;
use App\Models\PaperType;
use App\Models\Party;
use App\Models\PayrollRun;
use App\Models\Purchase;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Contracts\Routing\UrlRoutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Route;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class MachinesDashboardReportsSettingsE2ETest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-10-27 10:00:00'));
    }

    private function owner(): User
    {
        return User::factory()->owner()->create();
    }

    private function manager(): User
    {
        return User::factory()->create();
    }

    /**
     * @param  list<string>  $permissions
     */
    private function userWith(array $permissions): User
    {
        return User::factory()->create(['role_id' => Role::factory()->create(['permissions' => $permissions])->id]);
    }

    /**
     * @return list<array<int, string>>
     */
    private function csvRows(TestResponse $response): array
    {
        $content = ltrim($response->streamedContent(), "\xEF\xBB\xBF");

        return collect(preg_split('/\r?\n/', trim($content)))
            ->filter()
            ->map(fn (string $line) => str_getcsv($line))
            ->values()
            ->all();
    }

    // ------------------------------------------------------------------
    // Machines
    // ------------------------------------------------------------------

    public function test_owner_can_browse_every_machine_screen(): void
    {
        $owner = $this->owner();
        $machine = Machine::factory()->create(['code' => 'P-01', 'name' => 'Heidelberg', 'model' => 'GTO 52', 'purchase_price' => 2500000, 'notes' => 'Bought used']);
        MachineRepair::factory()->for($machine)->create(['status' => ApprovalStatus::Approved, 'description' => 'Chain replaced']);

        $this->actingAs($owner)->get(route('machines.index'))->assertOk()->assertSee('P-01')->assertSee('Chain replaced');
        $this->actingAs($owner)->get(route('machines.show', $machine))->assertOk()->assertSee('GTO 52')->assertSee('Bought used')->assertSee('Chain replaced');
        $this->actingAs($owner)->get(route('machines.create'))->assertOk();
        $this->actingAs($owner)->get(route('machines.edit', $machine))->assertOk()->assertSee('Heidelberg');
        $this->actingAs($owner)->get(route('machine-repairs.create'))->assertOk();
        $this->actingAs($owner)->get(route('machine-repairs.create', ['machine' => $machine->id]))->assertOk();
    }

    public function test_machine_pages_render_with_no_machines(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)->get(route('machines.index'))->assertOk()->assertSee('No machines yet');
        $this->actingAs($owner)->get(route('machine-repairs.create'))->assertOk();
    }

    public function test_machine_show_with_no_repairs_or_purchase_price(): void
    {
        $machine = Machine::factory()->create(['purchase_price' => null]);

        $this->actingAs($this->owner())->get(route('machines.show', $machine))->assertOk()->assertSee('No repairs logged');
    }

    public function test_owner_adds_and_edits_a_machine(): void
    {
        $owner = $this->owner();

        $response = $this->actingAs($owner)->post(route('machines.store'), [
            'code' => 'B-02', 'name' => 'Stitcher', 'type' => 'binding', 'status' => 'running', 'purchase_price' => 150000,
        ]);
        $machine = Machine::query()->where('code', 'B-02')->sole();
        $response->assertRedirect(route('machines.show', $machine));

        $this->actingAs($owner)->put(route('machines.update', $machine), [
            'code' => 'B-02', 'name' => 'Saddle stitcher', 'type' => 'binding', 'status' => 'under_repair', 'purchase_price' => '',
        ])->assertRedirect(route('machines.show', $machine))->assertSessionHasNoErrors();

        $machine->refresh();
        $this->assertSame('Saddle stitcher', $machine->name);
        $this->assertSame(MachineStatus::UnderRepair, $machine->status);
        $this->assertNull($machine->purchase_price);
    }

    public function test_machine_validation(): void
    {
        $owner = $this->owner();
        Machine::factory()->create(['code' => 'P-01']);

        $this->actingAs($owner)->post(route('machines.store'), [])
            ->assertSessionHasErrors(['code', 'name', 'type', 'status']);

        $this->actingAs($owner)->post(route('machines.store'), [
            'code' => 'P-01', 'name' => 'Dup', 'type' => 'laser', 'status' => 'broken', 'purchase_price' => -5,
            'model' => str_repeat('x', 121),
        ])->assertSessionHasErrors(['code', 'type', 'status', 'purchase_price', 'model']);

        $this->actingAs($owner)->post(route('machines.store'), [
            'code' => str_repeat('A', 21), 'name' => 'Too long code', 'type' => 'printing', 'status' => 'idle',
        ])->assertSessionHasErrors(['code']);

        $this->assertSame(1, Machine::query()->count());
    }

    public function test_editing_a_machine_to_another_machines_code_is_rejected(): void
    {
        $owner = $this->owner();
        Machine::factory()->create(['code' => 'P-01']);
        $second = Machine::factory()->create(['code' => 'P-02']);

        $this->actingAs($owner)->put(route('machines.update', $second), [
            'code' => 'P-01', 'name' => 'x', 'type' => 'printing', 'status' => 'running',
        ])->assertSessionHasErrors('code');

        $this->assertSame('P-02', $second->fresh()->code);
    }

    public function test_owner_logs_a_repair_which_is_approved_immediately(): void
    {
        Storage::fake('public');
        $owner = $this->owner();
        $machine = Machine::factory()->create();
        $mechanic = Party::factory()->kind(PartyKind::Mechanic)->create();

        $this->actingAs($owner)->post(route('machine-repairs.store'), [
            'repaired_on' => '2026-10-20', 'machine_id' => $machine->id, 'party_id' => $mechanic->id,
            'kind' => RepairKind::NewPart->value, 'description' => 'New roller', 'bill_amount' => 12000,
            'discount' => 500, 'amount_paid' => 11800, 'invoice_no' => 'INV-9',
            'attachment' => UploadedFile::fake()->image('bill.jpg'),
        ])->assertRedirect(route('machines.show', $machine))->assertSessionHas('status', 'Repair saved.');

        $repair = MachineRepair::query()->sole();
        $this->assertSame(ApprovalStatus::Approved, $repair->status);
        $this->assertSame($owner->id, $repair->reviewed_by);
        $this->assertSame(500, $repair->discount);
        Storage::disk('public')->assertExists($repair->attachment);

        $this->actingAs($owner)->get(route('machines.show', $machine))->assertOk()
            ->assertSee('Rs 11,800')
            ->assertSee('Invoice INV-9');
    }

    public function test_manager_repair_waits_for_approval_and_shows_on_dashboard(): void
    {
        $owner = $this->owner();
        $manager = $this->manager();
        $machine = Machine::factory()->create(['code' => 'P-07']);
        $mechanic = Party::factory()->kind(PartyKind::Mechanic)->create();

        $this->actingAs($manager)->post(route('machine-repairs.store'), [
            'repaired_on' => '2026-10-27', 'machine_id' => $machine->id, 'party_id' => $mechanic->id,
            'kind' => 'repair', 'description' => 'Gear fixed', 'bill_amount' => 3000, 'amount_paid' => 3000,
        ])->assertRedirect(route('machines.show', $machine))
            ->assertSessionHas('status', 'Repair saved and sent for approval.');

        $repair = MachineRepair::query()->sole();
        $this->assertSame(ApprovalStatus::Pending, $repair->status);
        $this->assertSame(0, $repair->discount, 'Missing discount must default to 0');

        $this->actingAs($owner)->get(route('dashboard'))->assertOk()
            ->assertViewHas('pending', fn ($pending) => $pending->count() === 1)
            ->assertSee('P-07: Gear fixed');

        $this->actingAs($owner)->patch(route('approvals.update', ['type' => 'repair', 'id' => $repair->id]), ['decision' => 'approve'])
            ->assertSessionHasNoErrors();
        $this->assertSame(ApprovalStatus::Approved, $repair->fresh()->status);
    }

    public function test_repair_validation(): void
    {
        $owner = $this->owner();
        $machine = Machine::factory()->create();
        $mechanic = Party::factory()->kind(PartyKind::Mechanic)->create();

        $this->actingAs($owner)->post(route('machine-repairs.store'), [])
            ->assertSessionHasErrors(['repaired_on', 'machine_id', 'party_id', 'kind', 'description', 'bill_amount', 'amount_paid']);

        $this->actingAs($owner)->post(route('machine-repairs.store'), [
            'repaired_on' => 'not-a-date', 'machine_id' => 999, 'party_id' => $mechanic->id, 'part_id' => 999,
            'kind' => 'service', 'description' => 'x', 'bill_amount' => 1000, 'discount' => 1001, 'amount_paid' => -1,
        ])->assertSessionHasErrors(['repaired_on', 'machine_id', 'part_id', 'kind', 'discount', 'amount_paid']);

        $this->actingAs($owner)->post(route('machine-repairs.store'), [
            'repaired_on' => '2026-10-27', 'machine_id' => $machine->id, 'party_id' => $mechanic->id,
            'kind' => 'repair', 'description' => 'x', 'bill_amount' => 100, 'amount_paid' => 100,
            'attachment' => UploadedFile::fake()->create('virus.exe', 10),
        ])->assertSessionHasErrors(['attachment']);

        $this->assertSame(0, MachineRepair::query()->count());
    }

    public function test_machine_index_spend_columns(): void
    {
        $owner = $this->owner();
        $machine = Machine::factory()->create();
        MachineRepair::factory()->for($machine)->create(['repaired_on' => '2026-10-01', 'amount_paid' => 1000, 'status' => ApprovalStatus::Approved]);
        MachineRepair::factory()->for($machine)->create(['repaired_on' => '2026-10-26', 'amount_paid' => 2000, 'status' => ApprovalStatus::Pending]);
        MachineRepair::factory()->for($machine)->create(['repaired_on' => '2026-10-26', 'amount_paid' => 4000, 'status' => ApprovalStatus::Rejected]);
        MachineRepair::factory()->for($machine)->create(['repaired_on' => '2026-09-30', 'amount_paid' => 8000, 'status' => ApprovalStatus::Approved]);

        $this->actingAs($owner)->get(route('machines.index'))->assertOk()
            ->assertViewHas('machines', function ($machines) {
                $m = $machines->sole();

                return (int) $m->month_spend === 3000 && (int) $m->total_spend === 11000;
            });

        // Show page KPI counts approved bills only.
        $this->actingAs($owner)->get(route('machines.show', $machine))->assertOk()->assertSee('Rs 9,000');
    }

    public function test_last_repair_on_index_is_per_machine_latest(): void
    {
        $owner = $this->owner();
        $a = Machine::factory()->create(['code' => 'A-1']);
        $b = Machine::factory()->create(['code' => 'B-1']);
        MachineRepair::factory()->for($a)->create(['repaired_on' => '2026-10-01', 'description' => 'A old']);
        MachineRepair::factory()->for($a)->create(['repaired_on' => '2026-10-20', 'description' => 'A new']);
        MachineRepair::factory()->for($b)->create(['repaired_on' => '2026-10-05', 'description' => 'B only']);

        $this->actingAs($owner)->get(route('machines.index'))->assertOk()
            ->assertSee('A new')->assertSee('B only')->assertDontSee('A old');
    }

    public function test_machine_permissions(): void
    {
        $machine = Machine::factory()->create();
        $nobody = $this->userWith([]);
        $viewer = $this->userWith(['machines.view']);
        $repairer = $this->userWith(['machines.repair']);
        $payload = ['code' => 'Z-1', 'name' => 'x', 'type' => 'printing', 'status' => 'running'];

        $this->actingAs($nobody)->get(route('machines.index'))->assertForbidden();
        $this->actingAs($nobody)->get(route('machines.show', $machine))->assertForbidden();

        $this->actingAs($viewer)->get(route('machines.index'))->assertOk()->assertDontSee(route('machines.create'))->assertDontSee(route('machine-repairs.create'));
        $this->actingAs($viewer)->get(route('machines.show', $machine))->assertOk()->assertDontSee(route('machines.edit', $machine));
        $this->actingAs($viewer)->get(route('machines.create'))->assertForbidden();
        $this->actingAs($viewer)->get(route('machines.edit', $machine))->assertForbidden();
        $this->actingAs($viewer)->post(route('machines.store'), $payload)->assertForbidden();
        $this->actingAs($viewer)->put(route('machines.update', $machine), $payload)->assertForbidden();
        $this->actingAs($viewer)->get(route('machine-repairs.create'))->assertForbidden();
        $this->actingAs($viewer)->post(route('machine-repairs.store'), [])->assertForbidden();

        $this->actingAs($repairer)->get(route('machine-repairs.create'))->assertOk();
        $this->actingAs($repairer)->get(route('machines.index'))->assertForbidden();

        $this->assertSame(1, Machine::query()->count());
    }

    // ------------------------------------------------------------------
    // Dashboard
    // ------------------------------------------------------------------

    public function test_dashboard_renders_on_an_empty_database(): void
    {
        $this->actingAs($this->owner())->get(route('dashboard'))->assertOk()
            ->assertViewHas('cashInHand', 0)
            ->assertViewHas('machineSpend', 0)
            ->assertViewHas('paperBalance', 0)
            ->assertSee('All clear')
            ->assertSee('Nothing spent yet')
            ->assertSee('Quiet so far');
    }

    public function test_dashboard_kpis_match_records(): void
    {
        $owner = $this->owner();
        $manager = $this->manager();
        $head = ExpenseHead::factory()->create(['name' => 'Diesel']);
        $machine = Machine::factory()->create(['status' => MachineStatus::UnderRepair]);
        Machine::factory()->create();

        CashFloat::factory()->create(['user_id' => $manager->id, 'amount' => 100000]);
        Expense::factory()->create(['user_id' => $manager->id, 'expense_head_id' => $head->id, 'amount' => 30000, 'status' => ApprovalStatus::Approved]);
        Expense::factory()->create(['user_id' => $manager->id, 'expense_head_id' => $head->id, 'amount' => 5000, 'status' => ApprovalStatus::Rejected]);
        Expense::factory()->create(['user_id' => $manager->id, 'expense_head_id' => $head->id, 'amount' => 1500, 'machine_id' => $machine->id, 'status' => ApprovalStatus::Approved]);
        Expense::factory()->create(['user_id' => $owner->id, 'expense_head_id' => $head->id, 'amount' => 999, 'spent_on' => '2026-09-30', 'status' => ApprovalStatus::Approved]);

        MachineRepair::factory()->for($machine)->create(['repaired_on' => '2026-10-01', 'amount_paid' => 9500, 'status' => ApprovalStatus::Approved]);
        MachineRepair::factory()->for($machine)->create(['repaired_on' => '2026-10-27', 'amount_paid' => 2000, 'status' => ApprovalStatus::Pending]);
        MachineRepair::factory()->for($machine)->create(['repaired_on' => '2026-10-27', 'amount_paid' => 4000, 'status' => ApprovalStatus::Rejected]);
        MachineRepair::factory()->for($machine)->create(['repaired_on' => '2026-09-30', 'amount_paid' => 7000, 'status' => ApprovalStatus::Approved]);

        $type = PaperType::factory()->create(['reorder_level' => 1000]);
        PaperReceipt::factory()->create(['paper_type_id' => $type->id, 'quantity' => 500, 'status' => ApprovalStatus::Approved]);
        PaperIssue::factory()->create(['paper_type_id' => $type->id, 'quantity' => 120, 'kind' => PaperIssueKind::InHouse]);

        $this->actingAs($owner)->get(route('dashboard'))->assertOk()
            ->assertViewHas('cashGiven', 100000)
            ->assertViewHas('cashSpent', 31500)
            ->assertViewHas('cashInHand', 68500)
            ->assertViewHas('machineSpend', 9500 + 2000 + 1500)
            ->assertViewHas('machinesUnderRepair', 1)
            ->assertViewHas('paperTypeCount', 1)
            ->assertViewHas('lowStock', fn ($low) => $low->count() === 1)
            ->assertViewHas('heads', fn (array $heads) => count($heads) === 1 && $heads[0]['amount'] === 31500)
            ->assertViewHas('weekly', fn (array $w) => count($w['labels']) === 8 && $w['received'][7] === 500 && $w['issued'][7] === 120)
            ->assertViewHas('feed', fn (array $feed) => count($feed) === 6)
            ->assertSee('Rs 13,000')
            ->assertSee('Rs 68,500');
    }

    /**
     * A repair dated next month is left out of this month's machine spend.
     */
    public function test_future_dated_repair_is_not_counted_in_this_months_spend(): void
    {
        $owner = $this->owner();
        $machine = Machine::factory()->create();
        MachineRepair::factory()->for($machine)->create(['repaired_on' => '2026-10-10', 'amount_paid' => 1000, 'status' => ApprovalStatus::Approved]);
        MachineRepair::factory()->for($machine)->create(['repaired_on' => '2026-11-05', 'amount_paid' => 5000, 'status' => ApprovalStatus::Approved]);

        $this->actingAs($owner)->get(route('dashboard'))->assertOk()->assertViewHas('machineSpend', 1000);
        $this->actingAs($owner)->get(route('machines.index'))->assertOk()
            ->assertViewHas('machines', fn ($machines) => (int) $machines->sole()->month_spend === 1000);
    }

    public function test_dashboard_feed_escapes_user_input(): void
    {
        $owner = $this->owner();
        $machine = Machine::factory()->create(['code' => '<i>X</i>']);
        MachineRepair::factory()->for($machine)->create(['description' => '<script>alert(1)</script>']);
        Expense::factory()->create(['reason' => '<script>alert(2)</script>']);

        $this->actingAs($owner)->get(route('dashboard'))->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertDontSee('<script>alert(2)</script>', false);
    }

    public function test_dashboard_opens_for_limited_roles(): void
    {
        $this->actingAs($this->userWith([]))->get(route('dashboard'))->assertOk()
            ->assertDontSee(route('reports.index'))
            ->assertDontSee(route('approvals.index'));
        $this->actingAs($this->manager())->get(route('dashboard'))->assertOk();
    }

    // ------------------------------------------------------------------
    // Reports
    // ------------------------------------------------------------------

    public function test_reports_render_on_an_empty_database(): void
    {
        $this->actingAs($this->owner())->get(route('reports.index'))->assertOk()->assertSee('Nothing in this period.');

        foreach (['purchases_by_supplier', 'purchases_by_category', 'expenses_by_head', 'machine_spend', 'paper_movement'] as $key) {
            $response = $this->actingAs($this->owner())->get(route('reports.index', ['export' => $key]));
            $response->assertOk()->assertDownload();
            $this->assertSame([], $this->csvRows($response));
        }
    }

    public function test_reports_default_period_is_month_to_date(): void
    {
        $this->actingAs($this->owner())->get(route('reports.index'))->assertOk()
            ->assertViewHas('from', fn (Carbon $from) => $from->toDateTimeString() === '2026-10-01 00:00:00')
            ->assertViewHas('to', fn (Carbon $to) => $to->toDateTimeString() === '2026-10-27 23:59:59');
    }

    public function test_report_totals_match_records_and_respect_month_boundaries(): void
    {
        $owner = $this->owner();
        $approved = ['status' => ApprovalStatus::Approved];

        $supplier = Party::factory()->kind(PartyKind::Supplier)->create(['name' => 'Ink House']);
        $item = InventoryItem::factory()->for(InventoryCategory::factory()->state(['name' => 'Inks']), 'category')->create();
        Purchase::factory()->create($approved + ['party_id' => $supplier->id, 'inventory_item_id' => $item->id, 'purchased_on' => '2026-10-01', 'amount' => 1000]);
        Purchase::factory()->create($approved + ['party_id' => $supplier->id, 'inventory_item_id' => $item->id, 'purchased_on' => '2026-10-31', 'amount' => 2000]);
        Purchase::factory()->create($approved + ['party_id' => $supplier->id, 'inventory_item_id' => $item->id, 'purchased_on' => '2026-09-30', 'amount' => 40000]);
        Purchase::factory()->create($approved + ['party_id' => $supplier->id, 'inventory_item_id' => $item->id, 'purchased_on' => '2026-11-01', 'amount' => 80000]);
        Purchase::factory()->create(['status' => ApprovalStatus::Pending, 'party_id' => $supplier->id, 'inventory_item_id' => $item->id, 'purchased_on' => '2026-10-15', 'amount' => 160000]);

        $head = ExpenseHead::factory()->create(['name' => 'Tea']);
        Expense::factory()->create($approved + ['expense_head_id' => $head->id, 'spent_on' => '2026-10-31', 'amount' => 300]);
        Expense::factory()->create($approved + ['expense_head_id' => $head->id, 'spent_on' => '2026-10-01', 'amount' => 200]);
        Expense::factory()->create($approved + ['expense_head_id' => $head->id, 'spent_on' => '2026-11-01', 'amount' => 7000]);
        Expense::factory()->create(['status' => ApprovalStatus::Rejected, 'expense_head_id' => $head->id, 'spent_on' => '2026-10-10', 'amount' => 9000]);

        $machine = Machine::factory()->create(['code' => 'P-01', 'name' => 'Offset']);
        MachineRepair::factory()->for($machine)->create($approved + ['repaired_on' => '2026-10-31', 'discount' => 100, 'amount_paid' => 900]);
        MachineRepair::factory()->for($machine)->create($approved + ['repaired_on' => '2026-10-01', 'discount' => 50, 'amount_paid' => 450]);
        MachineRepair::factory()->for($machine)->create($approved + ['repaired_on' => '2026-11-01', 'discount' => 0, 'amount_paid' => 5000]);
        MachineRepair::factory()->for($machine)->create(['status' => ApprovalStatus::Pending, 'repaired_on' => '2026-10-15', 'amount_paid' => 6000]);

        $type = PaperType::factory()->create(['name' => 'Offset', 'gsm' => 68]);
        PaperReceipt::factory()->create($approved + ['paper_type_id' => $type->id, 'received_at' => '2026-10-31 18:30:00', 'quantity' => 700]);
        PaperReceipt::factory()->create($approved + ['paper_type_id' => $type->id, 'received_at' => '2026-11-01 00:00:00', 'quantity' => 5]);
        PaperReceipt::factory()->create(['status' => ApprovalStatus::Rejected, 'paper_type_id' => $type->id, 'received_at' => '2026-10-10 09:00:00', 'quantity' => 9]);
        PaperIssue::factory()->create(['paper_type_id' => $type->id, 'issued_on' => '2026-10-31', 'quantity' => 100, 'kind' => PaperIssueKind::InHouse]);
        PaperIssue::factory()->create(['paper_type_id' => $type->id, 'issued_on' => '2026-10-01', 'quantity' => 40, 'kind' => PaperIssueKind::Outsource]);
        PaperIssue::factory()->create(['paper_type_id' => $type->id, 'issued_on' => '2026-10-02', 'quantity' => 7, 'kind' => PaperIssueKind::Return]);
        PaperIssue::factory()->create(['paper_type_id' => $type->id, 'issued_on' => '2026-09-30', 'quantity' => 1000, 'kind' => PaperIssueKind::InHouse]);

        $range = ['from' => '2026-10-01', 'to' => '2026-10-31'];

        $this->actingAs($owner)->get(route('reports.index', $range))->assertOk()
            ->assertViewHas('purchases_by_supplier', fn ($rows) => $rows->sole() === ['Name' => 'Ink House', 'Bills' => 2, 'Amount (Rs)' => 3000])
            ->assertViewHas('purchases_by_category', fn ($rows) => $rows->sole() === ['Name' => 'Inks', 'Bills' => 2, 'Amount (Rs)' => 3000])
            ->assertViewHas('expenses_by_head', fn ($rows) => $rows->sole() === ['Head' => 'Tea', 'Entries' => 2, 'Amount (Rs)' => 500])
            ->assertViewHas('machine_spend', fn ($rows) => $rows->sole() === ['Machine' => 'P-01 Offset', 'Bills' => 2, 'Discount (Rs)' => 150, 'Paid (Rs)' => 1350])
            ->assertViewHas('paper_movement', fn ($rows) => $rows->sole()['Received'] === 700
                && $rows->sole()['In-house'] === 100 && $rows->sole()['Outsource'] === 40 && $rows->sole()['Returned'] === 7);

        $rows = $this->csvRows($this->actingAs($owner)->get(route('reports.index', $range + ['export' => 'machine_spend']))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8')
            ->assertDownload('machine_spend_20261001_20261031.csv'));
        $this->assertSame([['Machine', 'Bills', 'Discount (Rs)', 'Paid (Rs)'], ['P-01 Offset', '2', '150', '1350']], $rows);

        // A single-day range on the last day of the month still includes that day.
        $this->actingAs($owner)->get(route('reports.index', ['from' => '2026-10-31', 'to' => '2026-10-31']))->assertOk()
            ->assertViewHas('purchases_by_supplier', fn ($rows) => $rows->sole()['Amount (Rs)'] === 2000)
            ->assertViewHas('expenses_by_head', fn ($rows) => $rows->sole()['Amount (Rs)'] === 300)
            ->assertViewHas('machine_spend', fn ($rows) => $rows->sole()['Paid (Rs)'] === 900);
    }

    public function test_report_rows_are_sorted_and_footer_totals_rendered(): void
    {
        $owner = $this->owner();
        $small = ExpenseHead::factory()->create(['name' => 'Small head']);
        $big = ExpenseHead::factory()->create(['name' => 'Big head']);
        Expense::factory()->create(['status' => ApprovalStatus::Approved, 'expense_head_id' => $small->id, 'amount' => 1234]);
        Expense::factory()->create(['status' => ApprovalStatus::Approved, 'expense_head_id' => $big->id, 'amount' => 98765]);

        $this->actingAs($owner)->get(route('reports.index'))->assertOk()
            ->assertSeeInOrder(['Big head', 'Small head'])
            ->assertSee('99,999');
    }

    public function test_reports_with_reversed_range_render_empty(): void
    {
        Expense::factory()->create(['status' => ApprovalStatus::Approved]);

        $this->actingAs($this->owner())->get(route('reports.index', ['from' => '2026-10-31', 'to' => '2026-10-01']))
            ->assertOk()->assertViewHas('expenses_by_head', fn ($rows) => $rows->isEmpty());
    }

    public function test_unknown_export_is_404(): void
    {
        $this->actingAs($this->owner())->get(route('reports.index', ['export' => 'salaries']))->assertNotFound();
    }

    /**
     * Malformed report dates fall back to the default range instead of crashing.
     */
    public function test_malformed_report_dates_do_not_500(): void
    {
        $owner = $this->owner();

        $this->assertLessThan(500, $this->actingAs($owner)->get('/reports?from=garbage')->status());
        $this->assertLessThan(500, $this->actingAs($owner)->get('/reports?to=31-31-2026')->status());
    }

    /**
     * An array ?export[] value is ignored instead of crashing.
     */
    public function test_array_export_parameter_does_not_500(): void
    {
        $this->assertLessThan(500, $this->actingAs($this->owner())->get('/reports?export[]=machine_spend')->status());
    }

    public function test_reports_permission(): void
    {
        $this->actingAs($this->userWith(['machines.view']))->get(route('reports.index'))->assertForbidden();
        $this->actingAs($this->userWith(['machines.view']))->get(route('reports.index', ['export' => 'machine_spend']))->assertForbidden();
        $this->actingAs($this->userWith(['reports.view']))->get(route('reports.index'))->assertOk();
        $this->actingAs($this->manager())->get(route('reports.index'))->assertOk();
    }

    public function test_reports_in_urdu(): void
    {
        $owner = User::factory()->owner()->create(['locale' => 'ur']);
        $head = ExpenseHead::factory()->create(['name' => 'Tea']);
        Expense::factory()->create(['status' => ApprovalStatus::Approved, 'expense_head_id' => $head->id, 'amount' => 500]);
        MachineRepair::factory()->create(['status' => ApprovalStatus::Approved]);

        $this->actingAs($owner)->get(route('reports.index'))->assertOk()->assertSee('dir="rtl"', false)->assertSee('رقم (روپے)');

        $rows = $this->csvRows($this->actingAs($owner)->get(route('reports.index', ['export' => 'expenses_by_head']))->assertOk());
        $this->assertSame(['مد', 'اندراج', 'رقم (روپے)'], $rows[0]);
        $this->assertSame('500', $rows[1][2]);
    }

    // ------------------------------------------------------------------
    // Settings
    // ------------------------------------------------------------------

    /**
     * @return array<string, string|int|float>
     */
    private function settingsPayload(array $overrides = []): array
    {
        return $overrides + [
            'manager_daily_limit' => 5000, 'default_wastage_percent' => 2.5,
            'ot_divisor' => 8, 'ot_multiplier' => 1.5, 'standard_hours' => 9, 'unmarked_as' => 'absent',
            'mid_month_day' => 15, 'mid_month_percent' => 40, 'pay_day' => 7,
        ];
    }

    public function test_settings_show_defaults_on_empty_database(): void
    {
        $this->actingAs($this->owner())->get(route('settings.edit'))->assertOk()
            ->assertViewHas('dailyLimit', 0)
            ->assertViewHas('defaultWastage', 3.0)
            ->assertViewHas('payroll', fn (array $p) => $p['ot_divisor'] === 6.0 && $p['unmarked_as'] === 'present' && $p['mid_month_day'] === 22 && $p['pay_day'] === 10);
    }

    public function test_owner_saves_settings_and_they_take_effect(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)->put(route('settings.update'), $this->settingsPayload())
            ->assertRedirect(route('settings.edit'))->assertSessionHasNoErrors();

        $this->assertSame('5000', (string) Setting::get(Setting::ManagerDailyLimit));
        $this->assertSame([
            'ot_divisor' => 8.0, 'ot_multiplier' => 1.5, 'standard_hours' => 9.0, 'unmarked_as' => 'absent',
            'mid_month_day' => 15, 'mid_month_percent' => 40.0, 'pay_day' => 7,
        ], Setting::payrollRules());

        $this->actingAs($owner)->get(route('settings.edit'))->assertOk()
            ->assertViewHas('dailyLimit', 5000)->assertViewHas('defaultWastage', 2.5);

        // Saving twice updates rather than duplicating rows.
        $this->actingAs($owner)->put(route('settings.update'), $this->settingsPayload(['manager_daily_limit' => 0]))->assertSessionHasNoErrors();
        $this->assertSame(1, Setting::query()->where('key', Setting::ManagerDailyLimit)->count());
        $this->assertSame(0, (int) Setting::get(Setting::ManagerDailyLimit));
    }

    public function test_daily_limit_drives_manager_expense_auto_approval(): void
    {
        $owner = $this->owner();
        $manager = $this->manager();
        $head = ExpenseHead::factory()->create();
        $this->actingAs($owner)->put(route('settings.update'), $this->settingsPayload(['manager_daily_limit' => 1000]))->assertSessionHasNoErrors();

        $post = fn (int $amount) => $this->actingAs($manager)->post(route('expenses.store'), [
            'spent_on' => '2026-10-27', 'expense_head_id' => $head->id, 'paid_to' => 'Chai wala', 'amount' => $amount, 'reason' => 'Tea',
        ])->assertSessionHasNoErrors();

        $post(600);
        $post(400);
        $post(1);

        $this->assertSame(
            [ApprovalStatus::Approved, ApprovalStatus::Approved, ApprovalStatus::Pending],
            Expense::query()->orderBy('id')->get()->pluck('status')->all(),
        );

        // Limit 0 sends everything for approval.
        $this->actingAs($owner)->put(route('settings.update'), $this->settingsPayload(['manager_daily_limit' => 0]));
        $post(1);
        $this->assertSame(ApprovalStatus::Pending, Expense::query()->latest('id')->first()->status);
    }

    public function test_settings_validation(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)->put(route('settings.update'), [])->assertSessionHasErrors([
            'manager_daily_limit', 'default_wastage_percent', 'ot_divisor', 'ot_multiplier', 'standard_hours',
            'unmarked_as', 'mid_month_day', 'mid_month_percent', 'pay_day',
        ]);

        $this->actingAs($owner)->put(route('settings.update'), [
            'manager_daily_limit' => -1, 'default_wastage_percent' => 51, 'ot_divisor' => 0, 'ot_multiplier' => 4,
            'standard_hours' => 17, 'unmarked_as' => 'leave', 'mid_month_day' => 29, 'mid_month_percent' => 101, 'pay_day' => 0,
        ])->assertSessionHasErrors([
            'manager_daily_limit', 'default_wastage_percent', 'ot_divisor', 'ot_multiplier', 'standard_hours',
            'unmarked_as', 'mid_month_day', 'mid_month_percent', 'pay_day',
        ]);

        $this->actingAs($owner)->put(route('settings.update'), $this->settingsPayload(['manager_daily_limit' => 'abc']))
            ->assertSessionHasErrors('manager_daily_limit');

        $this->assertSame(0, Setting::query()->count());
    }

    public function test_settings_permission(): void
    {
        foreach ([$this->manager(), $this->userWith(['reports.view', 'approve'])] as $user) {
            $this->actingAs($user)->get(route('settings.edit'))->assertForbidden();
            $this->actingAs($user)->put(route('settings.update'), $this->settingsPayload())->assertForbidden();
        }

        $this->actingAs($this->userWith(['manage-settings']))->get(route('settings.edit'))->assertOk();
        $this->actingAs($this->userWith(['manage-settings']))->put(route('settings.update'), $this->settingsPayload())->assertRedirect(route('settings.edit'));
        $this->assertSame(5000, (int) Setting::get(Setting::ManagerDailyLimit));
    }

    // ------------------------------------------------------------------
    // Urdu, guests
    // ------------------------------------------------------------------

    public function test_module_pages_render_in_urdu(): void
    {
        $owner = User::factory()->owner()->create(['locale' => 'ur']);
        $machine = Machine::factory()->create();
        MachineRepair::factory()->for($machine)->create(['kind' => RepairKind::NewPart]);
        Expense::factory()->create();

        foreach ([route('dashboard'), route('machines.index'), route('machines.show', $machine), route('machines.create'),
            route('machines.edit', $machine), route('machine-repairs.create'), route('reports.index'), route('settings.edit')] as $url) {
            $this->actingAs($owner)->get($url)->assertOk()->assertSee('dir="rtl"', false);
        }
    }

    public function test_guests_are_redirected_to_login(): void
    {
        foreach ([route('dashboard'), route('machines.index'), route('reports.index'), route('settings.edit')] as $url) {
            $this->get($url)->assertRedirect(route('login'));
        }
        $this->put(route('settings.update'), $this->settingsPayload())->assertRedirect(route('login'));
        $this->assertSame(0, Setting::query()->count());
    }

    // ------------------------------------------------------------------
    // Whole-app smoke test
    // ------------------------------------------------------------------

    /**
     * @return list<string>
     */
    private function crawlGetRoutes(User $user, bool $withParameters): array
    {
        $failures = [];

        /** @var Route $route */
        foreach (RouteFacade::getRoutes()->getRoutes() as $route) {
            if (! in_array('GET', $route->methods(), true) || ! $route->getName() || Str::startsWith($route->uri(), ['_', 'up', 'storage', 'sanctum'])) {
                continue;
            }
            if (in_array($route->getName(), ['logout'], true)) {
                continue;
            }

            $params = [];
            $names = $route->parameterNames();
            if ($names !== []) {
                if (! $withParameters) {
                    continue;
                }
                $bindings = collect($route->signatureParameters(['subClass' => UrlRoutable::class]))
                    ->mapWithKeys(fn (\ReflectionParameter $p) => [$p->getName() => $p->getType()?->getName()]);
                foreach ($names as $name) {
                    if ($name === 'locale') {
                        $params[$name] = 'ur';

                        continue;
                    }
                    $class = $bindings[$name] ?? $bindings[Str::camel($name)] ?? null;
                    if (! $class || ! is_subclass_of($class, Model::class)) {
                        $failures[] = "{$route->getName()}: cannot resolve {{$name}}";

                        continue 2;
                    }
                    $params[$name] = $class::query()->first() ?? $class::factory()->create();
                }
            }

            $url = route($route->getName(), $params);
            $status = $this->actingAs($user)->get($url)->status();
            if ($status >= 500) {
                $failures[] = "{$route->getName()} {$url} -> {$status}";
            }
        }

        return $failures;
    }

    public function test_full_seed_then_every_get_route_responds_without_server_error(): void
    {
        // DatabaseSeeder only calls DemoDataSeeder in the local environment, so call it explicitly too.
        $this->seed([DatabaseSeeder::class, DemoDataSeeder::class]);
        if (! PayrollRun::query()->exists()) {
            PayrollRun::factory()->create();
        }

        $owner = User::query()->where('email', 'owner@etihad.test')->sole();
        $manager = User::query()->where('email', 'faisal@etihad.test')->sole();

        $this->assertSame([], $this->crawlGetRoutes($owner, true));
        $this->assertSame([], $this->crawlGetRoutes($manager, true));

        $owner->update(['locale' => 'ur']);
        $this->assertSame([], $this->crawlGetRoutes($owner->fresh(), true));
    }

    public function test_every_parameterless_get_route_renders_on_an_empty_database(): void
    {
        $this->assertSame([], $this->crawlGetRoutes($this->owner(), false));
    }
}
