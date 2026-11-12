<?php

namespace Tests\Feature;

use App\Enums\ApprovalStatus;
use App\Enums\PartyKind;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\InventoryCategory;
use App\Models\InventoryIssue;
use App\Models\InventoryItem;
use App\Models\Machine;
use App\Models\Party;
use App\Models\Purchase;
use App\Models\Role;
use App\Models\User;
use App\Support\OtherOption;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_purchases_link_now_opens_inventory(): void
    {
        $this->actingAs(User::factory()->create())->get('/purchases')->assertRedirect('/inventory?tab=purchases');
    }

    public function test_inventory_pages_render(): void
    {
        $owner = User::factory()->owner()->create();
        $item = Purchase::factory()->create()->item;
        InventoryIssue::factory()->create(['inventory_item_id' => $item->id, 'quantity' => 1]);

        foreach (['stock', 'purchases', 'issues'] as $tab) {
            $this->actingAs($owner)->get(route('inventory.index', ['tab' => $tab]))->assertOk()->assertSee($item->name);
        }
        $this->actingAs($owner)->get(route('inventory-items.show', $item))->assertOk()->assertSee('Stock ledger');
        $this->actingAs($owner)->get(route('inventory-items.edit', $item))->assertOk();
        $this->actingAs($owner)->get(route('inventory-categories.edit', $item->category))->assertOk();
    }

    public function test_category_crud(): void
    {
        $owner = User::factory()->owner()->create();

        $this->actingAs($owner)->post(route('inventory-categories.store'), ['name' => 'Chemicals', 'name_ur' => 'کیمیکل', 'tracks_stock' => 1, 'is_active' => 1])
            ->assertRedirect(route('inventory-categories.index'));
        $category = InventoryCategory::query()->where('name', 'Chemicals')->sole();
        $this->assertTrue($category->tracks_stock);

        $this->actingAs($owner)->put(route('inventory-categories.update', $category), ['name' => 'Press chemicals', 'tracks_stock' => 0, 'is_active' => 1])
            ->assertSessionHasNoErrors();
        $this->assertFalse($category->refresh()->tracks_stock);

        $this->actingAs($owner)->delete(route('inventory-categories.destroy', $category), ['delete_note' => 'Test reason'])->assertRedirect(route('inventory-categories.index'));
        $this->assertModelMissing($category);
    }

    public function test_a_category_with_items_cannot_be_deleted(): void
    {
        $category = InventoryItem::factory()->create()->category;

        $this->actingAs(User::factory()->owner()->create())->delete(route('inventory-categories.destroy', $category), ['delete_note' => 'Test reason'])->assertSessionHasErrors('category');
        $this->assertModelExists($category);
    }

    public function test_purchases_add_stock_and_moving_out_takes_it_away(): void
    {
        $manager = User::factory()->create();
        $item = InventoryItem::factory()->create(['name' => 'Black ink', 'unit' => 'kg']);

        $this->actingAs($manager)->post(route('purchases.store'), [
            'purchased_on' => today()->toDateString(), 'party_id' => Party::factory()->create()->id,
            'inventory_item_id' => $item->id, 'quantity' => 25, 'rate' => 740,
        ])->assertRedirect(route('inventory.index', ['tab' => 'purchases']));

        $purchase = Purchase::query()->sole();
        $this->assertSame(18500, $purchase->amount);
        $this->assertSame(ApprovalStatus::Pending, $purchase->status);
        $this->assertSame(25.0, $item->fresh()->balance());

        $this->actingAs($manager)->post(route('inventory-issues.store'), [
            'issued_on' => today()->toDateString(), 'inventory_item_id' => $item->id, 'quantity' => 8.5, 'issued_to' => 'Machine P-01',
        ])->assertRedirect(route('inventory.index', ['tab' => 'issues']));

        $this->assertSame(16.5, $item->fresh()->balance());
    }

    public function test_a_new_supplier_can_be_typed_in_and_is_offered_next_time(): void
    {
        $manager = User::factory()->create();
        $item = InventoryItem::factory()->create();
        $existing = Party::factory()->create(['name' => 'Ravi Traders']);

        $this->actingAs($manager)->get(route('purchases.create'))->assertOk()->assertSee('Other… (type a new one)');
        $purchase = fn (string $name) => $this->actingAs($manager)->post(route('purchases.store'), [
            'purchased_on' => today()->toDateString(), 'party_id' => OtherOption::Value, 'party_id_other' => $name,
            'inventory_item_id' => $item->id, 'quantity' => 2, 'rate' => 100,
        ])->assertSessionHasNoErrors();

        $purchase('  Hafiz   Paint House ');
        $new = Party::query()->where('name', 'Hafiz Paint House')->sole();
        $this->assertSame(PartyKind::Supplier, $new->kind);
        $this->assertSame($new->id, Purchase::query()->latest('id')->first()->party_id);

        // The same name typed again, in any case, is reused rather than duplicated.
        $purchase('ravi traders');
        $this->assertSame($existing->id, Purchase::query()->latest('id')->first()->party_id);
        $this->assertSame(1, Party::query()->whereRaw('LOWER(name) = ?', ['ravi traders'])->count());

        $this->actingAs($manager)->get(route('purchases.create'))->assertSee('Hafiz Paint House');
    }

    public function test_the_item_is_typed_or_picked_on_purchase_and_move_out(): void
    {
        $manager = User::factory()->create();
        $ink = InventoryItem::factory()->create(['name' => 'Black ink', 'unit' => 'kg']);
        $party = Party::factory()->create();

        $this->actingAs($manager)->get(route('purchases.create'))->assertOk()
            ->assertSee('name="item_name"', false)->assertSee('<option value="Black ink">', false)->assertDontSee('Choose item');

        // A picked name and a brand-new name both work on Add purchase.
        $buy = fn (string $name) => $this->actingAs($manager)->post(route('purchases.store'), [
            'purchased_on' => today()->toDateString(), 'party_id' => $party->id, 'item_name' => $name, 'quantity' => 10, 'rate' => 100,
        ])->assertSessionHasNoErrors();
        $buy('  black INK ');
        $buy('Masking tape');
        $this->assertSame(10.0, $ink->fresh()->balance());
        $tape = InventoryItem::query()->where('name', 'Masking tape')->sole();
        $this->assertSame('General', $tape->category->name);

        // Move out takes the typed name of an item in stock, and refuses one that does not exist.
        $this->actingAs($manager)->get(route('inventory-issues.create'))->assertSee('<option value="Masking tape">', false);
        $this->actingAs($manager)->post(route('inventory-issues.store'), [
            'issued_on' => today()->toDateString(), 'item_name' => 'masking tape', 'quantity' => 4, 'issued_to' => 'Binding',
        ])->assertSessionHasNoErrors();
        $this->assertSame(6.0, $tape->fresh()->balance());
        $this->actingAs($manager)->post(route('inventory-issues.store'), [
            'issued_on' => today()->toDateString(), 'item_name' => 'Nothing like this', 'quantity' => 1, 'issued_to' => 'Binding',
        ])->assertSessionHasErrors('inventory_item_id');
    }

    public function test_ink_and_glue_bought_on_their_tab_go_into_stock(): void
    {
        $owner = User::factory()->owner()->create();
        $ink = InventoryCategory::query()->where('name', 'Ink')->sole();
        $diesel = InventoryCategory::query()->where('name', 'Diesel')->sole();
        $black = InventoryItem::factory()->create(['inventory_category_id' => $ink->id, 'name' => 'Black ink', 'unit' => 'kg']);

        // The Supplier box sits beside the date; it is needed, and a new name is added to Parties as a supplier.
        $this->actingAs($owner)->get(route('expenses.create', ['category' => $ink->id]))->assertOk()->assertSee('name="supplier"', false);
        $this->actingAs($owner)->post(route('expenses.store'), [
            'spent_on' => today()->toDateString(), 'inventory_category_id' => $ink->id, 'items' => [['item' => 'Black ink', 'quantity' => 1, 'rate' => 900]],
        ])->assertSessionHasErrors('supplier');

        $this->actingAs($owner)->post(route('expenses.store'), [
            'spent_on' => today()->toDateString(), 'inventory_category_id' => $ink->id, 'supplier' => '  Hafiz  Ink House ',
            'items' => [['item' => 'black ink', 'quantity' => 12, 'rate' => 900], ['item' => 'Cyan ink', 'quantity' => 3.5, 'rate' => 1200]],
        ])->assertSessionHasNoErrors();
        $supplier = Party::query()->where('name', 'Hafiz Ink House')->sole();
        $this->assertSame(PartyKind::Supplier, $supplier->kind);
        $this->assertSame('Hafiz Ink House', Expense::query()->sole()->paid_to);
        $this->actingAs($owner)->get(route('expenses.show', Expense::query()->sole()))->assertSee('Hafiz Ink House');
        $this->actingAs($owner)->get(route('expenses.create', ['category' => $ink->id]))->assertSee('<option value="Hafiz Ink House">', false);

        $this->assertSame(12.0, $black->fresh()->balance());
        $cyan = $ink->items()->where('name', 'Cyan ink')->sole();
        $this->assertSame(3.5, $cyan->balance());
        $this->actingAs($owner)->get(route('inventory-items.show', $black))->assertOk()->assertSee(Expense::query()->sole()->receiptNumber());
        $this->actingAs($owner)->get(route('inventory.index'))->assertSee('Cyan ink');

        // Stock bought this way can be moved out, and deleting the expense takes it back off.
        $this->actingAs($owner)->post(route('inventory-issues.store'), ['issued_on' => today()->toDateString(), 'item_name' => 'Black ink', 'quantity' => 2, 'issued_to' => 'Press 1'])->assertSessionHasNoErrors();
        $this->assertSame(10.0, $black->fresh()->balance());
        $this->actingAs($owner)->delete(route('expenses.destroy', Expense::query()->sole()))->assertRedirect();
        $this->assertSame(-2.0, $black->fresh()->balance());

        // Diesel is used up, not stocked: its expense lines add nothing to inventory.
        $this->actingAs($owner)->post(route('expenses.store'), [
            'spent_on' => today()->toDateString(), 'inventory_category_id' => $diesel->id, 'items' => [['item' => 'Diesel', 'quantity' => 20, 'rate' => 280]],
        ])->assertSessionHasNoErrors();
        $this->assertNull(Expense::query()->sole()->items()->sole()->inventory_item_id);
    }

    public function test_material_tabs_show_no_inventory_header_warning_or_totals(): void
    {
        $owner = User::factory()->owner()->create();
        $diesel = InventoryCategory::query()->where('name', 'Diesel')->sole();

        $this->actingAs($owner)->get(route('inventory.index'))->assertSee('Waiting for approval')->assertSee('Purchased this month');
        $this->actingAs($owner)->get(route('inventory.index', ['tab' => 'material-'.$diesel->id]))->assertOk()
            ->assertDontSee('Waiting for approval')->assertDontSee('Purchased this month')->assertDontSee('Moved out this month')
            ->assertDontSee(route('inventory-categories.index'), false);
    }

    public function test_cannot_move_out_more_than_the_balance(): void
    {
        $item = Purchase::factory()->create(['quantity' => 10])->item;

        $this->actingAs(User::factory()->create())->post(route('inventory-issues.store'), [
            'issued_on' => today()->toDateString(), 'inventory_item_id' => $item->id, 'quantity' => 10.01, 'issued_to' => 'Binders',
        ])->assertSessionHasErrors('quantity');

        $this->assertDatabaseCount('inventory_issues', 0);
    }

    public function test_a_purchase_sent_back_leaves_stock(): void
    {
        $owner = User::factory()->owner()->create();
        $item = InventoryItem::factory()->create(['reorder_level' => 5]);
        $purchase = Purchase::factory()->create(['inventory_item_id' => $item->id, 'quantity' => 20, 'created_by' => User::factory()->create()->id]);

        $this->assertSame(20.0, $item->fresh()->balance());

        $purchase->reject($owner, 'Wrong supplier');

        $this->assertSame(0.0, $item->fresh()->balance());
        $this->assertSame('none', $item->fresh()->level());

        InventoryIssue::factory()->create(['inventory_item_id' => $item->id, 'quantity' => 0]);
        Purchase::factory()->create(['inventory_item_id' => $item->id, 'quantity' => 3]);
        $this->assertSame('low', $item->fresh()->level());
    }

    public function test_an_item_with_history_cannot_be_deleted(): void
    {
        $owner = User::factory()->owner()->create();
        $used = Purchase::factory()->create()->item;
        $unused = InventoryItem::factory()->create();

        $this->actingAs($owner)->delete(route('inventory-items.destroy', $used), ['delete_note' => 'Test reason'])->assertSessionHasErrors('item');
        $this->assertModelExists($used);

        $this->actingAs($owner)->delete(route('inventory-items.destroy', $unused), ['delete_note' => 'Test reason'])->assertRedirect(route('inventory.index'));
        $this->assertModelMissing($unused);
    }

    public function test_item_names_are_unique_within_a_category(): void
    {
        $owner = User::factory()->owner()->create();
        $item = InventoryItem::factory()->create(['name' => 'Binding glue']);

        $this->actingAs($owner)->post(route('inventory-items.store'), [
            'inventory_category_id' => $item->inventory_category_id, 'name' => 'Binding glue', 'unit' => 'kg', 'is_active' => 1,
        ])->assertSessionHasErrors('name');

        $this->actingAs($owner)->post(route('inventory-items.store'), [
            'inventory_category_id' => InventoryCategory::factory()->create()->id, 'name' => 'Binding glue', 'unit' => 'kg', 'is_active' => 1,
        ])->assertSessionHasNoErrors();
    }

    public function test_bills_only_categories_stay_off_the_stock_tab(): void
    {
        $owner = User::factory()->owner()->create();
        $paper = InventoryCategory::query()->where('name', 'Paper')->sole();
        $this->assertFalse($paper->tracks_stock);
        $offset = Purchase::factory()->create(['inventory_item_id' => InventoryItem::factory()->create(['inventory_category_id' => $paper->id, 'name' => 'Offset 20 × 30'])->id])->item;

        $this->actingAs($owner)->get(route('inventory.index'))->assertDontSee('Offset 20 × 30');
        $this->actingAs($owner)->get(route('inventory.index', ['tab' => 'purchases']))->assertSee('Offset 20 × 30');
        $this->actingAs($owner)->get(route('inventory-issues.create'))->assertDontSee('Offset 20 × 30');
        $this->assertNotNull($offset);
    }

    public function test_permissions_guard_inventory_actions(): void
    {
        $viewer = User::factory()->create(['role_id' => Role::factory()->create(['permissions' => ['purchases.view']])->id]);

        $this->actingAs($viewer)->get(route('inventory.index'))->assertOk();
        $this->actingAs($viewer)->get(route('inventory-issues.create'))->assertForbidden();
        $this->actingAs($viewer)->get(route('inventory-categories.index'))->assertForbidden();
        $this->actingAs($viewer)->get(route('inventory-items.create'))->assertForbidden();
        $this->actingAs($viewer)->get(route('purchases.create'))->assertForbidden();
    }

    public function test_material_given_to_an_employee_waits_for_approval_and_comes_back_at_end_of_shift(): void
    {
        $owner = User::factory()->owner()->create();
        $manager = User::factory()->create();
        $item = Purchase::factory()->create(['quantity' => 20, 'created_by' => $owner->id])->item;
        $employee = Employee::factory()->create(['name' => 'Tariq Mehmood']);
        $machine = Machine::factory()->create();

        $this->actingAs($manager)->post(route('inventory-issues.store'), [
            'issued_on' => today()->toDateString(), 'inventory_item_id' => $item->id, 'quantity' => 5,
            'employee_id' => $employee->id, 'machine_id' => $machine->id,
        ])->assertSessionHasNoErrors();

        $issue = InventoryIssue::query()->sole();
        $this->assertSame('Tariq Mehmood', $issue->issued_to);
        $this->assertSame(ApprovalStatus::Pending, $issue->status);
        // Held out of stock while it waits, so it cannot be given twice.
        $this->assertSame(15.0, $item->refresh()->balance());
        $this->actingAs($owner)->get(route('approvals.index'))->assertSee('Material moved out');

        $this->actingAs($owner)->patch(route('approvals.update', ['inventory-issue', $issue->id]), ['decision' => 'approve'])->assertSessionHasNoErrors();
        $this->assertSame(ApprovalStatus::Approved, $issue->refresh()->status);

        $this->actingAs($manager)->patch(route('inventory-issues.settle', $issue), ['returned_quantity' => 6])->assertSessionHasErrors('returned_quantity');
        $this->actingAs($manager)->patch(route('inventory-issues.settle', $issue), ['returned_quantity' => 1.5])->assertSessionHasNoErrors();

        $this->assertSame(3.5, $issue->refresh()->used());
        $this->assertSame(16.5, $item->refresh()->balance());
        $this->actingAs($manager)->patch(route('inventory-issues.settle', $issue), ['returned_quantity' => 0])->assertStatus(409);
        $this->actingAs($owner)->get(route('inventory-items.show', $item))->assertOk()->assertSee('back at end of shift');
    }

    public function test_material_sent_back_by_the_owner_returns_to_stock(): void
    {
        $owner = User::factory()->owner()->create();
        $item = Purchase::factory()->create(['quantity' => 10, 'created_by' => $owner->id])->item;

        $this->actingAs(User::factory()->create())->post(route('inventory-issues.store'), [
            'issued_on' => today()->toDateString(), 'inventory_item_id' => $item->id, 'quantity' => 4, 'issued_to' => 'Binding section',
        ])->assertSessionHasNoErrors();
        $issue = InventoryIssue::query()->sole();
        $this->assertSame(6.0, $item->refresh()->balance());

        $this->actingAs($owner)->patch(route('approvals.update', ['inventory-issue', $issue->id]), ['decision' => 'reject', 'note' => 'Not needed'])->assertSessionHasNoErrors();

        $this->assertSame(10.0, $item->refresh()->balance());
    }

    public function test_material_needs_an_employee_or_a_name(): void
    {
        $item = Purchase::factory()->create(['quantity' => 10])->item;

        $this->actingAs(User::factory()->owner()->create())->post(route('inventory-issues.store'), [
            'issued_on' => today()->toDateString(), 'inventory_item_id' => $item->id, 'quantity' => 1,
        ])->assertSessionHasErrors('issued_to');
    }
}
