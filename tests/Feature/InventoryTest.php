<?php

namespace Tests\Feature;

use App\Enums\ApprovalStatus;
use App\Models\InventoryCategory;
use App\Models\InventoryIssue;
use App\Models\InventoryItem;
use App\Models\Party;
use App\Models\Purchase;
use App\Models\Role;
use App\Models\User;
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

        $this->actingAs($owner)->delete(route('inventory-categories.destroy', $category))->assertRedirect(route('inventory-categories.index'));
        $this->assertModelMissing($category);
    }

    public function test_a_category_with_items_cannot_be_deleted(): void
    {
        $category = InventoryItem::factory()->create()->category;

        $this->actingAs(User::factory()->owner()->create())->delete(route('inventory-categories.destroy', $category))->assertSessionHasErrors('category');
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

        $this->actingAs($owner)->delete(route('inventory-items.destroy', $used))->assertSessionHasErrors('item');
        $this->assertModelExists($used);

        $this->actingAs($owner)->delete(route('inventory-items.destroy', $unused))->assertRedirect(route('inventory.index'));
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
}
