<?php

namespace Tests\Feature\E2E;

use App\Enums\ApprovalStatus;
use App\Enums\PaperIssueKind;
use App\Enums\PartyKind;
use App\Models\Godown;
use App\Models\InventoryCategory;
use App\Models\InventoryIssue;
use App\Models\InventoryItem;
use App\Models\PaperIssue;
use App\Models\PaperReceipt;
use App\Models\PaperType;
use App\Models\Party;
use App\Models\Purchase;
use App\Models\Role;
use App\Models\User;
use App\Support\PaperStock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * End-to-end coverage of paper stock, godowns and inventory, driven over HTTP the way a user would.
 */
class PaperInventoryE2ETest extends TestCase
{
    use RefreshDatabase;

    private function userWith(array $permissions): User
    {
        return User::factory()->create(['role_id' => Role::factory()->create(['permissions' => $permissions])->id]);
    }

    /**
     * @return array<string, mixed>
     */
    private function receiptPayload(PaperType $type, Party $party, Godown $godown, array $overrides = []): array
    {
        return $overrides + [
            'slip_no' => '7001',
            'received_at' => now()->format('Y-m-d\TH:i'),
            'party_id' => $party->id,
            'paper_type_id' => $type->id,
            'quantity' => 776,
            'truck_no' => 'LRL-1819',
            'godown_id' => $godown->id,
            'received_by' => 'Faisal',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function issuePayload(PaperType $type, Party $party, array $overrides = []): array
    {
        return $overrides + [
            'issued_on' => today()->toDateString(),
            'kind' => PaperIssueKind::InHouse->value,
            'party_id' => $party->id,
            'paper_type_id' => $type->id,
            'quantity' => 100,
            'issued_to' => 'Machine P-01',
            'wastage_percent' => 3,
        ];
    }

    private function balance(PaperType $type, Party $party): int
    {
        return app(PaperStock::class)->available($type->id, $party->id);
    }

    // ---------------------------------------------------------------- pages

    public function test_owner_opens_every_paper_and_godown_screen_with_data(): void
    {
        $owner = User::factory()->owner()->create();
        $type = PaperType::factory()->create(['code' => 'AA-1975', 'name' => 'AA printing']);
        $party = Party::factory()->create(['name' => 'Imran Traders']);
        $godown = Godown::factory()->create(['name' => 'Godown A', 'name_ur' => 'گودام اے']);
        $receipt = PaperReceipt::factory()->for($type)->for($party)->for($godown)->create(['slip_no' => '5531', 'created_by' => $owner->id]);
        PaperIssue::factory()->for($type)->for($party)->create(['quantity' => 76, 'kind' => PaperIssueKind::Return, 'reference' => 'RET-014']);

        $this->actingAs($owner);

        foreach (['stock', 'receipts', 'issues', 'nonsense'] as $tab) {
            $this->get(route('paper.index', ['tab' => $tab]))->assertOk()->assertSee('AA');
        }
        $this->get(route('paper.index', ['paper_type_id' => $type->id, 'party_id' => $party->id]))->assertOk()->assertSee('Imran Traders');
        $this->get(route('paper-receipts.create'))->assertOk()->assertSee('Godown A');
        $this->get(route('paper-receipts.show', $receipt))->assertOk()->assertSee('5531')->assertSee('Unloaded in Godown A');
        $this->get(route('paper-issues.create'))->assertOk()->assertSee('Imran Traders');
        $this->get(route('paper-issues.create', ['kind' => 'return']))->assertOk();
        $this->get(route('paper-types.index'))->assertOk()->assertSee('AA-1975');
        $this->get(route('paper-types.create'))->assertOk();
        $this->get(route('paper-types.edit', $type))->assertOk()->assertSee('AA-1975');
        $this->get(route('godowns.index'))->assertOk()->assertSee('Godown A');
        $this->get(route('godowns.create'))->assertOk();
        $this->get(route('godowns.edit', $godown))->assertOk();
    }

    public function test_owner_opens_every_inventory_screen_with_data(): void
    {
        $owner = User::factory()->owner()->create();
        $item = InventoryItem::factory()->create(['name' => 'Black ink']);
        Purchase::factory()->for($item, 'item')->create(['created_by' => $owner->id, 'bill_no' => 'B-77']);
        InventoryIssue::factory()->for($item, 'item')->create(['quantity' => 2, 'reference' => 'WO-1042']);

        $this->actingAs($owner);

        foreach (['stock', 'purchases', 'issues', 'nonsense'] as $tab) {
            $this->get(route('inventory.index', ['tab' => $tab]))->assertOk()->assertSee('Black ink');
        }
        $this->get(route('inventory.index', ['category' => $item->inventory_category_id]))->assertOk()->assertSee('Black ink');
        $this->get(route('inventory-categories.index'))->assertOk()->assertSee($item->category->name);
        $this->get(route('inventory-categories.create'))->assertOk();
        $this->get(route('inventory-categories.edit', $item->category))->assertOk();
        $this->get(route('inventory-items.create', ['category' => $item->inventory_category_id]))->assertOk();
        $this->get(route('inventory-items.show', $item))->assertOk()->assertSee('B-77')->assertSee('WO-1042');
        $this->get(route('inventory-items.edit', $item))->assertOk();
        $this->get(route('inventory-issues.create', ['item' => $item->id]))->assertOk()->assertSee('Black ink');
        $this->get(route('purchases.create', ['item' => $item->id]))->assertOk()->assertSee('Black ink');
    }

    public function test_empty_screens_render_without_data(): void
    {
        $this->actingAs(User::factory()->owner()->create());

        $this->get(route('paper.index'))->assertOk()->assertSee('No paper on record yet');
        $this->get(route('paper.index', ['tab' => 'receipts']))->assertOk()->assertSee('No trucks received yet');
        $this->get(route('paper.index', ['tab' => 'issues']))->assertOk()->assertSee('No paper issued yet');
        $this->get(route('paper-issues.create'))->assertOk();
        $this->get(route('paper-receipts.create'))->assertOk();
        $this->get(route('godowns.index'))->assertOk()->assertSee('No godowns yet');
        $this->get(route('inventory.index'))->assertOk()->assertSee('No items yet');
        $this->get(route('inventory-issues.create'))->assertOk()->assertSee('Nothing in stock to move out');
        $this->get(route('purchases.create'))->assertOk()->assertSee('Add an item first');
    }

    public function test_purchases_link_redirects_to_inventory_purchases_tab(): void
    {
        $this->actingAs(User::factory()->owner()->create())
            ->get('/purchases')
            ->assertRedirect('/inventory?tab=purchases');

        $this->get('/inventory?tab=purchases')->assertOk()->assertViewHas('tab', 'purchases');
    }

    public function test_guests_are_sent_to_login(): void
    {
        $this->get(route('paper.index'))->assertRedirect(route('login'));
        $this->get(route('inventory.index'))->assertRedirect(route('login'));
    }

    // ------------------------------------------------------------ Urdu

    public function test_screens_render_in_urdu(): void
    {
        $owner = User::factory()->owner()->create();
        $item = InventoryItem::factory()->create(['name_ur' => 'کالی سیاہی']);
        Purchase::factory()->for($item, 'item')->create(['created_by' => $owner->id]);
        $receipt = PaperReceipt::factory()->create(['created_by' => $owner->id, 'godown_id' => Godown::factory()->create(['name_ur' => 'گودام بی'])->id]);

        $this->actingAs($owner)->from(route('paper.index'))->get(route('locale', 'ur'))->assertRedirect(route('paper.index'));
        $this->assertSame('ur', $owner->refresh()->locale);

        $this->get(route('paper.index'))->assertOk()->assertSee('کاغذ اسٹاک');
        $this->get(route('paper.index', ['tab' => 'receipts']))->assertOk()->assertSee('گودام بی');
        $this->get(route('paper-receipts.create'))->assertOk()->assertSee('ٹرک وصول کریں');
        $this->get(route('paper-receipts.show', $receipt))->assertOk()->assertSee('گودام بی');
        $this->get(route('paper-issues.create'))->assertOk()->assertSee('کاغذ جاری کریں');
        $this->get(route('paper-types.index'))->assertOk()->assertSee('کاغذ کی اقسام');
        $this->get(route('godowns.index'))->assertOk()->assertSee('گودام');
        $this->get(route('inventory.index'))->assertOk()->assertSee('انوینٹری')->assertSee('کالی سیاہی');
        $this->get(route('inventory-categories.index'))->assertOk()->assertSee('انوینٹری کی اقسام');
        $this->get(route('inventory-items.show', $item))->assertOk()->assertSee('اسٹاک کھاتہ');
        $this->get(route('inventory-issues.create'))->assertOk()->assertSee('انوینٹری سے نکالیں');
        $this->get(route('purchases.create'))->assertOk()->assertSee('خریداری درج کریں');

        $type = PaperType::factory()->create();
        $party = Party::factory()->create();
        $this->post(route('paper-issues.store'), $this->issuePayload($type, $party))
            ->assertSessionHasErrors(['quantity' => 'اس کاغذ اور پارٹی کا صرف 0 اسٹاک میں ہے۔']);
    }

    public function test_unknown_locale_is_not_found(): void
    {
        $this->actingAs(User::factory()->owner()->create())->get(route('locale', 'fr'))->assertNotFound();
    }

    // ------------------------------------------------------------ permissions

    public function test_paper_view_only_role_can_look_but_not_touch(): void
    {
        $viewer = $this->userWith(['paper.view']);
        $receipt = PaperReceipt::factory()->create();
        $type = PaperType::factory()->create();
        $godown = Godown::factory()->create();

        $this->actingAs($viewer);
        $this->get(route('paper.index'))->assertOk()
            ->assertDontSee(route('paper-receipts.create'))
            ->assertDontSee(route('paper-issues.create'))
            ->assertDontSee(route('godowns.index'))
            ->assertDontSee(route('paper-types.index'));
        $this->get(route('paper-receipts.show', $receipt))->assertOk();

        $this->get(route('paper-receipts.create'))->assertForbidden();
        $this->post(route('paper-receipts.store'), [])->assertForbidden();
        $this->get(route('paper-issues.create'))->assertForbidden();
        $this->post(route('paper-issues.store'), [])->assertForbidden();
        $this->get(route('paper-types.index'))->assertForbidden();
        $this->get(route('paper-types.create'))->assertForbidden();
        $this->post(route('paper-types.store'), [])->assertForbidden();
        $this->get(route('paper-types.edit', $type))->assertForbidden();
        $this->put(route('paper-types.update', $type), [])->assertForbidden();
        $this->get(route('godowns.index'))->assertForbidden();
        $this->get(route('godowns.create'))->assertForbidden();
        $this->post(route('godowns.store'), ['name' => 'X'])->assertForbidden();
        $this->get(route('godowns.edit', $godown))->assertForbidden();
        $this->put(route('godowns.update', $godown), ['name' => 'X'])->assertForbidden();
        $this->delete(route('godowns.destroy', $godown))->assertForbidden();
        $this->assertModelExists($godown);

        $this->get(route('inventory.index'))->assertForbidden();
        $this->get(route('approvals.index'))->assertForbidden();
    }

    public function test_receiver_without_view_cannot_open_stock_or_slip(): void
    {
        $receiver = $this->userWith(['paper.receive']);
        $receipt = PaperReceipt::factory()->create();

        $this->actingAs($receiver);
        $this->get(route('paper-receipts.create'))->assertOk();
        $this->get(route('paper.index'))->assertForbidden();
        $this->get(route('paper-receipts.show', $receipt))->assertForbidden();
    }

    public function test_inventory_permissions_are_enforced_per_action(): void
    {
        $item = InventoryItem::factory()->create();
        Purchase::factory()->for($item, 'item')->create(['status' => ApprovalStatus::Approved]);
        $category = $item->category;

        $viewer = $this->userWith(['purchases.view']);
        $this->actingAs($viewer);
        $this->get(route('inventory.index'))->assertOk()
            ->assertDontSee(route('purchases.create'))
            ->assertDontSee(route('inventory-categories.index'));
        $this->get(route('inventory-items.show', $item))->assertOk()->assertDontSee(route('inventory-items.edit', $item));
        $this->get(route('inventory-items.create'))->assertForbidden();
        $this->post(route('inventory-items.store'), [])->assertForbidden();
        $this->get(route('inventory-items.edit', $item))->assertForbidden();
        $this->put(route('inventory-items.update', $item), [])->assertForbidden();
        $this->delete(route('inventory-items.destroy', $item))->assertForbidden();
        $this->get(route('inventory-categories.index'))->assertForbidden();
        $this->post(route('inventory-categories.store'), ['name' => 'X'])->assertForbidden();
        $this->put(route('inventory-categories.update', $category), ['name' => 'X'])->assertForbidden();
        $this->delete(route('inventory-categories.destroy', $category))->assertForbidden();
        $this->get(route('inventory-issues.create'))->assertForbidden();
        $this->post(route('inventory-issues.store'), [])->assertForbidden();
        $this->get(route('purchases.create'))->assertForbidden();
        $this->post(route('purchases.store'), [])->assertForbidden();

        // A store-keeper who can only move items out cannot see the inventory screens.
        $storeKeeper = $this->userWith(['inventory.issue']);
        $this->actingAs($storeKeeper);
        $this->get(route('inventory-issues.create'))->assertOk();
        $this->get(route('inventory.index'))->assertForbidden();
        $this->get(route('inventory-items.show', $item))->assertForbidden();

        // An inactive user loses every permission.
        $inactive = User::factory()->owner()->create(['is_active' => false]);
        $this->actingAs($inactive)->get(route('inventory.index'));
        $this->assertFalse($inactive->can('purchases.view'));
    }

    // ------------------------------------------------------------ paper flow

    public function test_full_truck_to_press_flow_with_approval(): void
    {
        $owner = User::factory()->owner()->create();
        $clerk = $this->userWith(['paper.view', 'paper.receive', 'paper.issue']);
        $type = PaperType::factory()->create(['reorder_level' => 500]);
        $party = Party::factory()->create(['name' => 'Imran Traders']);
        $godown = Godown::factory()->create(['name' => 'Godown A']);

        // Clerk receives a truck: it waits for the owner but counts in stock straight away.
        $this->actingAs($clerk)->post(route('paper-receipts.store'), $this->receiptPayload($type, $party, $godown, ['quantity' => 776]))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'Slip 7001 saved and sent for approval.');
        $receipt = PaperReceipt::query()->sole();
        $this->assertSame(ApprovalStatus::Pending, $receipt->status);
        $this->assertSame($clerk->id, $receipt->created_by);
        $this->assertSame(776, $this->balance($type, $party));

        $this->get(route('paper.index'))->assertOk()->assertSee('776 waiting')
            ->assertViewHas('receivedThisWeek', 776)->assertViewHas('trucksThisWeek', 1);

        // Owner sees it in approvals and approves.
        $this->actingAs($owner)->get(route('approvals.index'))->assertOk()->assertSee('Imran Traders');
        $this->actingAs($owner)->from(route('approvals.index'))
            ->patch(route('approvals.update', ['paper-receipt', $receipt->id]), ['decision' => 'approve'])
            ->assertRedirect(route('approvals.index'));
        $this->assertSame(ApprovalStatus::Approved, $receipt->refresh()->status);
        $this->assertSame($owner->id, $receipt->reviewed_by);

        // Deciding twice is refused.
        $this->actingAs($owner)->patch(route('approvals.update', ['paper-receipt', $receipt->id]), ['decision' => 'reject', 'note' => 'x'])->assertStatus(409);

        // Clerk issues in-house, then outsources, then returns some to the supplier.
        $this->actingAs($clerk)->post(route('paper-issues.store'), $this->issuePayload($type, $party, ['quantity' => 300]))
            ->assertSessionHasNoErrors()->assertRedirect(route('paper.index', ['tab' => 'issues']));
        $this->actingAs($clerk)->post(route('paper-issues.store'), $this->issuePayload($type, $party, ['kind' => 'outsource', 'quantity' => 200, 'issued_to' => 'Al-Noor Press']))
            ->assertSessionHasNoErrors();
        $this->actingAs($clerk)->post(route('paper-issues.store'), $this->issuePayload($type, $party, ['kind' => 'return', 'quantity' => 76, 'copies' => 5000, 'wastage_percent' => 3, 'book_name' => 'Should go']))
            ->assertSessionHasNoErrors();

        $return = PaperIssue::query()->where('kind', 'return')->sole();
        $this->assertNull($return->book_name);
        $this->assertNull($return->copies);
        $this->assertSame('0.00', $return->wastage_percent);

        $this->assertSame(200, $this->balance($type, $party));
        $this->actingAs($clerk)->get(route('paper.index'))->assertOk()
            ->assertViewHas('issuedThisWeek', 576)
            ->assertViewHas('balances', fn (Collection $rows) => $rows->sole()['received'] === 776 && $rows->sole()['issued'] === 576 && $rows->sole()['balance'] === 200 && $rows->sole()['status'] === 'critical')
            ->assertViewHas('lowStock', fn (Collection $rows) => $rows->count() === 1);

        // Over-issuing the last rims is refused; exactly the balance is accepted.
        $this->actingAs($clerk)->post(route('paper-issues.store'), $this->issuePayload($type, $party, ['quantity' => 201]))
            ->assertSessionHasErrors(['quantity' => 'Only 200 in stock for this paper and party.']);
        $this->actingAs($clerk)->post(route('paper-issues.store'), $this->issuePayload($type, $party, ['quantity' => 200]))
            ->assertSessionHasNoErrors();
        $this->assertSame(0, $this->balance($type, $party));

        // Godown totals and the issue-form dropdown follow along.
        $this->actingAs($owner)->get(route('godowns.index'))->assertOk()
            ->assertViewHas('godowns', fn (Collection $g) => (int) $g->sole()->paper_receipts_sum_quantity === 776 && $g->sole()->paper_receipts_count === 1);
        $this->actingAs($clerk)->get(route('paper-issues.create'))->assertOk()
            ->assertViewHas('balances', fn (Collection $rows) => $rows->isEmpty());
    }

    public function test_owner_receipts_are_approved_on_save(): void
    {
        $owner = User::factory()->owner()->create();
        $type = PaperType::factory()->create();
        $party = Party::factory()->create();

        $this->actingAs($owner)->post(route('paper-receipts.store'), $this->receiptPayload($type, $party, Godown::factory()->create()))
            ->assertSessionHas('status', 'Slip 7001 saved.');

        $receipt = PaperReceipt::query()->sole();
        $this->assertSame(ApprovalStatus::Approved, $receipt->status);
        $this->assertSame($owner->id, $receipt->reviewed_by);
        $this->actingAs($owner)->get(route('approvals.index'))->assertOk()->assertViewHas('items', fn (Collection $items) => $items->isEmpty());
    }

    public function test_rejected_receipt_drops_out_of_stock_totals_and_godown(): void
    {
        $owner = User::factory()->owner()->create();
        $clerk = $this->userWith(['paper.view', 'paper.receive']);
        $type = PaperType::factory()->create();
        $party = Party::factory()->create();
        $godown = Godown::factory()->create();

        $this->actingAs($clerk)->post(route('paper-receipts.store'), $this->receiptPayload($type, $party, $godown, ['quantity' => 500]));
        $this->actingAs($clerk)->post(route('paper-receipts.store'), $this->receiptPayload($type, $party, $godown, ['slip_no' => '7002', 'quantity' => 300]));
        $wrong = PaperReceipt::query()->where('slip_no', '7002')->sole();

        $this->actingAs($owner)->patch(route('approvals.update', ['paper-receipt', $wrong->id]), ['decision' => 'reject'])->assertSessionHasErrors('note');
        $this->actingAs($owner)->patch(route('approvals.update', ['paper-receipt', $wrong->id]), ['decision' => 'reject', 'note' => 'Wrong truck'])->assertSessionHasNoErrors();

        $this->assertSame(500, $this->balance($type, $party));
        $this->actingAs($clerk)->get(route('paper.index'))->assertViewHas('receivedThisWeek', 500)->assertViewHas('trucksThisWeek', 1);
        $this->actingAs($clerk)->get(route('paper.index', ['tab' => 'receipts']))->assertOk()->assertSee('Sent back');
        $this->actingAs($clerk)->get(route('paper-receipts.show', $wrong))->assertOk()->assertSee('Wrong truck');
        $this->actingAs($owner)->get(route('godowns.index'))
            ->assertViewHas('godowns', fn (Collection $g) => (int) $g->sole()->paper_receipts_sum_quantity === 500 && $g->sole()->paper_receipts_count === 2);
    }

    /**
     * A receipt whose paper was already issued cannot be sent back until the issue is reversed.
     */
    public function test_rejecting_a_receipt_whose_paper_was_already_issued_must_not_leave_negative_stock(): void
    {
        $owner = User::factory()->owner()->create();
        $clerk = $this->userWith(['paper.view', 'paper.receive', 'paper.issue']);
        $type = PaperType::factory()->create();
        $party = Party::factory()->create();

        $this->actingAs($clerk)->post(route('paper-receipts.store'), $this->receiptPayload($type, $party, Godown::factory()->create(), ['quantity' => 100]));
        $this->actingAs($clerk)->post(route('paper-issues.store'), $this->issuePayload($type, $party, ['quantity' => 100]))->assertSessionHasNoErrors();
        $receipt = PaperReceipt::query()->sole();

        $this->actingAs($owner)->patch(route('approvals.update', ['paper-receipt', $receipt->id]), ['decision' => 'reject', 'note' => 'Wrong slip']);

        $this->assertGreaterThanOrEqual(0, $this->balance($type, $party), 'Rejecting a receipt after its paper was issued leaves a negative paper balance.');
    }

    public function test_paper_of_one_party_cannot_be_issued_against_another(): void
    {
        $clerk = $this->userWith(['paper.issue']);
        $type = PaperType::factory()->create();
        [$imran, $qadri] = Party::factory()->count(2)->create();
        PaperReceipt::factory()->for($type)->for($imran)->create(['quantity' => 500]);

        $this->actingAs($clerk)->post(route('paper-issues.store'), $this->issuePayload($type, $qadri))->assertSessionHasErrors('quantity');
        $this->actingAs($clerk)->post(route('paper-issues.store'), $this->issuePayload(PaperType::factory()->create(), $imran))->assertSessionHasErrors('quantity');
        $this->assertSame(0, PaperIssue::query()->count());
    }

    public function test_paper_issue_validation(): void
    {
        $clerk = $this->userWith(['paper.issue']);
        $type = PaperType::factory()->create();
        $party = Party::factory()->create();
        PaperReceipt::factory()->for($type)->for($party)->create(['quantity' => 500]);

        $this->actingAs($clerk);
        $this->post(route('paper-issues.store'), [])->assertSessionHasErrors(['issued_on', 'kind', 'party_id', 'paper_type_id', 'quantity', 'issued_to']);
        $this->post(route('paper-issues.store'), $this->issuePayload($type, $party, ['quantity' => 0]))->assertSessionHasErrors('quantity');
        $this->post(route('paper-issues.store'), $this->issuePayload($type, $party, ['quantity' => -5]))->assertSessionHasErrors('quantity');
        $this->post(route('paper-issues.store'), $this->issuePayload($type, $party, ['quantity' => 1.5]))->assertSessionHasErrors('quantity');
        $this->post(route('paper-issues.store'), $this->issuePayload($type, $party, ['kind' => 'stolen']))->assertSessionHasErrors('kind');
        $this->post(route('paper-issues.store'), $this->issuePayload($type, $party, ['wastage_percent' => 51]))->assertSessionHasErrors('wastage_percent');
        $this->post(route('paper-issues.store'), $this->issuePayload($type, $party, ['book_format_id' => 999]))->assertSessionHasErrors('book_format_id');
        $this->assertSame(0, PaperIssue::query()->count());
    }

    /**
     * An empty "Wastage %" box is saved as zero wastage.
     */
    public function test_issuing_with_an_empty_wastage_box_does_not_crash(): void
    {
        $clerk = $this->userWith(['paper.issue']);
        $type = PaperType::factory()->create();
        $party = Party::factory()->create();
        PaperReceipt::factory()->for($type)->for($party)->create(['quantity' => 500]);

        $this->actingAs($clerk)->post(route('paper-issues.store'), $this->issuePayload($type, $party, ['wastage_percent' => '']))
            ->assertRedirect(route('paper.index', ['tab' => 'issues']));

        $this->assertSame('0.00', PaperIssue::query()->sole()->wastage_percent);
    }

    public function test_paper_receipt_validation_edge_cases(): void
    {
        $clerk = $this->userWith(['paper.receive']);
        $type = PaperType::factory()->create();
        $party = Party::factory()->create();
        $godown = Godown::factory()->create();
        $closed = Godown::factory()->create(['is_active' => false]);
        PaperReceipt::factory()->create(['slip_no' => '5531']);

        $this->actingAs($clerk);
        $this->post(route('paper-receipts.store'), [])->assertSessionHasErrors(['slip_no', 'received_at', 'party_id', 'paper_type_id', 'quantity', 'godown_id', 'received_by']);
        $this->post(route('paper-receipts.store'), $this->receiptPayload($type, $party, $godown, ['quantity' => 0]))->assertSessionHasErrors('quantity');
        $this->post(route('paper-receipts.store'), $this->receiptPayload($type, $party, $godown, ['quantity' => -776]))->assertSessionHasErrors('quantity');
        $this->post(route('paper-receipts.store'), $this->receiptPayload($type, $party, $godown, ['quantity' => 'lots']))->assertSessionHasErrors('quantity');
        $this->post(route('paper-receipts.store'), $this->receiptPayload($type, $party, $godown, ['slip_no' => '5531']))->assertSessionHasErrors('slip_no');
        $this->post(route('paper-receipts.store'), $this->receiptPayload($type, $party, $closed))->assertSessionHasErrors('godown_id');
        $this->post(route('paper-receipts.store'), $this->receiptPayload($type, $party, $godown, ['party_id' => 999]))->assertSessionHasErrors('party_id');
        $this->post(route('paper-receipts.store'), $this->receiptPayload($type, $party, $godown, ['received_at' => 'yesterday-ish']))->assertSessionHasErrors('received_at');
        $this->assertSame(1, PaperReceipt::query()->count());
    }

    public function test_receipt_form_lists_only_active_godowns_types_and_paper_parties(): void
    {
        $owner = User::factory()->owner()->create();
        Godown::factory()->create(['name' => 'Godown Open']);
        Godown::factory()->create(['name' => 'Godown Shut', 'is_active' => false]);
        PaperType::factory()->create(['code' => 'ON-1', 'is_active' => true]);
        PaperType::factory()->create(['code' => 'OFF-1', 'is_active' => false]);
        Party::factory()->create(['name' => 'Paper Wala']);
        Party::factory()->kind(PartyKind::Press)->create(['name' => 'Outside Press Co']);

        $this->actingAs($owner)->get(route('paper-receipts.create'))->assertOk()
            ->assertSee('Godown Open')->assertDontSee('Godown Shut')
            ->assertSee('ON-1')->assertDontSee('OFF-1')
            ->assertSee('Paper Wala')->assertDontSee('Outside Press Co');
    }

    public function test_next_slip_number_is_suggested(): void
    {
        $owner = User::factory()->owner()->create();
        PaperReceipt::factory()->create(['slip_no' => '5531']);

        $this->actingAs($owner)->get(route('paper-receipts.create'))->assertViewHas('nextSlip', '5532');
    }

    /**
     * The next slip number follows the highest slip numerically, not alphabetically.
     */
    public function test_next_slip_number_is_numeric_not_alphabetical(): void
    {
        $owner = User::factory()->owner()->create();
        PaperReceipt::factory()->create(['slip_no' => '999']);
        PaperReceipt::factory()->create(['slip_no' => '1000']);

        $this->actingAs($owner)->get(route('paper-receipts.create'))->assertViewHas('nextSlip', '1001');
    }

    /**
     * "RET-WO-…" return slips are ignored when suggesting the next slip number.
     */
    public function test_return_slips_do_not_break_the_next_slip_suggestion(): void
    {
        $owner = User::factory()->owner()->create();
        PaperReceipt::factory()->create(['slip_no' => '5531']);
        PaperReceipt::factory()->create(['slip_no' => 'RET-WO-1001-1', 'created_by' => $owner->id]);

        $this->actingAs($owner)->get(route('paper-receipts.create'))->assertViewHas('nextSlip', '5532');
    }

    public function test_return_slip_from_outside_press_shows_like_any_receipt(): void
    {
        $owner = User::factory()->owner()->create();
        $type = PaperType::factory()->create();
        $party = Party::factory()->create();
        PaperIssue::factory()->for($type)->for($party)->create(['quantity' => 0]);
        $ret = PaperReceipt::factory()->for($type)->for($party)->create(['slip_no' => 'RET-WO-1001-1', 'quantity' => 9, 'created_by' => $owner->id]);

        $this->actingAs($owner)->get(route('paper.index', ['tab' => 'receipts']))->assertOk()->assertSee('RET-WO-1001-1');
        $this->actingAs($owner)->get(route('paper-receipts.show', $ret))->assertOk()->assertSee('RET-WO-1001-1');
        $this->assertSame(9, $this->balance($type, $party));
    }

    // ------------------------------------------------------------ paper types

    public function test_paper_type_create_edit_and_validation(): void
    {
        $clerk = $this->userWith(['paper.types', 'paper.view']);
        PaperType::factory()->create(['code' => 'AA-1975']);

        $this->actingAs($clerk);
        $this->post(route('paper-types.store'), [])->assertSessionHasErrors(['code', 'name', 'size', 'unit', 'sheets_per_unit', 'reorder_level']);
        $this->post(route('paper-types.store'), ['code' => 'AA-1975', 'name' => 'x', 'size' => '1', 'unit' => 'rims', 'sheets_per_unit' => 500, 'reorder_level' => 0])->assertSessionHasErrors('code');
        $this->post(route('paper-types.store'), ['code' => 'BC-1', 'name' => 'x', 'size' => '1', 'unit' => 'sheets', 'sheets_per_unit' => 0, 'reorder_level' => -1, 'gsm' => 0])
            ->assertSessionHasErrors(['unit', 'sheets_per_unit', 'reorder_level', 'gsm']);

        $this->post(route('paper-types.store'), [
            'code' => 'BC-2030', 'name' => 'Bleach card', 'name_ur' => 'بلیچ کارڈ', 'size' => '20 × 30', 'gsm' => 300,
            'unit' => 'packets', 'sheets_per_unit' => 100, 'reorder_level' => 40, 'is_active' => '1',
        ])->assertRedirect(route('paper-types.index'))->assertSessionHasNoErrors();

        $card = PaperType::query()->where('code', 'BC-2030')->sole();
        $this->assertTrue($card->is_active);
        $this->assertSame('packets', $card->unit);

        // Keeping the same code on edit is allowed; deactivating hides it from the receipt form.
        $this->put(route('paper-types.update', $card), [
            'code' => 'BC-2030', 'name' => 'Bleach card', 'size' => '20 × 30', 'unit' => 'packets', 'sheets_per_unit' => 100, 'reorder_level' => 50, 'is_active' => '0',
        ])->assertSessionHasNoErrors();
        $this->assertFalse($card->refresh()->is_active);
        $this->assertSame(50, $card->reorder_level);
        $this->get(route('paper-types.index'))->assertOk()->assertSee('Inactive');
        $this->assertNull(PaperType::query()->active()->find($card->id));
    }

    public function test_reorder_level_drives_the_low_stock_kpi(): void
    {
        $owner = User::factory()->owner()->create();
        $low = PaperType::factory()->create(['name' => 'Offset', 'reorder_level' => 1000]);
        $fine = PaperType::factory()->create(['name' => 'Newsprint', 'reorder_level' => 100]);
        $none = PaperType::factory()->create(['name' => 'Art paper', 'reorder_level' => 0]);
        PaperReceipt::factory()->for($low)->create(['quantity' => 800]);
        PaperReceipt::factory()->for($fine)->create(['quantity' => 800]);

        $this->actingAs($owner)->get(route('paper.index'))->assertOk()
            ->assertViewHas('lowStock', fn (Collection $rows) => $rows->pluck('paper_type.id')->all() === [$low->id])
            ->assertSee('Offset');

        // A type with no reorder level and no stock is never flagged.
        $this->assertSame('ok', app(PaperStock::class)->levelFor($none, 0));
    }

    // ------------------------------------------------------------ godowns

    public function test_godown_crud_and_history_protection(): void
    {
        $clerk = $this->userWith(['godowns.manage', 'paper.view']);

        $this->actingAs($clerk);
        $this->post(route('godowns.store'), [])->assertSessionHasErrors('name');
        $this->post(route('godowns.store'), ['name' => 'Godown C', 'name_ur' => 'گودام سی', 'address' => 'Urdu Bazar', 'is_active' => '1'])
            ->assertRedirect(route('godowns.index'));
        $godown = Godown::query()->where('name', 'Godown C')->sole();
        $this->post(route('godowns.store'), ['name' => 'Godown C'])->assertSessionHasErrors('name');

        $this->put(route('godowns.update', $godown), ['name' => 'Godown C', 'is_active' => '0'])->assertSessionHasNoErrors();
        $this->assertFalse($godown->refresh()->is_active);

        // Unused godown deletes.
        $this->delete(route('godowns.destroy', $godown))->assertRedirect(route('godowns.index'));
        $this->assertModelMissing($godown);

        // A godown with history (even a rejected slip) cannot be deleted.
        $used = Godown::factory()->create(['name' => 'Godown A']);
        PaperReceipt::factory()->for($used)->create(['status' => ApprovalStatus::Rejected]);
        $this->from(route('godowns.index'))->delete(route('godowns.destroy', $used))
            ->assertRedirect(route('godowns.index'))->assertSessionHasErrors('godown');
        $this->assertModelExists($used);
        $this->get(route('godowns.index'))->assertOk()->assertDontSee('action="'.route('godowns.destroy', $used).'"', false);
    }

    // ------------------------------------------------------------ inventory

    public function test_category_crud_and_bills_only_categories(): void
    {
        $clerk = $this->userWith(['inventory.manage', 'purchases.view']);

        $this->actingAs($clerk);
        $this->post(route('inventory-categories.store'), [])->assertSessionHasErrors('name');
        $this->post(route('inventory-categories.store'), ['name' => 'Varnish', 'tracks_stock' => '1', 'is_active' => '1'])->assertRedirect(route('inventory-categories.index'));
        $this->post(route('inventory-categories.store'), ['name' => 'Varnish'])->assertSessionHasErrors('name');
        // The migration seeds Paper (bills only), Ink, Plates, Glue, Machine parts and Other.
        $this->post(route('inventory-categories.store'), ['name' => 'Ink'])->assertSessionHasErrors('name');
        $this->post(route('inventory-categories.store'), ['name' => 'Paper offcuts', 'tracks_stock' => '0', 'is_active' => '1']);

        $paper = InventoryCategory::query()->where('name', 'Paper offcuts')->sole();
        $this->assertFalse($paper->tracks_stock);
        $this->get(route('inventory-categories.index'))->assertOk()->assertSee('Bills only')->assertSee('Tracked here');

        $this->put(route('inventory-categories.update', $paper), ['name' => 'Paper bills', 'tracks_stock' => '0', 'is_active' => '0'])->assertSessionHasNoErrors();
        $this->assertSame('Paper bills', $paper->refresh()->name);
        $this->assertFalse($paper->is_active);

        $this->delete(route('inventory-categories.destroy', $paper))->assertRedirect(route('inventory-categories.index'));
        $this->assertModelMissing($paper);

        $ink = InventoryCategory::query()->where('name', 'Varnish')->sole();
        InventoryItem::factory()->for($ink, 'category')->create();
        $this->from(route('inventory-categories.index'))->delete(route('inventory-categories.destroy', $ink))->assertSessionHasErrors('category');
        $this->assertModelExists($ink);
    }

    public function test_item_crud_and_validation(): void
    {
        $clerk = $this->userWith(['inventory.manage', 'purchases.view']);
        $ink = InventoryCategory::query()->firstOrCreate(['name' => 'Ink'], ['tracks_stock' => true]);
        $glue = InventoryCategory::query()->firstOrCreate(['name' => 'Glue'], ['tracks_stock' => true]);

        $this->actingAs($clerk);
        $this->post(route('inventory-items.store'), [])->assertSessionHasErrors(['inventory_category_id', 'name', 'unit']);
        $this->post(route('inventory-items.store'), ['inventory_category_id' => $ink->id, 'name' => 'Black ink', 'unit' => 'kg', 'reorder_level' => -1])->assertSessionHasErrors('reorder_level');

        $this->post(route('inventory-items.store'), ['inventory_category_id' => $ink->id, 'name' => 'Black ink', 'unit' => 'kg', 'reorder_level' => '', 'is_active' => '1'])
            ->assertSessionHasNoErrors();
        $item = InventoryItem::query()->sole();
        $this->assertSame('0.00', $item->reorder_level);

        // Same name in the same category is refused, but allowed in another category.
        $this->post(route('inventory-items.store'), ['inventory_category_id' => $ink->id, 'name' => 'Black ink', 'unit' => 'kg'])
            ->assertSessionHasErrors(['name' => 'This category already has an item with that name.']);
        $this->post(route('inventory-items.store'), ['inventory_category_id' => $glue->id, 'name' => 'Black ink', 'unit' => 'kg'])->assertSessionHasNoErrors();

        $this->put(route('inventory-items.update', $item), ['inventory_category_id' => $ink->id, 'name' => 'Black ink', 'unit' => 'kg', 'reorder_level' => 12.5, 'is_active' => '1'])
            ->assertRedirect(route('inventory-items.show', $item));
        $this->assertSame('12.50', $item->refresh()->reorder_level);

        $this->delete(route('inventory-items.destroy', $item))->assertRedirect(route('inventory.index'));
        $this->assertModelMissing($item);
    }

    public function test_full_purchase_approve_move_out_flow_with_running_ledger(): void
    {
        $owner = User::factory()->owner()->create();
        $clerk = $this->userWith(['purchases.view', 'purchases.create', 'inventory.issue']);
        $item = InventoryItem::factory()->create(['name' => 'Black ink', 'unit' => 'kg', 'reorder_level' => 10]);
        $supplier = Party::factory()->kind(PartyKind::Supplier)->create(['name' => 'Ink House']);

        // Clerk buys 25 kg at 740.50 → Rs 18,513 (rounded), waits for approval.
        $this->actingAs($clerk)->post(route('purchases.store'), [
            'purchased_on' => today()->toDateString(), 'party_id' => $supplier->id, 'inventory_item_id' => $item->id,
            'quantity' => 25, 'rate' => 740.5, 'bill_no' => 'B-1',
        ])->assertRedirect(route('inventory.index', ['tab' => 'purchases']))
            ->assertSessionHas('status', 'Purchase saved and sent for approval.');

        $purchase = Purchase::query()->sole();
        $this->assertSame(18513, $purchase->amount);
        $this->assertSame(ApprovalStatus::Pending, $purchase->status);
        $this->assertSame(25.0, $item->refresh()->balance());

        $this->actingAs($clerk)->get(route('inventory.index'))->assertOk()
            ->assertViewHas('pendingTotal', 18513)->assertViewHas('pendingCount', 1)->assertViewHas('monthTotal', 18513);

        $this->actingAs($owner)->get(route('approvals.index'))->assertOk()->assertSee('Black ink — Ink House');
        $this->actingAs($owner)->patch(route('approvals.update', ['purchase', $purchase->id]), ['decision' => 'approve'])->assertSessionHasNoErrors();
        $this->assertSame(ApprovalStatus::Approved, $purchase->refresh()->status);

        // Move out in decimals.
        $this->actingAs($clerk)->post(route('inventory-issues.store'), [
            'issued_on' => today()->toDateString(), 'inventory_item_id' => $item->id, 'quantity' => 12.75, 'issued_to' => 'Machine P-01', 'reference' => 'WO-1',
        ])->assertRedirect(route('inventory.index', ['tab' => 'issues']))
            ->assertSessionHas('status', '12.75 kg Black ink moved out to Machine P-01.');

        $this->assertSame(12.25, $item->refresh()->balance());
        $this->assertSame('ok', $item->level());

        $this->actingAs($clerk)->post(route('inventory-issues.store'), [
            'issued_on' => today()->toDateString(), 'inventory_item_id' => $item->id, 'quantity' => 12.26, 'issued_to' => 'Machine P-01',
        ])->assertSessionHasErrors(['quantity' => 'Only 12.25 kg in stock.']);

        $this->actingAs($clerk)->post(route('inventory-issues.store'), [
            'issued_on' => today()->toDateString(), 'inventory_item_id' => $item->id, 'quantity' => 3, 'issued_to' => 'Binders',
        ])->assertSessionHasNoErrors();
        $this->assertSame(9.25, $item->refresh()->balance());
        $this->assertSame('low', $item->level());

        $this->actingAs($clerk)->get(route('inventory.index'))->assertOk()
            ->assertSee('1 item is low or out of stock.')
            ->assertViewHas('issuedThisMonth', 2);

        $this->actingAs($clerk)->get(route('inventory-items.show', $item))->assertOk()
            ->assertViewHas('ledger', fn (Collection $ledger) => $ledger->pluck('balance')->all() === [9.25, 12.25, 25.0]);

        // Use it up exactly → "out", and the item can no longer be deleted.
        $this->actingAs($clerk)->post(route('inventory-issues.store'), [
            'issued_on' => today()->toDateString(), 'inventory_item_id' => $item->id, 'quantity' => 9.25, 'issued_to' => 'Binders',
        ])->assertSessionHasNoErrors();
        $this->assertSame('out', $item->refresh()->level());

        $manager = $this->userWith(['inventory.manage', 'purchases.view']);
        $this->actingAs($manager)->from(route('inventory-items.show', $item))->delete(route('inventory-items.destroy', $item))
            ->assertSessionHasErrors('item');
        $this->assertModelExists($item);
    }

    public function test_purchase_and_move_out_validation(): void
    {
        $owner = User::factory()->owner()->create();
        $supplier = Party::factory()->kind(PartyKind::Supplier)->create();
        $item = InventoryItem::factory()->create();
        $inactive = InventoryItem::factory()->create(['is_active' => false]);
        Purchase::factory()->for($inactive, 'item')->create(['status' => ApprovalStatus::Approved]);
        $base = ['purchased_on' => today()->toDateString(), 'party_id' => $supplier->id, 'inventory_item_id' => $item->id, 'quantity' => 1, 'rate' => 10];

        $this->actingAs($owner);
        $this->post(route('purchases.store'), [])->assertSessionHasErrors(['purchased_on', 'party_id', 'inventory_item_id', 'quantity', 'rate']);
        $this->post(route('purchases.store'), ['quantity' => 0] + $base)->assertSessionHasErrors('quantity');
        $this->post(route('purchases.store'), ['quantity' => -3] + $base)->assertSessionHasErrors('quantity');
        $this->post(route('purchases.store'), ['rate' => -1] + $base)->assertSessionHasErrors('rate');
        $this->post(route('purchases.store'), ['inventory_item_id' => $inactive->id] + $base)->assertSessionHasErrors('inventory_item_id');
        $this->assertSame(1, Purchase::query()->count());

        // A free sample at rate 0 is fine.
        $this->post(route('purchases.store'), ['rate' => 0] + $base)->assertSessionHasNoErrors()->assertSessionHas('status', 'Purchase saved.');

        $issue = ['issued_on' => today()->toDateString(), 'inventory_item_id' => $item->id, 'quantity' => 1, 'issued_to' => 'Machine'];
        $this->post(route('inventory-issues.store'), [])->assertSessionHasErrors(['issued_on', 'inventory_item_id', 'quantity', 'issued_to']);
        $this->post(route('inventory-issues.store'), ['quantity' => 0] + $issue)->assertSessionHasErrors('quantity');
        $this->post(route('inventory-issues.store'), ['quantity' => -1] + $issue)->assertSessionHasErrors('quantity');
        $this->post(route('inventory-issues.store'), ['quantity' => 2] + $issue)->assertSessionHasErrors('quantity');
        $this->post(route('inventory-issues.store'), ['inventory_item_id' => $inactive->id] + $issue)->assertSessionHasErrors('inventory_item_id');
        $this->post(route('inventory-issues.store'), ['machine_id' => 999] + $issue)->assertSessionHasErrors('machine_id');
        $this->post(route('inventory-issues.store'), $issue)->assertSessionHasNoErrors();
        $this->assertSame(0.0, $item->refresh()->balance());
    }

    public function test_rejected_purchase_leaves_stock_and_totals_but_stays_on_the_ledger(): void
    {
        $owner = User::factory()->owner()->create();
        $clerk = $this->userWith(['purchases.view', 'purchases.create']);
        $item = InventoryItem::factory()->create();
        $supplier = Party::factory()->kind(PartyKind::Supplier)->create();

        $this->actingAs($clerk)->post(route('purchases.store'), [
            'purchased_on' => today()->toDateString(), 'party_id' => $supplier->id, 'inventory_item_id' => $item->id, 'quantity' => 10, 'rate' => 100,
        ]);
        $purchase = Purchase::query()->sole();
        $this->actingAs($owner)->patch(route('approvals.update', ['purchase', $purchase->id]), ['decision' => 'reject', 'note' => 'Overpriced']);

        $this->assertSame(0.0, $item->refresh()->balance());
        $this->actingAs($clerk)->get(route('inventory.index', ['tab' => 'purchases']))->assertOk()
            ->assertSee('Overpriced')->assertViewHas('monthTotal', 0)->assertViewHas('pendingTotal', 0);
        $this->actingAs($clerk)->get(route('inventory-items.show', $item))->assertOk()
            ->assertViewHas('ledger', fn (Collection $l) => $l->sole()['counts'] === false && $l->sole()['balance'] === 0.0);
    }

    /**
     * A purchase whose stock was already moved out cannot be sent back until the issue is reversed.
     */
    public function test_rejecting_a_purchase_whose_stock_was_moved_out_must_not_leave_negative_stock(): void
    {
        $owner = User::factory()->owner()->create();
        $clerk = $this->userWith(['purchases.view', 'purchases.create', 'inventory.issue']);
        $item = InventoryItem::factory()->create();
        $supplier = Party::factory()->kind(PartyKind::Supplier)->create();

        $this->actingAs($clerk)->post(route('purchases.store'), [
            'purchased_on' => today()->toDateString(), 'party_id' => $supplier->id, 'inventory_item_id' => $item->id, 'quantity' => 10, 'rate' => 100,
        ]);
        $this->actingAs($clerk)->post(route('inventory-issues.store'), [
            'issued_on' => today()->toDateString(), 'inventory_item_id' => $item->id, 'quantity' => 10, 'issued_to' => 'Machine',
        ])->assertSessionHasNoErrors();

        $this->actingAs($owner)->patch(route('approvals.update', ['purchase', Purchase::query()->sole()->id]), ['decision' => 'reject', 'note' => 'Wrong bill']);

        $this->assertGreaterThanOrEqual(0.0, $item->refresh()->balance(), 'Rejecting a purchase after its stock was moved out leaves a negative balance.');
    }

    public function test_bills_only_category_items_stay_off_stock_and_move_out_screens(): void
    {
        $owner = User::factory()->owner()->create();
        $paperBills = InventoryCategory::query()->where('name', 'Paper')->where('tracks_stock', false)->sole();
        $paperItem = InventoryItem::factory()->for($paperBills, 'category')->create(['name' => 'Offset 20 × 30', 'unit' => 'rims']);
        Purchase::factory()->for($paperItem, 'item')->create(['created_by' => $owner->id, 'quantity' => 776, 'rate' => 5000, 'amount' => 3880000]);

        $this->actingAs($owner);
        $this->get(route('inventory.index'))->assertOk()->assertDontSee('Offset 20 × 30')->assertViewHas('items', fn ($items) => $items->isEmpty());
        $this->get(route('inventory.index', ['tab' => 'purchases']))->assertOk()->assertSee('Offset 20 × 30')->assertViewHas('monthTotal', 3880000);
        $this->get(route('inventory-categories.index'))->assertOk()->assertSee('Rs 3,880,000');
        $this->get(route('inventory-issues.create'))->assertOk()->assertDontSee('Offset 20 × 30');
        $this->get(route('purchases.create'))->assertOk()->assertSee('Offset 20 × 30');

        // Bills-only paper is not mixed into the paper godown stock.
        $this->assertSame(0, PaperReceipt::query()->count());
    }

    /**
     * Items of bills-only categories are refused by the move-out request.
     */
    public function test_bills_only_items_cannot_be_moved_out(): void
    {
        $owner = User::factory()->owner()->create();
        $paperBills = InventoryCategory::query()->where('name', 'Paper')->where('tracks_stock', false)->sole();
        $paperItem = InventoryItem::factory()->for($paperBills, 'category')->create(['name' => 'Offset 20 × 30']);
        Purchase::factory()->for($paperItem, 'item')->create(['created_by' => $owner->id, 'quantity' => 10]);

        $this->actingAs($owner)->post(route('inventory-issues.store'), [
            'issued_on' => today()->toDateString(), 'inventory_item_id' => $paperItem->id, 'quantity' => 5, 'issued_to' => 'Machine',
        ])->assertSessionHasErrors('inventory_item_id');
    }

    /**
     * The item page of a bills-only item offers no "Move out" button.
     */
    public function test_bills_only_item_page_offers_no_move_out_button(): void
    {
        $owner = User::factory()->owner()->create();
        $paperBills = InventoryCategory::query()->where('name', 'Paper')->where('tracks_stock', false)->sole();
        $paperItem = InventoryItem::factory()->for($paperBills, 'category')->create(['name' => 'Offset 20 × 30']);
        Purchase::factory()->for($paperItem, 'item')->create(['created_by' => $owner->id, 'quantity' => 10]);

        $this->actingAs($owner)->get(route('inventory-issues.create', ['item' => $paperItem->id]))->assertOk()->assertDontSee('Offset 20 × 30');
        $this->actingAs($owner)->get(route('inventory-items.show', $paperItem))->assertOk()
            ->assertDontSee(route('inventory-issues.create', ['item' => $paperItem->id]));
    }

    public function test_category_filter_scopes_every_inventory_tab_and_total(): void
    {
        $owner = User::factory()->owner()->create();
        $inkItem = InventoryItem::factory()->create(['name' => 'Black ink']);
        $glueItem = InventoryItem::factory()->create(['name' => 'Binding glue']);
        Purchase::factory()->for($inkItem, 'item')->create(['created_by' => $owner->id, 'amount' => 1000]);
        Purchase::factory()->for($glueItem, 'item')->create(['created_by' => $owner->id, 'amount' => 500]);
        InventoryIssue::factory()->for($glueItem, 'item')->create(['quantity' => 1]);

        $this->actingAs($owner)->get(route('inventory.index', ['category' => $inkItem->inventory_category_id]))->assertOk()
            ->assertViewHas('items', fn ($items) => $items->pluck('id')->all() === [$inkItem->id])
            ->assertViewHas('monthTotal', 1000)
            ->assertViewHas('issueCount', 0)
            ->assertViewHas('purchaseCount', 1);

        $this->actingAs($owner)->get(route('inventory.index'))->assertViewHas('monthTotal', 1500)->assertViewHas('issueCount', 1);
    }

    public function test_purchase_from_a_month_ago_does_not_count_this_month(): void
    {
        $owner = User::factory()->owner()->create();
        Purchase::factory()->create(['created_by' => $owner->id, 'amount' => 700, 'purchased_on' => now()->startOfMonth()->subDay()]);
        Purchase::factory()->create(['created_by' => $owner->id, 'amount' => 300]);

        $this->actingAs($owner)->get(route('inventory.index'))->assertViewHas('monthTotal', 300);
    }
}
