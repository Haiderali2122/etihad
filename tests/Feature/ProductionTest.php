<?php

namespace Tests\Feature;

use App\Enums\ApprovalStatus;
use App\Enums\ContractorJobKind;
use App\Enums\PartyKind;
use App\Enums\WorkOrderStage;
use App\Models\BookFormat;
use App\Models\BuyerEntry;
use App\Models\Contractor;
use App\Models\ContractorJob;
use App\Models\ContractorPayment;
use App\Models\Godown;
use App\Models\Machine;
use App\Models\PaperReceipt;
use App\Models\PaperType;
use App\Models\Party;
use App\Models\PlateJob;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkOrder;
use App\Support\PaperStock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionTest extends TestCase
{
    use RefreshDatabase;

    private PaperType $paper;

    private Party $party;

    private BookFormat $format;

    protected function setUp(): void
    {
        parent::setUp();
        $this->paper = PaperType::factory()->create();
        $this->party = Party::factory()->create();
        PaperReceipt::factory()->for($this->paper)->for($this->party)->create(['quantity' => 1000]);
        $this->format = BookFormat::factory()->create(['forms' => 10, 'folding_rate' => 35, 'binding_rate' => 2.5]);
    }

    /**
     * @return array<string, mixed>
     */
    private function order(array $overrides = []): array
    {
        return $overrides + [
            'ordered_on' => today()->toDateString(), 'book_format_id' => $this->format->id, 'copies' => 18000, 'wastage_percent' => 3,
            'paper_type_id' => $this->paper->id, 'party_id' => $this->party->id, 'mode' => 'in_house', 'machine_id' => Machine::factory()->create()->id,
        ];
    }

    private function balance(): int
    {
        return app(PaperStock::class)->available($this->paper->id, $this->party->id);
    }

    public function test_a_manager_order_waits_and_paper_leaves_stock_on_approval(): void
    {
        $owner = User::factory()->owner()->create();
        $manager = User::factory()->create();

        $this->actingAs($manager)->post(route('work-orders.store'), $this->order())->assertSessionHasNoErrors();

        $order = WorkOrder::query()->sole();
        // 10 forms × 18,000 ÷ 500 = 360 rims + 3% = 371
        $this->assertSame(371, $order->rims_needed);
        $this->assertSame('WO-1001', $order->number);
        $this->assertSame(WorkOrderStage::Waiting, $order->stage);
        $this->assertSame(1000, $this->balance());

        $this->actingAs($owner)->patch(route('approvals.update', ['work-order', $order->id]), ['decision' => 'approve'])->assertSessionHasNoErrors();

        $order->refresh();
        $this->assertSame(WorkOrderStage::Printing, $order->stage);
        $this->assertNotNull($order->paper_issue_id);
        $this->assertSame(629, $this->balance());
        $this->assertSame('WO-1001', $order->paperIssue->reference);
    }

    public function test_an_owner_order_issues_paper_straight_away(): void
    {
        $this->actingAs(User::factory()->owner()->create())->post(route('work-orders.store'), $this->order())->assertSessionHasNoErrors();

        $this->assertSame(WorkOrderStage::Printing, WorkOrder::query()->sole()->stage);
        $this->assertSame(629, $this->balance());
    }

    public function test_an_order_the_godown_cannot_cover_is_refused(): void
    {
        $this->actingAs(User::factory()->create())->post(route('work-orders.store'), $this->order(['copies' => 60000]))
            ->assertSessionHasErrors('paper_type_id');

        $this->assertDatabaseCount('work_orders', 0);
    }

    public function test_approval_is_blocked_if_stock_ran_out_meanwhile(): void
    {
        $owner = User::factory()->owner()->create();
        $this->actingAs(User::factory()->create())->post(route('work-orders.store'), $this->order());
        $order = WorkOrder::query()->sole();
        // Someone else takes the paper before the owner approves.
        $this->actingAs($owner)->post(route('paper-issues.store'), [
            'issued_on' => today()->toDateString(), 'kind' => 'in_house', 'party_id' => $this->party->id,
            'paper_type_id' => $this->paper->id, 'issued_to' => 'Machine P-01', 'quantity' => 900,
        ]);

        $this->actingAs($owner)->patch(route('approvals.update', ['work-order', $order->id]), ['decision' => 'approve'])
            ->assertSessionHasErrors('decision');

        $this->assertSame(ApprovalStatus::Pending, $order->refresh()->status);
        $this->assertNull($order->paper_issue_id);
    }

    public function test_orders_move_through_the_stages(): void
    {
        $owner = User::factory()->owner()->create();
        $this->actingAs($owner)->post(route('work-orders.store'), $this->order());
        $order = WorkOrder::query()->sole();

        foreach ([WorkOrderStage::Folding, WorkOrderStage::Binding, WorkOrderStage::Ready, WorkOrderStage::Dispatched] as $expected) {
            $this->actingAs($owner)->patch(route('work-orders.advance', $order));
            $this->assertSame($expected, $order->refresh()->stage);
        }

        $this->actingAs($owner)->patch(route('work-orders.advance', $order))->assertStatus(409);
        $this->actingAs($owner)->get(route('work-orders.show', $order))->assertOk();
        $this->actingAs($owner)->get(route('work-orders.index'))->assertOk()->assertSee($order->number);
    }

    public function test_outside_press_returns_unused_rims_to_stock(): void
    {
        $owner = User::factory()->owner()->create();
        $press = Party::factory()->kind(PartyKind::Press)->create();
        $this->actingAs($owner)->post(route('work-orders.store'), $this->order(['mode' => 'outsource', 'machine_id' => null, 'press_id' => $press->id]))->assertSessionHasNoErrors();
        $order = WorkOrder::query()->sole();
        $this->assertSame(629, $this->balance());

        $this->actingAs($owner)->post(route('work-orders.outside-receipts.store', $order), [
            'received_on' => today()->toDateString(), 'printed_copies' => 18000, 'leftover_rims' => 9,
            'godown_id' => Godown::factory()->create()->id, 'bill_amount' => 42000,
        ])->assertSessionHasNoErrors();

        $this->assertSame(638, $this->balance());
        $this->assertSame(362, $order->refresh()->rimsUsed());
        $this->assertStringStartsWith('RET-WO-1001', PaperReceipt::query()->latest('id')->first()->slip_no);
    }

    public function test_contractor_jobs_follow_the_client_formulas(): void
    {
        // Folding: (10 × 12,000 ÷ 1000) × 35 = 4,200. Binding: ((8 × 0.6) + 2.5) × 25,000 = 182,500.
        $this->assertSame(4200, ContractorJob::price(ContractorJobKind::Folding, 10, 12000, 35));
        $this->assertSame(182500, ContractorJob::price(ContractorJobKind::Binding, 8, 25000, 0, 0.6, 2.5));

        $manager = User::factory()->create();
        $contractor = Contractor::factory()->create();
        $this->actingAs($manager)->post(route('contractor-jobs.store'), [
            'contractor_id' => $contractor->id, 'kind' => 'binding', 'job_on' => today()->toDateString(),
            'forms' => 8, 'copies' => 25000, 'form_rate' => 0.6, 'copy_rate' => 2.5,
        ])->assertSessionHasNoErrors();

        $this->assertSame(182500, ContractorJob::query()->sole()->amount);
    }

    public function test_contractor_hisab_counts_payments_but_not_ones_sent_back(): void
    {
        $owner = User::factory()->owner()->create();
        $manager = User::factory()->create();
        $contractor = Contractor::factory()->create();
        ContractorJob::factory()->create(['contractor_id' => $contractor->id, 'amount' => 30000]);

        $this->actingAs($manager)->post(route('contractors.payments.store', $contractor), ['paid_on' => today()->toDateString(), 'amount' => 20000, 'kind' => 'advance'])->assertSessionHasNoErrors();
        $payment = ContractorPayment::query()->sole();
        $this->assertSame(ApprovalStatus::Pending, $payment->status);
        $this->assertSame(10000, $contractor->balance());

        $this->actingAs($owner)->patch(route('approvals.update', ['contractor-payment', $payment->id]), ['decision' => 'reject', 'note' => 'Too much']);
        $this->assertSame(30000, $contractor->balance());

        $this->actingAs($owner)->get(route('contractors.show', $contractor))->assertOk();
    }

    public function test_dispatch_counts_copies_and_labour_and_closes_the_order(): void
    {
        $owner = User::factory()->owner()->create();
        $buyer = Party::factory()->kind(PartyKind::Buyer)->create();
        $this->actingAs($owner)->post(route('work-orders.store'), $this->order(['copies' => 1000]));
        $order = WorkOrder::query()->sole();
        $order->update(['stage' => WorkOrderStage::Ready]);

        $this->actingAs($owner)->post(route('dispatches.store'), [
            'dispatched_on' => today()->toDateString(), 'buyer_id' => $buyer->id, 'work_order_id' => $order->id,
            'bundles' => 39, 'copies_per_bundle' => 25, 'loose_copies' => 25, 'rims' => 2, 'labour_per_bundle' => 3, 'labour_per_rim' => 10,
        ])->assertSessionHasNoErrors();

        $dispatch = $order->dispatches()->sole();
        $this->assertSame(1000, $dispatch->copies);
        $this->assertSame(137, $dispatch->labour_amount);
        $this->assertSame(WorkOrderStage::Dispatched, $order->refresh()->stage);
        $this->actingAs($owner)->get(route('dispatches.show', $dispatch))->assertOk()->assertSee('LOADING SLIP');
        $this->actingAs($owner)->get(route('buyers.show', $buyer))->assertOk();
    }

    public function test_buyer_adjustment_cannot_exceed_the_advance(): void
    {
        $owner = User::factory()->owner()->create();
        $buyer = BuyerEntry::factory()->create(['amount' => 50000])->buyer;

        $this->actingAs($owner)->post(route('buyers.entries.store', $buyer), ['entry_on' => today()->toDateString(), 'kind' => 'adjustment', 'amount' => 60000])
            ->assertSessionHasErrors('amount');
        $this->actingAs($owner)->post(route('buyers.entries.store', $buyer), ['entry_on' => today()->toDateString(), 'kind' => 'adjustment', 'amount' => 20000])
            ->assertSessionHasNoErrors();
    }

    public function test_plate_bills_can_be_cleared(): void
    {
        $manager = User::factory()->create();
        $processor = Party::factory()->kind(PartyKind::PlateProcessor)->create();

        $this->actingAs($manager)->post(route('plate-jobs.store'), ['party_id' => $processor->id, 'job_on' => today()->toDateString(), 'plates' => 12, 'rate' => 125])->assertSessionHasNoErrors();
        $job = PlateJob::query()->sole();
        $this->assertSame(1500, $job->amount);

        $this->actingAs($manager)->patch(route('plate-jobs.pay', $job), ['paid_on' => today()->toDateString()]);
        $this->assertTrue($job->refresh()->isPaid());
    }

    public function test_waste_sold_needs_a_sale_amount(): void
    {
        $manager = User::factory()->create();
        $payload = ['recorded_on' => today()->toDateString(), 'stage' => 'printing', 'quantity' => 38, 'unit' => 'kg', 'disposal' => 'sold'];

        $this->actingAs($manager)->post(route('waste.store'), $payload)->assertSessionHasErrors('sale_amount');
        $this->actingAs($manager)->post(route('waste.store'), $payload + ['sale_amount' => 2280])->assertSessionHasNoErrors();
    }

    public function test_viewers_cannot_change_production(): void
    {
        $viewer = User::factory()->create(['role_id' => Role::factory()->create(['permissions' => ['orders.view']])->id]);

        $this->actingAs($viewer)->get(route('work-orders.index'))->assertOk();
        $this->actingAs($viewer)->get(route('work-orders.create'))->assertForbidden();
        $this->actingAs($viewer)->get(route('contractor-jobs.create'))->assertForbidden();
        $this->actingAs($viewer)->get(route('dispatches.create'))->assertForbidden();
        $this->actingAs($viewer)->get(route('waste.create'))->assertForbidden();
    }
}
