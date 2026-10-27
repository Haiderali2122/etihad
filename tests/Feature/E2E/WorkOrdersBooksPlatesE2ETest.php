<?php

namespace Tests\Feature\E2E;

use App\Enums\ApprovalStatus;
use App\Enums\PartyKind;
use App\Enums\WorkOrderStage;
use App\Models\Book;
use App\Models\BookFormat;
use App\Models\Godown;
use App\Models\Machine;
use App\Models\OutsidePrintReceipt;
use App\Models\PaperIssue;
use App\Models\PaperReceipt;
use App\Models\PaperType;
use App\Models\Party;
use App\Models\PlateJob;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkOrder;
use App\Support\PaperStock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * End-to-end walk through work orders, books & formats and plate jobs over HTTP.
 */
class WorkOrdersBooksPlatesE2ETest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $manager;

    private PaperType $paper;

    private Party $party;

    private Godown $godown;

    private BookFormat $format;

    private Machine $machine;

    private Party $press;

    private Party $buyer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->owner()->create(['name' => 'Owner']);
        $this->manager = User::factory()->create(['name' => 'Manager']);
        $this->paper = PaperType::factory()->create(['name' => 'AA printing', 'sheets_per_unit' => 500]);
        $this->party = Party::factory()->create(['name' => 'Ilmi Kutub Khana']);
        $this->godown = Godown::factory()->create();
        $this->receive(1000);
        $this->format = BookFormat::factory()->create(['forms' => 10, 'book_id' => Book::factory()->create(['name' => 'Urdu Reader Class 5'])->id]);
        $this->machine = Machine::factory()->create(['code' => 'P-01']);
        $this->press = Party::factory()->kind(PartyKind::Press)->create(['name' => 'Al-Noor Press']);
        $this->buyer = Party::factory()->kind(PartyKind::Buyer)->create(['name' => 'Kitab Ghar']);
    }

    private function receive(int $rims, ?PaperType $paper = null, ?Party $party = null): PaperReceipt
    {
        return PaperReceipt::factory()->create([
            'paper_type_id' => ($paper ?? $this->paper)->id, 'party_id' => ($party ?? $this->party)->id,
            'godown_id' => $this->godown->id, 'quantity' => $rims, 'created_by' => $this->owner->id,
        ]);
    }

    private function stock(): int
    {
        return app(PaperStock::class)->available($this->paper->id, $this->party->id);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function order(array $overrides = []): array
    {
        return $overrides + [
            'ordered_on' => today()->toDateString(), 'book_format_id' => $this->format->id, 'copies' => 18000, 'wastage_percent' => 3,
            'paper_type_id' => $this->paper->id, 'party_id' => $this->party->id, 'mode' => 'in_house', 'machine_id' => $this->machine->id,
            'buyer_id' => $this->buyer->id, 'due_on' => today()->addWeek()->toDateString(), 'note' => 'Board order',
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function outsourceOrder(array $overrides = []): array
    {
        return $this->order($overrides + ['mode' => 'outsource', 'press_id' => $this->press->id, 'machine_id' => null]);
    }

    private function limitedUser(array $permissions): User
    {
        return User::factory()->create(['role_id' => Role::factory()->create(['permissions' => $permissions])->id]);
    }

    private function decide(WorkOrder $order, string $decision, ?string $note = null): TestResponse
    {
        return $this->actingAs($this->owner)
            ->from(route('approvals.index'))
            ->patch(route('approvals.update', ['work-order', $order->id]), array_filter(['decision' => $decision, 'note' => $note]));
    }

    // ---------------------------------------------------------------- pages

    public function test_owner_can_open_every_screen_of_the_module(): void
    {
        $this->actingAs($this->owner)->post(route('work-orders.store'), $this->order())->assertSessionHasNoErrors();
        $this->actingAs($this->owner)->post(route('work-orders.store'), $this->outsourceOrder(['copies' => 1000]))->assertSessionHasNoErrors();
        $this->actingAs($this->manager)->post(route('work-orders.store'), $this->order(['copies' => 500]))->assertSessionHasNoErrors();
        $this->actingAs($this->manager)->post(route('work-orders.store'), $this->order(['copies' => 600]))->assertSessionHasNoErrors();
        [$inHouse, $outsource, $pending, $rejected] = WorkOrder::query()->orderBy('id')->get()->all();
        $this->decide($rejected, 'reject', 'Wrong book')->assertSessionHasNoErrors();
        $plate = PlateJob::factory()->create(['work_order_id' => $inHouse->id]);

        $this->actingAs($this->owner);
        $this->get(route('work-orders.index'))->assertOk()->assertSee($inHouse->number)->assertSee($outsource->number)->assertSee($pending->number)->assertSee($rejected->number);
        $this->get(route('work-orders.create'))->assertOk()->assertSee('WO-1005')->assertSee('Urdu Reader Class 5');
        $this->get(route('work-orders.create', ['format' => $this->format->id]))->assertOk();
        $this->get(route('work-orders.show', $inHouse))->assertOk()->assertSee('371')->assertSee('Move to Folding')->assertSee('Kitab Ghar');
        $this->get(route('work-orders.show', $outsource))->assertOk()->assertSee('Outside printing')->assertSee('Record receipt')->assertSee('Al-Noor Press');
        $this->get(route('work-orders.show', $pending))->assertOk()->assertSee('Waiting for the owner')->assertDontSee('Move to');
        $this->get(route('work-orders.show', $rejected))->assertOk()->assertSee('Sent back')->assertSee('Wrong book');

        $book = $this->format->book;
        $this->get(route('books.index'))->assertOk()->assertSee('Urdu Reader Class 5');
        $this->get(route('books.index', ['q' => 'Reader']))->assertOk()->assertSee('Urdu Reader Class 5');
        $this->get(route('books.index', ['q' => 'zzz-nothing']))->assertOk()->assertDontSee('Urdu Reader Class 5');
        $this->get(route('books.create'))->assertOk();
        $this->get(route('books.show', $book))->assertOk()->assertSee('Standard');
        $this->get(route('books.edit', $book))->assertOk();

        foreach (['unpaid', 'paid', 'all', 'bogus'] as $show) {
            $this->get(route('plate-jobs.index', ['show' => $show]))->assertOk();
        }
        $this->get(route('plate-jobs.index'))->assertOk()->assertSee($inHouse->number);
        $this->get(route('plate-jobs.create'))->assertOk();
        $this->get(route('plate-jobs.create', ['order' => $inHouse->id]))->assertOk()->assertSee($inHouse->number);
        $this->assertNotNull($plate->id);
    }

    public function test_pages_render_in_urdu(): void
    {
        $urdu = User::factory()->owner()->create(['locale' => 'ur']);
        $this->actingAs($urdu)->post(route('work-orders.store'), $this->order())->assertSessionHasNoErrors();
        $this->actingAs($urdu)->post(route('work-orders.store'), $this->outsourceOrder(['copies' => 100]))->assertSessionHasNoErrors();
        $this->actingAs($this->manager)->post(route('work-orders.store'), $this->order(['copies' => 100]))->assertSessionHasNoErrors();
        PlateJob::factory()->create(['work_order_id' => WorkOrder::query()->first()->id]);
        [$order, $outsource, $pending] = WorkOrder::query()->orderBy('id')->get()->all();

        $this->actingAs($urdu);
        $this->get(route('work-orders.index'))->assertOk()->assertSee('ورک آرڈر');
        $this->get(route('work-orders.create'))->assertOk();
        $this->get(route('work-orders.show', $order))->assertOk()->assertSee('پرنٹنگ');
        $this->get(route('work-orders.show', $outsource))->assertOk();
        $this->get(route('work-orders.show', $pending))->assertOk()->assertSee('مالک کی منظوری کا انتظار۔');
        $this->get(route('books.index'))->assertOk();
        $this->get(route('books.create'))->assertOk();
        $this->get(route('books.show', $this->format->book))->assertOk();
        $this->get(route('books.edit', $this->format->book))->assertOk();
        $this->get(route('plate-jobs.index', ['show' => 'all']))->assertOk();
        $this->get(route('plate-jobs.create'))->assertOk();

        // Switching from English via the top bar link sticks for the session.
        $this->actingAs($this->owner)->get(route('locale', 'ur'))->assertRedirect();
        $this->get(route('work-orders.index'))->assertOk()->assertSee('ورک آرڈر');
        $this->assertSame('ur', $this->owner->refresh()->locale);

        $this->actingAs($urdu)->post(route('books.store'), ['name' => 'Islamiyat 3', 'formats' => [['name' => 'A', 'forms' => 4]]])
            ->assertSessionHas('status', 'کتاب محفوظ ہو گئی۔');
    }

    // ---------------------------------------------------------------- permissions

    public function test_viewer_role_can_look_but_not_change_anything(): void
    {
        $this->actingAs($this->owner)->post(route('work-orders.store'), $this->order())->assertSessionHasNoErrors();
        $this->actingAs($this->owner)->post(route('work-orders.store'), $this->outsourceOrder(['copies' => 100]))->assertSessionHasNoErrors();
        [$order, $outsource] = WorkOrder::query()->orderBy('id')->get()->all();
        $plate = PlateJob::factory()->create(['work_order_id' => $order->id]);
        $book = $this->format->book;
        $viewer = $this->limitedUser(['orders.view', 'books.view']);

        $this->actingAs($viewer);
        $this->get(route('work-orders.index'))->assertOk();
        $this->get(route('work-orders.show', $order))->assertOk()->assertDontSee('Move to Folding');
        $this->get(route('work-orders.show', $outsource))->assertOk()->assertDontSee('Record receipt');
        $this->get(route('books.index'))->assertOk();
        $this->get(route('books.show', $book))->assertOk();
        $this->get(route('plate-jobs.index'))->assertOk();

        $this->get(route('work-orders.create'))->assertForbidden();
        $this->post(route('work-orders.store'), $this->order())->assertForbidden();
        $this->patch(route('work-orders.advance', $order))->assertForbidden();
        $this->post(route('work-orders.outside-receipts.store', $outsource), ['received_on' => today()->toDateString(), 'printed_copies' => 1])->assertForbidden();
        $this->delete(route('work-orders.destroy', $order))->assertForbidden();
        $this->get(route('books.create'))->assertForbidden();
        $this->post(route('books.store'), ['name' => 'X', 'formats' => [['name' => 'A', 'forms' => 1]]])->assertForbidden();
        $this->get(route('books.edit', $book))->assertForbidden();
        $this->put(route('books.update', $book), ['name' => 'X', 'formats' => [['name' => 'A', 'forms' => 1]]])->assertForbidden();
        $this->delete(route('books.destroy', $book))->assertForbidden();
        $this->get(route('plate-jobs.create'))->assertForbidden();
        $this->post(route('plate-jobs.store'), [])->assertForbidden();
        $this->patch(route('plate-jobs.pay', $plate), ['paid_on' => today()->toDateString()])->assertForbidden();
        $this->get(route('approvals.index'))->assertForbidden();
        $this->patch(route('approvals.update', ['work-order', $order->id]), ['decision' => 'approve'])->assertForbidden();

        $this->assertSame(WorkOrderStage::Printing, $order->refresh()->stage);
        $this->assertSame('Urdu Reader Class 5', $book->refresh()->name);
        $this->assertNull($plate->refresh()->paid_on);
    }

    public function test_role_without_production_permissions_is_shut_out(): void
    {
        $order = WorkOrder::factory()->create();
        $nobody = $this->limitedUser(['paper.view']);

        $this->actingAs($nobody);
        $this->get(route('work-orders.index'))->assertForbidden();
        $this->get(route('work-orders.show', $order))->assertForbidden();
        $this->get(route('books.index'))->assertForbidden();
        $this->get(route('books.show', $this->format->book))->assertForbidden();
        $this->get(route('plate-jobs.index'))->assertForbidden();
    }

    public function test_plates_role_can_record_plates_but_not_orders(): void
    {
        $platesOnly = $this->limitedUser(['orders.view', 'plates.manage']);
        $processor = Party::factory()->kind(PartyKind::PlateProcessor)->create();

        $this->actingAs($platesOnly)->get(route('plate-jobs.create'))->assertOk();
        $this->post(route('plate-jobs.store'), ['party_id' => $processor->id, 'job_on' => today()->toDateString(), 'plates' => 4, 'rate' => 100])
            ->assertRedirect(route('plate-jobs.index'));
        $this->get(route('work-orders.create'))->assertForbidden();
        $this->get(route('books.index'))->assertForbidden();
    }

    public function test_guests_are_sent_to_login(): void
    {
        $this->get(route('work-orders.index'))->assertRedirect(route('login'));
        $this->get(route('books.index'))->assertRedirect(route('login'));
        $this->get(route('plate-jobs.index'))->assertRedirect(route('login'));
    }

    // ---------------------------------------------------------------- lifecycle

    public function test_in_house_order_full_lifecycle_from_manager_entry_to_dispatch(): void
    {
        $this->assertSame(1000, $this->stock());

        $this->actingAs($this->manager)->post(route('work-orders.store'), $this->order())
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'WO-1001 saved and sent for approval. Paper is issued once it is approved.');
        $order = WorkOrder::query()->sole();
        $this->assertSame(ApprovalStatus::Pending, $order->status);
        $this->assertSame(WorkOrderStage::Waiting, $order->stage);
        $this->assertSame(371, $order->rims_needed);
        $this->assertSame('10.00', $order->forms);
        $this->assertNull($order->paper_issue_id);
        $this->assertSame(1000, $this->stock(), 'A waiting order must not touch stock.');

        // A waiting order cannot be moved on.
        $this->actingAs($this->manager)->patch(route('work-orders.advance', $order))->assertStatus(409);

        // The manager cannot approve; the order shows in the owner's queue.
        $this->actingAs($this->manager)->patch(route('approvals.update', ['work-order', $order->id]), ['decision' => 'approve'])->assertForbidden();
        $this->actingAs($this->owner)->get(route('approvals.index'))->assertOk()->assertSee('WO-1001');

        $this->decide($order, 'approve')->assertSessionHasNoErrors()->assertRedirect(route('approvals.index'));
        $order->refresh();
        $this->assertSame(ApprovalStatus::Approved, $order->status);
        $this->assertSame($this->owner->id, $order->reviewed_by);
        $this->assertSame(WorkOrderStage::Printing, $order->stage);
        $this->assertSame(629, $this->stock());

        $issue = PaperIssue::query()->sole();
        $this->assertSame(371, $issue->quantity);
        $this->assertSame('WO-1001', $issue->reference);
        $this->assertSame('Urdu Reader Class 5', $issue->book_name);
        $this->assertSame('Machine P-01', $issue->issued_to);
        $this->assertSame($this->owner->id, $issue->created_by);

        // Deciding again is refused and nothing more is issued.
        $this->decide($order, 'approve')->assertStatus(409);
        $this->assertSame(1, PaperIssue::query()->count());

        $show = route('work-orders.show', $order);
        foreach ([WorkOrderStage::Folding, WorkOrderStage::Binding, WorkOrderStage::Ready, WorkOrderStage::Dispatched] as $stage) {
            $this->actingAs($this->manager)->from($show)->patch(route('work-orders.advance', $order))
                ->assertRedirect($show)->assertSessionHas('status', "WO-1001 moved to {$stage->label()}.");
            $this->assertSame($stage, $order->refresh()->stage);
        }

        $this->actingAs($this->manager)->patch(route('work-orders.advance', $order))->assertStatus(409);
        $this->assertSame(629, $this->stock(), 'Moving through stages must not issue paper again.');
        $this->assertSame(1, PaperIssue::query()->count());

        $this->actingAs($this->owner)->get(route('work-orders.index'))->assertOk()->assertSee('WO-1001');
        $this->get($show)->assertOk()->assertDontSee('Move to');
    }

    public function test_owner_order_is_approved_and_issued_on_save(): void
    {
        $this->actingAs($this->owner)->post(route('work-orders.store'), $this->order())
            ->assertSessionHas('status', 'WO-1001 saved; 371 rims issued from stock.');

        $order = WorkOrder::query()->sole();
        $this->assertSame(ApprovalStatus::Approved, $order->status);
        $this->assertSame(WorkOrderStage::Printing, $order->stage);
        $this->assertSame(629, $this->stock());
        $this->actingAs($this->owner)->flushSession()->get(route('approvals.index'))->assertOk()->assertDontSee('WO-1001');
    }

    public function test_rejected_order_leaves_stock_and_cannot_be_approved_later(): void
    {
        $this->actingAs($this->manager)->post(route('work-orders.store'), $this->order())->assertSessionHasNoErrors();
        $order = WorkOrder::query()->sole();

        $this->decide($order, 'reject')->assertSessionHasErrors('note');
        $this->assertTrue($order->refresh()->isPending());

        $this->decide($order, 'reject', 'Check the copies')->assertSessionHasNoErrors();
        $order->refresh();
        $this->assertSame(ApprovalStatus::Rejected, $order->status);
        $this->assertSame(WorkOrderStage::Waiting, $order->stage);
        $this->assertNull($order->paper_issue_id);
        $this->assertSame(1000, $this->stock());

        // There is no way to re-submit: a sent-back order stays sent back.
        $this->decide($order, 'approve')->assertStatus(409);
        $this->assertSame(1000, $this->stock());
        $this->actingAs($this->manager)->patch(route('work-orders.advance', $order))->assertStatus(409);

        $this->actingAs($this->owner)->get(route('work-orders.show', $order))->assertOk()->assertSee('Check the copies');

        // A sent-back order can be deleted since nothing left the godown.
        $this->actingAs($this->manager)->delete(route('work-orders.destroy', $order))->assertRedirect(route('work-orders.index'));
        $this->assertModelMissing($order);
        $this->assertSame(1000, $this->stock());
    }

    public function test_approval_is_blocked_when_stock_runs_short_and_allowed_once_paper_arrives(): void
    {
        // 10 forms × 30,000 ÷ 500 = 600 rims, no wastage; both pass the save-time check against 1000.
        $this->actingAs($this->manager)->post(route('work-orders.store'), $this->order(['copies' => 30000, 'wastage_percent' => 0]))->assertSessionHasNoErrors();
        $this->actingAs($this->manager)->post(route('work-orders.store'), $this->order(['copies' => 30000, 'wastage_percent' => 0]))->assertSessionHasNoErrors();
        [$first, $second] = WorkOrder::query()->orderBy('id')->get()->all();
        $this->assertSame(['WO-1001', 'WO-1002'], [$first->number, $second->number]);

        $this->decide($first, 'approve')->assertSessionHasNoErrors();
        $this->assertSame(400, $this->stock());

        $this->decide($second, 'approve')->assertRedirect(route('approvals.index'))
            ->assertSessionHasErrors(['decision' => 'Only 400 rims of this paper are in stock for Ilmi Kutub Khana; the order needs 600.']);
        $this->assertTrue($second->refresh()->isPending());
        $this->assertNull($second->paper_issue_id);
        $this->assertSame(400, $this->stock());

        // Rejecting is still possible while blocked.
        // Instead, more paper arrives and the owner approves.
        $this->receive(200);
        $this->decide($second, 'approve')->assertSessionHasNoErrors();
        $this->assertSame(WorkOrderStage::Printing, $second->refresh()->stage);
        $this->assertSame(0, $this->stock());
    }

    public function test_paper_of_another_party_or_type_does_not_cover_an_order(): void
    {
        $otherParty = Party::factory()->create();
        $otherPaper = PaperType::factory()->create();
        $this->receive(5000, $this->paper, $otherParty);
        $this->receive(5000, $otherPaper, $this->party);

        $this->actingAs($this->owner)->post(route('work-orders.store'), $this->order(['copies' => 60000]))
            ->assertSessionHasErrors(['paper_type_id' => 'This order needs 1,236 rims but only 1,000 are in stock for this paper and party.']);
        $this->assertSame(0, WorkOrder::query()->count());
        $this->assertSame(1000, $this->stock());
    }

    public function test_deleting_orders_only_before_paper_leaves(): void
    {
        $this->actingAs($this->owner)->post(route('work-orders.store'), $this->order())->assertSessionHasNoErrors();
        $this->actingAs($this->manager)->post(route('work-orders.store'), $this->order(['copies' => 100]))->assertSessionHasNoErrors();
        [$issued, $pending] = WorkOrder::query()->orderBy('id')->get()->all();
        PlateJob::factory()->create(['work_order_id' => $pending->id]);

        $this->actingAs($this->owner)->get(route('work-orders.show', $issued))->assertDontSee('Delete WO-1001');
        $this->delete(route('work-orders.destroy', $issued))->assertStatus(409);
        $this->assertModelExists($issued);
        $this->assertSame(629, $this->stock());

        // A waiting order with plate history can go; the plate bill stays, unlinked.
        $this->actingAs($this->manager)->delete(route('work-orders.destroy', $pending))
            ->assertRedirect(route('work-orders.index'))->assertSessionHas('status', 'WO-1002 deleted.');
        $this->assertModelMissing($pending);
        $this->assertNull(PlateJob::query()->sole()->work_order_id);
        $this->assertSame(629, $this->stock());
    }

    public function test_there_is_no_way_to_edit_an_order_after_it_is_saved(): void
    {
        $this->actingAs($this->owner)->post(route('work-orders.store'), $this->order())->assertSessionHasNoErrors();
        $order = WorkOrder::query()->sole();

        $this->get('/work-orders/'.$order->id.'/edit')->assertNotFound();
        $this->put(route('work-orders.show', $order), $this->order(['copies' => 1]))->assertStatus(405);
        $this->assertSame(18000, $order->refresh()->copies);
        $this->assertSame(629, $this->stock());
    }

    // ---------------------------------------------------------------- outside printing

    public function test_outsourced_order_returns_leftover_rims_as_ret_receipts(): void
    {
        $this->actingAs($this->owner)->post(route('work-orders.store'), $this->outsourceOrder(['machine_id' => $this->machine->id]))->assertSessionHasNoErrors();
        $order = WorkOrder::query()->sole();
        $this->assertNull($order->machine_id, 'Outsourced orders never keep a machine.');
        $this->assertSame($this->press->id, $order->press_id);
        $this->assertSame('Al-Noor Press', PaperIssue::query()->sole()->issued_to);
        $this->assertSame('outsource', PaperIssue::query()->sole()->kind->value);
        $this->assertSame(629, $this->stock());

        $show = route('work-orders.show', $order);
        $receipt = fn (array $data) => $this->actingAs($this->owner)->from($show)->post(route('work-orders.outside-receipts.store', $order), $data + ['received_on' => today()->toDateString()]);

        // More leftover than was sent out is refused.
        $receipt(['printed_copies' => 100, 'leftover_rims' => 372, 'godown_id' => $this->godown->id])->assertSessionHasErrors('leftover_rims');
        // Leftover without a godown is refused.
        $receipt(['printed_copies' => 100, 'leftover_rims' => 5])->assertSessionHasErrors('godown_id');
        // An inactive godown is refused.
        $closed = Godown::factory()->create(['is_active' => false]);
        $receipt(['printed_copies' => 100, 'leftover_rims' => 5, 'godown_id' => $closed->id])->assertSessionHasErrors('godown_id');
        $receipt(['printed_copies' => -1])->assertSessionHasErrors('printed_copies');
        $this->assertSame(0, OutsidePrintReceipt::query()->count());

        $receipt(['printed_copies' => 9000, 'leftover_rims' => 20, 'godown_id' => $this->godown->id, 'bill_amount' => 45000])
            ->assertRedirect($show)->assertSessionHasNoErrors()->assertSessionHas('status', 'Printed work received from Al-Noor Press.');
        $ret = PaperReceipt::query()->where('slip_no', 'RET-WO-1001-1')->sole();
        $this->assertSame(20, $ret->quantity);
        $this->assertSame(ApprovalStatus::Approved, $ret->status);
        $this->assertSame($this->party->id, $ret->party_id);
        $this->assertSame(649, $this->stock());
        $this->assertSame(351, $order->refresh()->rimsUsed());

        // UX: typing 0 unused rims still demands a godown (required_with treats "0" as present).
        $receipt(['printed_copies' => 1, 'leftover_rims' => 0])->assertSessionHasErrors('godown_id');

        // No leftover → no receipt slip, but the numbering still advances.
        $receipt(['printed_copies' => 5000])->assertSessionHasNoErrors();
        $this->assertSame(1, PaperReceipt::query()->where('slip_no', 'like', 'RET-%')->count());

        // The cap shrinks as rims come back.
        $receipt(['printed_copies' => 0, 'leftover_rims' => 352, 'godown_id' => $this->godown->id])->assertSessionHasErrors('leftover_rims');
        $receipt(['printed_copies' => 4000, 'leftover_rims' => 351, 'godown_id' => $this->godown->id])->assertSessionHasNoErrors();
        $this->assertTrue(PaperReceipt::query()->where('slip_no', 'RET-WO-1001-3')->exists());
        $this->assertSame(1000, $this->stock());
        $this->assertSame(0, $order->refresh()->rimsUsed());

        $this->actingAs($this->owner)->get($show)->assertOk()->assertSee('18,000 copies received back')->assertSee('371 rims returned');
        $this->get(route('paper-receipts.show', $ret))->assertOk();
    }

    public function test_leftover_rims_returned_by_a_manager_wait_for_approval_but_count_in_stock(): void
    {
        $this->actingAs($this->manager)->post(route('work-orders.store'), $this->outsourceOrder())->assertSessionHasNoErrors();
        $order = WorkOrder::query()->sole();

        // Not issued yet: receiving printed work is refused.
        $this->actingAs($this->manager)->post(route('work-orders.outside-receipts.store', $order), ['received_on' => today()->toDateString(), 'printed_copies' => 1])->assertStatus(409);

        $this->decide($order, 'approve')->assertSessionHasNoErrors();
        $this->assertSame(629, $this->stock());

        $this->actingAs($this->manager)->post(route('work-orders.outside-receipts.store', $order), [
            'received_on' => today()->toDateString(), 'printed_copies' => 18000, 'leftover_rims' => 11, 'godown_id' => $this->godown->id,
        ])->assertSessionHasNoErrors();

        $ret = PaperReceipt::query()->where('slip_no', 'RET-WO-1001-1')->sole();
        $this->assertSame(ApprovalStatus::Pending, $ret->status);
        $this->assertSame(640, $this->stock());
        $this->actingAs($this->owner)->get(route('approvals.index'))->assertOk()->assertSee('RET-WO-1001-1');
    }

    public function test_in_house_order_cannot_take_outside_receipts(): void
    {
        $this->actingAs($this->owner)->post(route('work-orders.store'), $this->order())->assertSessionHasNoErrors();
        $order = WorkOrder::query()->sole();

        $this->post(route('work-orders.outside-receipts.store', $order), ['received_on' => today()->toDateString(), 'printed_copies' => 1])->assertStatus(409);
        $this->assertSame(0, OutsidePrintReceipt::query()->count());
    }

    // ---------------------------------------------------------------- rims & validation

    /**
     * @return array<string, array{0: float, 1: int, 2: float, 3: int, 4: int}>
     */
    public static function rimCases(): array
    {
        return [
            'client example 10 × 18,000 + 3%' => [10, 18000, 3, 500, 371],
            'exactly half a rim rounds up' => [10, 25, 0, 500, 1],
            '2.5 rims rounds up' => [1, 1250, 0, 500, 3],
            '2.48 rims rounds down' => [1, 1240, 0, 500, 2],
            'fractional forms 6.5 × 1000 + 4%' => [6.5, 1000, 4, 500, 14],
            'wastage pushes over .5' => [10, 2450, 3, 500, 50],
            'bleach card packets of 100' => [2, 1000, 0, 100, 20],
        ];
    }

    #[DataProvider('rimCases')]
    public function test_rims_needed_are_worked_out_and_rounded(float $forms, int $copies, float $wastage, int $sheets, int $expected): void
    {
        $this->paper->update(['sheets_per_unit' => $sheets]);
        $this->format->update(['forms' => $forms]);

        $this->actingAs($this->owner)->post(route('work-orders.store'), $this->order(['copies' => $copies, 'wastage_percent' => $wastage]))->assertSessionHasNoErrors();

        $order = WorkOrder::query()->sole();
        $this->assertSame($expected, $order->rims_needed);
        $this->assertSame($expected, PaperIssue::query()->sole()->quantity);
        $this->assertSame(1000 - $expected, $this->stock());
    }

    /**
     * SUSPICIOUS (business rule, unconfirmed): a small job under a quarter-rim rounds to 0 rims, so the
     * order is approved and "issues" a 0-rim paper slip although paper is physically used. Documents current behaviour.
     */
    public function test_tiny_order_rounds_to_zero_rims(): void
    {
        $this->format->update(['forms' => 1]);

        $this->actingAs($this->owner)->post(route('work-orders.store'), $this->order(['copies' => 200, 'wastage_percent' => 3]))->assertSessionHasNoErrors();

        $this->assertSame(0, WorkOrder::query()->sole()->rims_needed);
        $this->assertSame(0, PaperIssue::query()->sole()->quantity);
        $this->assertSame(1000, $this->stock());
    }

    public function test_order_form_validation(): void
    {
        $this->actingAs($this->manager);

        $this->post(route('work-orders.store'), [])->assertSessionHasErrors(['ordered_on', 'book_format_id', 'copies', 'wastage_percent', 'paper_type_id', 'party_id', 'mode']);
        $this->post(route('work-orders.store'), $this->order(['machine_id' => null]))->assertSessionHasErrors(['machine_id' => 'The machine field is required when mode is in_house.']);
        $this->post(route('work-orders.store'), $this->outsourceOrder(['press_id' => null]))->assertSessionHasErrors('press_id');
        $this->post(route('work-orders.store'), $this->outsourceOrder(['press_id' => $this->buyer->id]))->assertSessionHasErrors('press_id');
        $this->post(route('work-orders.store'), $this->order(['buyer_id' => $this->press->id]))->assertSessionHasErrors('buyer_id');
        $this->post(route('work-orders.store'), $this->order(['copies' => 0]))->assertSessionHasErrors('copies');
        $this->post(route('work-orders.store'), $this->order(['copies' => 1.5]))->assertSessionHasErrors('copies');
        $this->post(route('work-orders.store'), $this->order(['wastage_percent' => 51]))->assertSessionHasErrors('wastage_percent');
        $this->post(route('work-orders.store'), $this->order(['wastage_percent' => -1]))->assertSessionHasErrors('wastage_percent');
        $this->post(route('work-orders.store'), $this->order(['mode' => 'teleport']))->assertSessionHasErrors('mode');
        $this->post(route('work-orders.store'), $this->order(['book_format_id' => 9999]))->assertSessionHasErrors('book_format_id');
        $this->post(route('work-orders.store'), $this->order(['paper_type_id' => 9999]))->assertSessionHasErrors('paper_type_id');
        $this->post(route('work-orders.store'), $this->order(['ordered_on' => 'not-a-date']))->assertSessionHasErrors('ordered_on');
        $this->post(route('work-orders.store'), $this->order(['note' => str_repeat('x', 256)]))->assertSessionHasErrors('note');

        $this->assertSame(0, WorkOrder::query()->count());
        $this->assertSame(1000, $this->stock());
    }

    public function test_order_numbers_follow_on(): void
    {
        $this->actingAs($this->owner);
        foreach (range(1, 3) as $i) {
            $this->post(route('work-orders.store'), $this->order(['copies' => 100]))->assertSessionHasNoErrors();
        }

        $this->assertSame(['WO-1001', 'WO-1002', 'WO-1003'], WorkOrder::query()->orderBy('id')->pluck('number')->all());
        $this->get(route('work-orders.create'))->assertSee('WO-1004');
    }

    // ---------------------------------------------------------------- books & formats

    public function test_book_create_edit_and_formats_flow(): void
    {
        $publisher = Party::factory()->create(['name' => 'Gohar Publishers']);
        $this->actingAs($this->manager)->post(route('books.store'), [
            'name' => 'Mathematics Class 7', 'name_ur' => 'ریاضی ساتویں', 'class_subject' => 'Class 7', 'party_id' => $publisher->id, 'is_active' => 1,
            'formats' => [
                ['name' => 'Standard', 'size' => '20 × 30', 'forms' => 12.5, 'pages' => 200, 'paper_type_id' => $this->paper->id, 'folding_rate' => 35, 'binding_rate' => 2.5],
                ['name' => 'Pocket', 'forms' => 6, 'folding_rate' => ''],
            ],
        ])->assertSessionHasNoErrors();

        $book = Book::query()->where('name', 'Mathematics Class 7')->sole();
        $this->assertSame(['Standard', 'Pocket'], $book->formats->pluck('name')->all());
        $this->assertNull($book->formats[1]->folding_rate);
        $this->actingAs($this->owner)->get(route('books.show', $book))->assertOk()->assertSee('Gohar Publishers')->assertSee('ریاضی ساتویں');
        $this->get(route('books.index', ['q' => 'ریاضی']))->assertSee('Mathematics Class 7');

        // Both formats appear on the order form; a two-format book names the format on the paper slip.
        $this->get(route('work-orders.create'))->assertSee('Mathematics Class 7 — Pocket');
        $pocket = $book->formats[1];
        $this->post(route('work-orders.store'), $this->order(['book_format_id' => $pocket->id, 'copies' => 1000, 'wastage_percent' => 0]))->assertSessionHasNoErrors();
        $this->assertSame('Mathematics Class 7 — Pocket', PaperIssue::query()->sole()->book_name);
        $this->assertSame(12, PaperIssue::query()->sole()->quantity);

        // Edit: rename the first format and add a third, keep the used one.
        $standard = $book->formats[0];
        $this->actingAs($this->manager)->put(route('books.update', $book), [
            'name' => 'Mathematics Class 7', 'is_active' => 1,
            'formats' => [
                ['id' => $standard->id, 'name' => 'Large', 'forms' => 13],
                ['id' => $pocket->id, 'name' => 'Pocket', 'forms' => 6],
                ['name' => 'Workbook', 'forms' => 3],
            ],
        ])->assertRedirect(route('books.show', $book))->assertSessionHasNoErrors();
        $this->assertSame(['Large', 'Pocket', 'Workbook'], $book->refresh()->formats->pluck('name')->all());
        $this->assertSame('13.00', $standard->refresh()->forms);
        $this->assertSame('6.00', WorkOrder::query()->sole()->bookFormat->forms);

        // Deactivating hides the book from the order form.
        $this->put(route('books.update', $book), ['name' => 'Mathematics Class 7', 'is_active' => 0, 'formats' => [
            ['id' => $standard->id, 'name' => 'Large', 'forms' => 13], ['id' => $pocket->id, 'name' => 'Pocket', 'forms' => 6],
        ]])->assertSessionHasNoErrors();
        $this->assertFalse($book->refresh()->is_active);
        $this->actingAs($this->owner)->get(route('work-orders.create'))->assertDontSee('Mathematics Class 7');
    }

    public function test_book_validation(): void
    {
        $this->actingAs($this->manager);

        $this->post(route('books.store'), ['name' => 'X'])->assertSessionHasErrors(['formats' => 'Add at least one format.']);
        $this->post(route('books.store'), ['name' => '', 'formats' => [['name' => 'A', 'forms' => 1]]])->assertSessionHasErrors('name');
        $this->post(route('books.store'), ['name' => 'Urdu Reader Class 5', 'formats' => [['name' => 'A', 'forms' => 1]]])->assertSessionHasErrors('name');
        $this->post(route('books.store'), ['name' => 'X', 'formats' => [['name' => 'A', 'forms' => 0.25]]])->assertSessionHasErrors('formats.0.forms');
        $this->post(route('books.store'), ['name' => 'X', 'formats' => [['name' => 'A', 'forms' => 1000]]])->assertSessionHasErrors('formats.0.forms');
        $this->post(route('books.store'), ['name' => 'X', 'formats' => [['name' => 'A', 'forms' => 1], ['name' => 'A', 'forms' => 2]]])->assertSessionHasErrors('formats.1.name');
        $this->post(route('books.store'), ['name' => 'X', 'formats' => [['name' => 'A', 'forms' => 1, 'folding_rate' => -1]]])->assertSessionHasErrors('formats.0.folding_rate');
        $this->post(route('books.store'), ['name' => 'X', 'formats' => [['forms' => 1]]])->assertSessionHasErrors('formats.0.name');
        $this->assertSame(1, Book::query()->count());

        // Keeping its own name on update is fine.
        $book = $this->format->book;
        $this->put(route('books.update', $book), ['name' => 'Urdu Reader Class 5', 'formats' => [['id' => $this->format->id, 'name' => 'Standard', 'forms' => 10]]])->assertSessionHasNoErrors();
    }

    public function test_deleting_a_book_without_orders_removes_it_and_keeps_paper_history(): void
    {
        $format = BookFormat::factory()->create(['forms' => 2]);
        $issue = PaperIssue::factory()->create(['book_format_id' => $format->id, 'party_id' => $this->party->id, 'paper_type_id' => $this->paper->id, 'quantity' => 5]);

        $this->actingAs($this->manager)->delete(route('books.destroy', $format->book))->assertRedirect(route('books.index'));

        $this->assertModelMissing($format);
        $this->assertNull($issue->refresh()->book_format_id);
        $this->assertSame(995, $this->stock());
    }

    /**
     * A book whose formats have work orders is not deleted; the owner is told to deactivate it.
     */
    public function test_deleting_a_book_with_work_orders_is_refused_politely(): void
    {
        $this->actingAs($this->owner)->post(route('work-orders.store'), $this->order())->assertSessionHasNoErrors();
        $book = $this->format->book;

        $response = $this->actingAs($this->manager)->from(route('books.show', $book))->delete(route('books.destroy', $book));

        $this->assertNotSame(500, $response->getStatusCode(), 'Deleting a book with work orders crashes with a 500.');
        $this->assertModelExists($book);
        $this->assertModelExists($this->format);
    }

    /**
     * A format used by a work order cannot be removed from the book.
     */
    public function test_removing_a_format_used_by_an_order_is_refused_politely(): void
    {
        $this->actingAs($this->owner)->post(route('work-orders.store'), $this->order())->assertSessionHasNoErrors();
        $book = $this->format->book;

        $response = $this->actingAs($this->manager)->from(route('books.edit', $book))->put(route('books.update', $book), [
            'name' => $book->name, 'formats' => [['name' => 'Replacement', 'forms' => 8]],
        ]);

        $this->assertNotSame(500, $response->getStatusCode(), 'Removing a format in use crashes with a 500.');
        $this->assertModelExists($this->format);
    }

    // ---------------------------------------------------------------- plate jobs

    public function test_plate_job_record_and_pay(): void
    {
        $processor = Party::factory()->kind(PartyKind::PlateProcessor)->create(['name' => 'Lahore Plates']);
        $this->actingAs($this->owner)->post(route('work-orders.store'), $this->order())->assertSessionHasNoErrors();
        $order = WorkOrder::query()->sole();

        $this->actingAs($this->manager);
        $this->post(route('plate-jobs.store'), [])->assertSessionHasErrors(['party_id', 'job_on', 'plates', 'rate']);
        $this->post(route('plate-jobs.store'), ['party_id' => $this->press->id, 'job_on' => today()->toDateString(), 'plates' => 1, 'rate' => 1])->assertSessionHasErrors('party_id');
        $this->post(route('plate-jobs.store'), ['party_id' => $processor->id, 'job_on' => today()->toDateString(), 'plates' => 0, 'rate' => 1])->assertSessionHasErrors('plates');
        $this->post(route('plate-jobs.store'), ['party_id' => $processor->id, 'job_on' => today()->toDateString(), 'plates' => 1, 'rate' => -1])->assertSessionHasErrors('rate');
        $this->post(route('plate-jobs.store'), ['party_id' => $processor->id, 'work_order_id' => 9999, 'job_on' => today()->toDateString(), 'plates' => 1, 'rate' => 1])->assertSessionHasErrors('work_order_id');

        // 3 plates × Rs 12.50 = Rs 37.50 → 38
        $this->post(route('plate-jobs.store'), ['party_id' => $processor->id, 'work_order_id' => $order->id, 'job_on' => today()->toDateString(), 'plates' => 3, 'rate' => 12.5])
            ->assertRedirect(route('plate-jobs.index'))->assertSessionHas('status', 'Plate job saved.');
        $job = PlateJob::query()->sole();
        $this->assertSame(38, $job->amount);
        $this->assertSame($this->manager->id, $job->created_by);

        $this->get(route('plate-jobs.index'))->assertOk()->assertSee('Lahore Plates')->assertSee('WO-1001');
        $this->get(route('plate-jobs.index', ['show' => 'paid']))->assertOk()->assertDontSee('Lahore Plates');
        $this->get(route('work-orders.show', $order))->assertOk()->assertSee('Lahore Plates')->assertSee('Unpaid');

        $this->from(route('plate-jobs.index'))->patch(route('plate-jobs.pay', $job), [])->assertSessionHasErrors('paid_on');
        $this->from(route('plate-jobs.index'))->patch(route('plate-jobs.pay', $job), ['paid_on' => today()->toDateString(), 'payment_note' => 'Cash'])
            ->assertRedirect(route('plate-jobs.index'))->assertSessionHas('status', 'Plate bill of Rs 38 marked as paid.');
        $this->assertTrue($job->refresh()->isPaid());
        $this->get(route('plate-jobs.index', ['show' => 'paid']))->assertOk()->assertSee('Lahore Plates')->assertSee('Cash');
        $this->get(route('plate-jobs.index'))->assertOk()->assertDontSee('Lahore Plates');
        $this->get(route('work-orders.show', $order))->assertOk()->assertSee('Paid');
    }

    public function test_plate_job_form_lists_only_open_orders(): void
    {
        Party::factory()->kind(PartyKind::PlateProcessor)->create();
        $this->actingAs($this->owner)->post(route('work-orders.store'), $this->order(['copies' => 100]))->assertSessionHasNoErrors();
        $this->actingAs($this->owner)->post(route('work-orders.store'), $this->order(['copies' => 200]))->assertSessionHasNoErrors();
        [$open, $done] = WorkOrder::query()->orderBy('id')->get()->all();
        $done->forceFill(['stage' => WorkOrderStage::Dispatched])->save();

        $this->flushSession()->get(route('plate-jobs.create'))->assertOk()->assertSee($open->number)->assertDontSee($done->number);
    }
}
