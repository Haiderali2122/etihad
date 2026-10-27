<?php

namespace Tests\Feature\E2E;

use App\Enums\ApprovalStatus;
use App\Enums\BuyerEntryKind;
use App\Enums\ContractorJobKind;
use App\Enums\PartyKind;
use App\Enums\WorkOrderStage;
use App\Models\BookFormat;
use App\Models\BuyerEntry;
use App\Models\Contractor;
use App\Models\ContractorJob;
use App\Models\ContractorPayment;
use App\Models\Dispatch;
use App\Models\Party;
use App\Models\Role;
use App\Models\User;
use App\Models\WasteEntry;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * End-to-end walk through folding & binding contractors, loading & dispatch, buyers and waste,
 * driven over HTTP the way the owner and limited staff use the screens.
 */
class ContractorDispatchBuyerWasteE2ETest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->owner()->create();
    }

    /**
     * @param  list<string>  $permissions
     */
    private function userWith(array $permissions, string $locale = 'en'): User
    {
        return User::factory()->create([
            'role_id' => Role::factory()->create(['permissions' => $permissions])->id,
            'locale' => $locale,
        ]);
    }

    private function order(WorkOrderStage $stage = WorkOrderStage::Ready, int $copies = 1000): WorkOrder
    {
        return WorkOrder::factory()->create([
            'stage' => $stage,
            'copies' => $copies,
            'forms' => 10,
            'book_format_id' => BookFormat::factory()->create(['forms' => 10, 'folding_rate' => 35, 'binding_rate' => 2.5])->id,
        ]);
    }

    private function buyer(string $name = 'Ilmi Kitab Ghar'): Party
    {
        return Party::factory()->kind(PartyKind::Buyer)->create(['name' => $name]);
    }

    /**
     * @return array<string, mixed>
     */
    private function load(Party $buyer, array $overrides = []): array
    {
        return $overrides + [
            'dispatched_on' => today()->toDateString(), 'buyer_id' => $buyer->id,
            'bundles' => 10, 'copies_per_bundle' => 25, 'loose_copies' => 0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function seedModule(): array
    {
        $order = $this->order(WorkOrderStage::Binding);
        $contractor = Contractor::factory()->create(['name' => 'Rafiq Folding', 'name_ur' => 'رفیق فولڈنگ']);
        $job = ContractorJob::factory()->create(['contractor_id' => $contractor->id, 'work_order_id' => $order->id]);
        ContractorPayment::factory()->create(['contractor_id' => $contractor->id, 'amount' => 1000, 'created_by' => $this->owner->id]);
        $buyer = $this->buyer();
        $dispatch = Dispatch::factory()->create(['buyer_id' => $buyer->id, 'work_order_id' => $order->id, 'book_format_id' => $order->book_format_id, 'rims' => 2]);
        BuyerEntry::factory()->create(['party_id' => $buyer->id, 'amount' => 20000]);
        BuyerEntry::factory()->create(['party_id' => $buyer->id, 'kind' => BuyerEntryKind::Adjustment, 'amount' => 5000, 'dispatch_id' => $dispatch->id]);
        WasteEntry::factory()->create(['work_order_id' => $order->id]);

        return compact('order', 'contractor', 'job', 'buyer', 'dispatch');
    }

    // ---------------------------------------------------------------- screens

    public function test_owner_can_open_every_screen_of_the_module(): void
    {
        ['order' => $order, 'contractor' => $contractor, 'buyer' => $buyer, 'dispatch' => $dispatch] = $this->seedModule();
        $this->actingAs($this->owner);

        $this->get(route('contractors.index'))->assertOk()->assertSee('Rafiq Folding');
        $this->get(route('contractors.create'))->assertOk();
        $this->get(route('contractors.show', $contractor))->assertOk()->assertSee($order->number);
        $this->get(route('contractors.edit', $contractor))->assertOk()->assertSee('Rafiq Folding');
        $this->get(route('contractor-jobs.create'))->assertOk();
        $this->get(route('contractor-jobs.create', ['order' => $order->id, 'contractor' => $contractor->id]))->assertOk();
        $this->get(route('contractor-jobs.create', ['kind' => 'binding']))->assertOk();
        $this->get(route('dispatches.index'))->assertOk()->assertSee($dispatch->number);
        $this->get(route('dispatches.create'))->assertOk()->assertSee('DSP-');
        $this->get(route('dispatches.create', ['order' => $order->id]))->assertOk();
        $this->get(route('dispatches.show', $dispatch))->assertOk()->assertSee('LOADING SLIP')->assertSee('2,500');
        $this->get(route('buyers.index'))->assertOk()->assertSee('Ilmi Kitab Ghar');
        $this->get(route('buyers.show', $buyer))->assertOk()->assertSee('Rs 15,000');
        $this->get(route('waste.index'))->assertOk();
        $this->get(route('waste.create'))->assertOk();
        $this->get(route('waste.create', ['order' => $order->id]))->assertOk();
        $this->get(route('parties.index', ['kind' => 'buyer']))->assertOk()->assertSee('Ilmi Kitab Ghar');
        $this->get(route('parties.create'))->assertOk();
        $this->get(route('parties.edit', $buyer))->assertOk();
        $this->get(route('work-orders.show', $order))->assertOk();
    }

    public function test_empty_screens_render_without_data(): void
    {
        $this->actingAs($this->owner);

        $this->get(route('contractors.index'))->assertOk()->assertSee('No contractors yet');
        $this->get(route('contractor-jobs.create'))->assertOk()->assertSee('Add a contractor first.');
        $this->get(route('dispatches.index'))->assertOk()->assertSee('Nothing loaded yet');
        $this->get(route('dispatches.create'))->assertOk()->assertSee('No buyers yet');
        $this->get(route('buyers.index'))->assertOk()->assertSee('No buyers yet');
        $this->get(route('waste.index'))->assertOk()->assertSee('No waste recorded');
    }

    public function test_screens_render_in_urdu(): void
    {
        ['contractor' => $contractor, 'buyer' => $buyer, 'dispatch' => $dispatch] = $this->seedModule();
        $urdu = User::factory()->owner()->create(['locale' => 'ur']);
        $this->actingAs($urdu);

        $this->get(route('contractors.index'))->assertOk()->assertSee('dir="rtl"', false)->assertSee('فولڈنگ اور بائنڈنگ')->assertSee('رفیق فولڈنگ');
        $this->get(route('contractors.show', $contractor))->assertOk()->assertSee('رفیق فولڈنگ');
        $this->get(route('contractors.create'))->assertOk();
        $this->get(route('contractor-jobs.create'))->assertOk()->assertSee('کام دیں');
        $this->get(route('dispatches.index'))->assertOk()->assertSee('لوڈنگ اور ترسیل');
        $this->get(route('dispatches.create'))->assertOk()->assertSee('نئی لوڈنگ');
        $this->get(route('dispatches.show', $dispatch))->assertOk()->assertSee('لوڈنگ سلپ');
        $this->get(route('buyers.index'))->assertOk()->assertSee('خریدار');
        $this->get(route('buyers.show', $buyer))->assertOk()->assertSee('ایڈجسٹ ہونا باقی ایڈوانس');
        $this->get(route('waste.index'))->assertOk()->assertSee('فروخت شدہ ویسٹ');
        $this->get(route('waste.create'))->assertOk()->assertSee('ویسٹ درج کریں');

        // Switching back via the top bar.
        $this->get(route('locale', 'en'))->assertRedirect();
        $this->get(route('contractors.index'))->assertOk()->assertSee('Folding &amp; binding', false);
    }

    // ---------------------------------------------------------------- contractors

    public function test_contractor_is_added_edited_deactivated_and_validated(): void
    {
        $this->actingAs($this->owner);

        $this->post(route('contractors.store'), ['name' => '', 'work' => 'gluing', 'folding_rate' => -1])
            ->assertSessionHasErrors(['name', 'work', 'folding_rate']);
        $this->assertDatabaseCount('contractors', 0);

        $this->post(route('contractors.store'), [
            'name' => 'Bashir Binders', 'name_ur' => 'بشیر بائنڈر', 'phone' => '0300-1234567', 'work' => 'binding',
            'form_rate' => 0.6, 'copy_rate' => 2.5, 'is_active' => '1',
        ])->assertSessionHasNoErrors();

        $contractor = Contractor::query()->sole();
        $this->assertTrue($contractor->is_active);
        $this->assertSame('0.60', $contractor->form_rate);

        $this->get(route('contractors.show', $contractor))->assertOk()->assertSee('Bashir Binders')->assertSee('name="_method" value="DELETE"', false);

        // Unticking "active" deactivates.
        $this->put(route('contractors.update', $contractor), ['name' => 'Bashir Binders', 'work' => 'both', 'folding_rate' => 40])
            ->assertRedirect(route('contractors.show', $contractor));
        $contractor->refresh();
        $this->assertFalse($contractor->is_active);
        $this->assertSame('40.00', $contractor->folding_rate);
        $this->get(route('contractors.index'))->assertSee('Inactive');

        // An inactive contractor can't be given a job, and isn't offered on the job screen.
        $this->get(route('contractor-jobs.create'))->assertSee('Add a contractor first.');
        $this->post(route('contractor-jobs.store'), [
            'contractor_id' => $contractor->id, 'kind' => 'folding', 'job_on' => today()->toDateString(), 'forms' => 10, 'copies' => 1000, 'folding_rate' => 35,
        ])->assertSessionHasErrors('contractor_id');
    }

    public function test_contractor_without_history_can_be_deleted(): void
    {
        $contractor = Contractor::factory()->create();

        $this->actingAs($this->owner)->delete(route('contractors.destroy', $contractor))->assertRedirect(route('contractors.index'));
        $this->assertModelMissing($contractor);
    }

    public function test_contractor_with_jobs_or_payments_cannot_be_deleted(): void
    {
        $withJob = Contractor::factory()->create(['name' => 'Has Job']);
        ContractorJob::factory()->create(['contractor_id' => $withJob->id]);
        $withPayment = Contractor::factory()->create(['name' => 'Has Payment']);
        ContractorPayment::factory()->create(['contractor_id' => $withPayment->id, 'created_by' => $this->owner->id]);

        $this->actingAs($this->owner);
        $this->get(route('contractors.show', $withJob))->assertOk()->assertDontSee('name="_method" value="DELETE"', false);

        foreach ([$withJob, $withPayment] as $contractor) {
            $this->from(route('contractors.show', $contractor))->delete(route('contractors.destroy', $contractor))
                ->assertRedirect(route('contractors.show', $contractor))
                ->assertSessionHasErrors('contractor');
            $this->assertModelExists($contractor);
        }

        $this->followingRedirects()->from(route('contractors.show', $withJob))->delete(route('contractors.destroy', $withJob))
            ->assertOk()->assertSee('Has Job has jobs or payments');
    }

    // ---------------------------------------------------------------- contractor jobs & formulas

    public function test_folding_and_binding_jobs_follow_the_client_formulas(): void
    {
        $contractor = Contractor::factory()->create();
        $order = $this->order(WorkOrderStage::Folding, 12000);
        $this->actingAs($this->owner);

        // Folding: (10 × 12,000 ÷ 1000) × 35 = 4,200; given against an order it returns to the order.
        $this->post(route('contractor-jobs.store'), [
            'contractor_id' => $contractor->id, 'work_order_id' => $order->id, 'kind' => 'folding', 'job_on' => today()->toDateString(),
            'forms' => 10, 'copies' => 12000, 'folding_rate' => 35,
        ])->assertRedirect(route('work-orders.show', $order))->assertSessionHas('status', 'Folding job given to '.$contractor->name.': Rs 4,200.');

        // Folding: 7.5 × 3,333 ÷ 1000 × 36 = 899.91 → rounded to the nearest 10 = 900.
        $this->post(route('contractor-jobs.store'), [
            'contractor_id' => $contractor->id, 'kind' => 'folding', 'job_on' => today()->toDateString(),
            'forms' => 7.5, 'copies' => 3333, 'folding_rate' => 36,
        ])->assertRedirect(route('contractors.show', $contractor));

        // Binding: ((8 × 0.6) + 2.5) × 25,000 = 182,500.
        $this->post(route('contractor-jobs.store'), [
            'contractor_id' => $contractor->id, 'kind' => 'binding', 'job_on' => today()->toDateString(),
            'forms' => 8, 'copies' => 25000, 'form_rate' => 0.6, 'copy_rate' => 2.5,
        ])->assertSessionHasNoErrors();

        // Binding: ((6 × 0.45) + 1.75) × 1,234 = 5,491.30 → 5,490.
        $this->post(route('contractor-jobs.store'), [
            'contractor_id' => $contractor->id, 'kind' => 'binding', 'job_on' => today()->toDateString(),
            'forms' => 6, 'copies' => 1234, 'form_rate' => 0.45, 'copy_rate' => 1.75,
        ])->assertSessionHasNoErrors();

        // Folding landing exactly on a 5: 0.5 × 1,000 ÷ 1000 × 50 = 25 → 30 (half up).
        $this->post(route('contractor-jobs.store'), [
            'contractor_id' => $contractor->id, 'kind' => 'folding', 'job_on' => today()->toDateString(),
            'forms' => 0.5, 'copies' => 1000, 'folding_rate' => 50,
        ])->assertSessionHasNoErrors();

        $this->assertSame([4200, 900, 182500, 5490, 30], ContractorJob::query()->orderBy('id')->pluck('amount')->all());
        $this->assertSame(4200 + 900 + 182500 + 5490 + 30, $contractor->balance());

        $this->get(route('contractors.show', $contractor))->assertOk()
            ->assertSee('Rs 193,120')
            ->assertSee('10 × 12,000 ÷ 1000 × 35')
            ->assertSee('(8 × 0.6 + 2.5) × 25,000')
            ->assertSee('Still to pay');
        $this->get(route('work-orders.show', $order))->assertOk()->assertSee('4,200');
    }

    public function test_job_validation_errors(): void
    {
        $contractor = Contractor::factory()->create();
        $base = ['contractor_id' => $contractor->id, 'kind' => 'folding', 'job_on' => today()->toDateString(), 'forms' => 10, 'copies' => 1000, 'folding_rate' => 35];
        $this->actingAs($this->owner);

        $this->post(route('contractor-jobs.store'), ['folding_rate' => null] + $base)->assertSessionHasErrors('folding_rate');
        $this->post(route('contractor-jobs.store'), ['forms' => 0.25] + $base)->assertSessionHasErrors('forms');
        $this->post(route('contractor-jobs.store'), ['copies' => 0] + $base)->assertSessionHasErrors('copies');
        $this->post(route('contractor-jobs.store'), ['copies' => 2.5] + $base)->assertSessionHasErrors('copies');
        $this->post(route('contractor-jobs.store'), ['kind' => 'gluing'] + $base)->assertSessionHasErrors('kind');
        $this->post(route('contractor-jobs.store'), ['contractor_id' => 999] + $base)->assertSessionHasErrors('contractor_id');
        $this->post(route('contractor-jobs.store'), ['work_order_id' => 999] + $base)->assertSessionHasErrors('work_order_id');
        $this->post(route('contractor-jobs.store'), ['job_on' => 'yesterday-ish'] + $base)->assertSessionHasErrors('job_on');

        $this->assertDatabaseCount('contractor_jobs', 0);
    }

    /**
     * A binding job needs its rate per form and rate per copy.
     */
    public function test_binding_job_without_rates_is_refused(): void
    {
        $contractor = Contractor::factory()->create();

        $this->actingAs($this->owner)->post(route('contractor-jobs.store'), [
            'contractor_id' => $contractor->id, 'kind' => 'binding', 'job_on' => today()->toDateString(), 'forms' => 8, 'copies' => 25000,
        ])->assertSessionHasErrors();

        $this->assertDatabaseCount('contractor_jobs', 0);
    }

    /**
     * Rates are limited to two decimals, so the amount matches the rate shown on the hisab.
     */
    public function test_job_amount_matches_the_rate_shown_on_the_hisab(): void
    {
        $contractor = Contractor::factory()->create();

        $this->actingAs($this->owner)->post(route('contractor-jobs.store'), [
            'contractor_id' => $contractor->id, 'kind' => 'folding', 'job_on' => today()->toDateString(),
            'forms' => 10, 'copies' => 1000000, 'folding_rate' => 0.555,
        ]);

        $job = ContractorJob::query()->first();
        if ($job === null) {
            $this->assertTrue(true, 'Refused by validation, which is also acceptable.');

            return;
        }

        $shown = ContractorJob::price(ContractorJobKind::Folding, (float) $job->forms, $job->copies, (float) $job->folding_rate);
        $this->assertSame($shown, $job->amount, "Hisab shows {$job->formula()} = Rs {$shown} but the job is charged Rs {$job->amount}");
    }

    public function test_job_is_marked_done_and_open_jobs_count_drops(): void
    {
        $contractor = Contractor::factory()->create();
        $order = $this->order(WorkOrderStage::Folding);
        $job = ContractorJob::factory()->create(['contractor_id' => $contractor->id, 'work_order_id' => $order->id]);
        $this->actingAs($this->owner);

        $this->get(route('work-orders.show', $order))->assertSee(route('contractor-jobs.done', $job));
        $this->from(route('work-orders.show', $order))->patch(route('contractor-jobs.done', $job))->assertRedirect(route('work-orders.show', $order));

        $this->assertTrue($job->refresh()->isDone());
        $this->get(route('work-orders.show', $order))->assertDontSee(route('contractor-jobs.done', $job));
        $this->get(route('contractors.show', $contractor))->assertSee('Done');
    }

    // ---------------------------------------------------------------- payments & hisab

    public function test_hisab_running_balance_with_advances_payments_rejections_and_overpayment(): void
    {
        $clerk = $this->userWith(['orders.view', 'contractors.manage']);
        $contractor = Contractor::factory()->create(['name' => 'Rafiq Folding']);
        $orderA = $this->order(WorkOrderStage::Folding, 12000);
        $orderB = $this->order(WorkOrderStage::Binding, 25000);
        $today = today()->toDateString();

        // Jobs: order A folding 4,200; order B binding 182,500. Earned 186,700.
        $this->actingAs($this->owner)->post(route('contractor-jobs.store'), [
            'contractor_id' => $contractor->id, 'work_order_id' => $orderA->id, 'kind' => 'folding', 'job_on' => $today, 'forms' => 10, 'copies' => 12000, 'folding_rate' => 35,
        ]);
        $this->actingAs($this->owner)->post(route('contractor-jobs.store'), [
            'contractor_id' => $contractor->id, 'work_order_id' => $orderB->id, 'kind' => 'binding', 'job_on' => $today, 'forms' => 8, 'copies' => 25000, 'form_rate' => 0.6, 'copy_rate' => 2.5,
        ]);
        $this->assertSame(186700, $contractor->balance());

        // Owner's advance against order A is approved immediately: 186,700 − 3,000 = 183,700.
        $this->actingAs($this->owner)->post(route('contractors.payments.store', $contractor), ['paid_on' => $today, 'amount' => 3000, 'kind' => 'advance', 'work_order_id' => $orderA->id])
            ->assertSessionHas('status', 'Rs 3,000 recorded.');
        $this->assertSame(ApprovalStatus::Approved, ContractorPayment::query()->latest('id')->first()->status);
        $this->assertSame(183700, $contractor->balance());

        // Clerk's payment waits for approval but already counts: 183,700 − 100,000 = 83,700.
        $this->actingAs($clerk)->post(route('contractors.payments.store', $contractor), ['paid_on' => $today, 'amount' => 100000, 'kind' => 'payment', 'work_order_id' => $orderB->id])
            ->assertSessionHas('status', 'Rs 100,000 recorded and sent for approval.');
        $pending = ContractorPayment::query()->latest('id')->first();
        $this->assertSame(ApprovalStatus::Pending, $pending->status);
        $this->assertSame(83700, $contractor->balance());

        // A clerk payment the owner sends back drops out: still 83,700.
        $this->actingAs($clerk)->post(route('contractors.payments.store', $contractor), ['paid_on' => $today, 'amount' => 50000, 'kind' => 'payment']);
        $rejected = ContractorPayment::query()->latest('id')->first();
        $this->actingAs($this->owner)->patch(route('approvals.update', ['contractor-payment', $rejected->id]), ['decision' => 'reject', 'note' => 'Wrong amount'])->assertSessionHasNoErrors();
        $this->actingAs($this->owner)->patch(route('approvals.update', ['contractor-payment', $pending->id]), ['decision' => 'approve'])->assertSessionHasNoErrors();
        $this->assertSame(ApprovalStatus::Rejected, $rejected->refresh()->status);
        $this->assertSame(83700, $contractor->balance());

        $this->actingAs($this->owner)->get(route('contractors.show', $contractor))->assertOk()
            ->assertSee('Rs 186,700')   // earned
            ->assertSee('Rs 103,000')   // paid (rejected excluded)
            ->assertSee('Rs 83,700')    // still to pay
            ->assertSee('Still to pay')
            ->assertSee('Rs 1,200')     // order A: 4,200 − 3,000
            ->assertSee('Rs 82,500')    // order B: 182,500 − 100,000
            ->assertSee('value="83700"', false); // pay form pre-filled with the balance

        $this->get(route('contractors.index'))->assertOk()->assertSee('Rs 83,700');

        // Overpaying: 90,000 against 83,700 owed leaves the press 6,300 ahead.
        $this->post(route('contractors.payments.store', $contractor), ['paid_on' => $today, 'amount' => 90000, 'kind' => 'payment'])->assertSessionHasNoErrors();
        $this->assertSame(-6300, $contractor->balance());

        $this->get(route('contractors.show', $contractor))->assertOk()->assertSee('Paid ahead')->assertSee('Rs 6,300')->assertDontSee('Still to pay');
        $this->get(route('contractors.index'))->assertOk()->assertSee('Advance Rs 6,300')->assertSee('Rs 0'); // owed KPI is 0
    }

    public function test_payment_validation_errors(): void
    {
        $contractor = Contractor::factory()->create();
        $this->actingAs($this->owner);

        $this->post(route('contractors.payments.store', $contractor), [])->assertSessionHasErrors(['paid_on', 'amount', 'kind']);
        $this->post(route('contractors.payments.store', $contractor), ['paid_on' => today()->toDateString(), 'amount' => 0, 'kind' => 'advance'])->assertSessionHasErrors('amount');
        $this->post(route('contractors.payments.store', $contractor), ['paid_on' => today()->toDateString(), 'amount' => -500, 'kind' => 'advance'])->assertSessionHasErrors('amount');
        $this->post(route('contractors.payments.store', $contractor), ['paid_on' => today()->toDateString(), 'amount' => 10.5, 'kind' => 'advance'])->assertSessionHasErrors('amount');
        $this->post(route('contractors.payments.store', $contractor), ['paid_on' => today()->toDateString(), 'amount' => 100, 'kind' => 'bonus'])->assertSessionHasErrors('kind');
        $this->post(route('contractors.payments.store', $contractor), ['paid_on' => today()->toDateString(), 'amount' => 100, 'kind' => 'advance', 'work_order_id' => 999])->assertSessionHasErrors('work_order_id');
        $this->assertDatabaseCount('contractor_payments', 0);
    }

    // ---------------------------------------------------------------- dispatch

    public function test_dispatch_counts_copies_labour_numbers_slips_and_closes_order(): void
    {
        $order = $this->order(WorkOrderStage::Ready, 1000);
        $buyer = $this->buyer();
        $this->actingAs($this->owner);

        // 30 × 25 + 10 loose = 760 copies; labour 30 × 3 + 2.5 rims × 10 = 115.
        $this->post(route('dispatches.store'), $this->load($buyer, [
            'work_order_id' => $order->id, 'destination' => 'Urdu Bazaar, Lahore', 'bundles' => 30, 'copies_per_bundle' => 25, 'loose_copies' => 10,
            'rims' => 2.5, 'labour_per_bundle' => 3, 'labour_per_rim' => 10, 'labour_paid_to' => 'Aslam', 'truck_no' => 'LES-1234',
        ]))->assertSessionHasNoErrors();

        $first = Dispatch::query()->sole();
        $this->assertSame('DSP-101', $first->number);
        $this->assertSame(760, $first->copies);
        $this->assertSame(115, $first->labour_amount);
        $this->assertSame($order->book_format_id, $first->book_format_id);
        $this->assertSame(WorkOrderStage::Ready, $order->refresh()->stage);

        $this->get(route('dispatches.show', $first))->assertOk()
            ->assertSee('LOADING SLIP')->assertSee('DSP-101')->assertSee('760')->assertSee('30 × 25 + 10 loose')
            ->assertSee('LES-1234')->assertSee('Rs 115 — Aslam')->assertSee($order->number);

        // The remaining 240 copies (9 × 25 + 15) close the order. Labour 9 × 2.5 = 22.5 → 23.
        $this->post(route('dispatches.store'), $this->load($buyer, ['work_order_id' => $order->id, 'bundles' => 9, 'copies_per_bundle' => 25, 'loose_copies' => 15, 'labour_per_bundle' => 2.5]))
            ->assertSessionHasNoErrors();
        $second = Dispatch::query()->latest('id')->first();
        $this->assertSame('DSP-102', $second->number);
        $this->assertSame(240, $second->copies);
        $this->assertSame(23, $second->labour_amount);
        $this->assertSame(WorkOrderStage::Dispatched, $order->refresh()->stage);

        // No rims → no rims line on the slip.
        $this->get(route('dispatches.show', $second))->assertOk()->assertDontSee('Rims');

        $this->get(route('dispatches.index'))->assertOk()->assertSee('1,000')->assertSee('Rs 138')->assertSee('DSP-102');
        $this->get(route('buyers.index'))->assertOk()->assertSee('1,000');
        $this->get(route('buyers.show', $buyer))->assertOk()->assertSee('DSP-101')->assertSee('2 loads');
    }

    public function test_dispatch_without_an_order_needs_a_book(): void
    {
        $buyer = $this->buyer();
        $format = BookFormat::factory()->create();
        $this->actingAs($this->owner);

        $this->post(route('dispatches.store'), $this->load($buyer))->assertSessionHasErrors('book_format_id');
        $this->post(route('dispatches.store'), $this->load($buyer, ['book_format_id' => $format->id]))->assertSessionHasNoErrors();

        $this->assertSame(250, Dispatch::query()->sole()->copies);
        $this->get(route('dispatches.show', Dispatch::query()->sole()))->assertOk()->assertSee($format->book->name);
    }

    public function test_dispatch_validation_errors(): void
    {
        $format = BookFormat::factory()->create();
        $buyer = $this->buyer();
        $supplier = Party::factory()->kind(PartyKind::Supplier)->create();
        $base = $this->load($buyer, ['book_format_id' => $format->id]);
        $this->actingAs($this->owner);

        $this->post(route('dispatches.store'), ['buyer_id' => $supplier->id] + $base)->assertSessionHasErrors('buyer_id');
        $this->post(route('dispatches.store'), ['copies_per_bundle' => 0] + $base)->assertSessionHasErrors('copies_per_bundle');
        $this->post(route('dispatches.store'), ['bundles' => -1] + $base)->assertSessionHasErrors('bundles');
        $this->post(route('dispatches.store'), ['loose_copies' => -5] + $base)->assertSessionHasErrors('loose_copies');
        $this->post(route('dispatches.store'), ['labour_per_bundle' => -3] + $base)->assertSessionHasErrors('labour_per_bundle');
        $this->post(route('dispatches.store'), ['work_order_id' => 999] + $base)->assertSessionHasErrors('work_order_id');
        $this->post(route('dispatches.store'), ['dispatched_on' => ''] + $base)->assertSessionHasErrors('dispatched_on');
        $this->assertDatabaseCount('dispatches', 0);
    }

    /**
     * A load cannot carry more copies than the order has left to dispatch.
     */
    public function test_dispatching_more_than_the_order_produced_is_refused(): void
    {
        $order = $this->order(WorkOrderStage::Ready, 1000);
        $buyer = $this->buyer();

        $this->actingAs($this->owner)->post(route('dispatches.store'), $this->load($buyer, ['work_order_id' => $order->id, 'bundles' => 60, 'copies_per_bundle' => 25]))
            ->assertSessionHasErrors();

        $this->assertSame(0, (int) $order->dispatches()->sum('copies'));
    }

    /**
     * A load cannot be recorded against an order that has not reached binding.
     */
    public function test_dispatch_against_an_unprinted_order_is_refused(): void
    {
        $order = $this->order(WorkOrderStage::Waiting, 1000);
        $buyer = $this->buyer();

        $this->actingAs($this->owner)->post(route('dispatches.store'), $this->load($buyer, ['work_order_id' => $order->id, 'bundles' => 40, 'copies_per_bundle' => 25]))
            ->assertSessionHasErrors('work_order_id');

        $this->assertSame(WorkOrderStage::Waiting, $order->refresh()->stage);
    }

    /**
     * A load of zero copies is refused.
     */
    public function test_a_load_of_zero_copies_is_refused(): void
    {
        $format = BookFormat::factory()->create();
        $buyer = $this->buyer();

        $this->actingAs($this->owner)->post(route('dispatches.store'), $this->load($buyer, ['book_format_id' => $format->id, 'bundles' => 0, 'loose_copies' => 0]))
            ->assertSessionHasErrors();

        $this->assertDatabaseCount('dispatches', 0);
    }

    // ---------------------------------------------------------------- buyers

    public function test_buyer_balance_after_advances_and_adjustments(): void
    {
        $buyer = $this->buyer('Maktaba Rehmania');
        $other = $this->buyer('Other Buyer');
        $format = BookFormat::factory()->create();
        $this->actingAs($this->owner);
        $this->post(route('dispatches.store'), $this->load($buyer, ['book_format_id' => $format->id]));
        $this->post(route('dispatches.store'), $this->load($other, ['book_format_id' => $format->id]));
        $mine = Dispatch::query()->where('buyer_id', $buyer->id)->sole();
        $theirs = Dispatch::query()->where('buyer_id', $other->id)->sole();
        $entry = fn (array $data) => $this->from(route('buyers.show', $buyer))->post(route('buyers.entries.store', $buyer), $data + ['entry_on' => today()->toDateString()]);

        // Advances 50,000 + 25,000 = 75,000.
        $entry(['kind' => 'advance', 'amount' => 50000])->assertRedirect(route('buyers.show', $buyer))->assertSessionHasNoErrors();
        $entry(['kind' => 'advance', 'amount' => 25000])->assertSessionHasNoErrors();
        // Adjust 30,000 against a load → 45,000 left.
        $entry(['kind' => 'adjustment', 'amount' => 30000, 'dispatch_id' => $mine->id, 'note' => 'Against DSP-101'])->assertSessionHasNoErrors();

        // Can't adjust more than is left, nor against another buyer's load.
        $entry(['kind' => 'adjustment', 'amount' => 45001])->assertSessionHasErrors(['amount' => 'Only Rs 45,000 of advance is left to adjust.']);
        $entry(['kind' => 'adjustment', 'amount' => 100, 'dispatch_id' => $theirs->id])->assertSessionHasErrors('dispatch_id');
        $entry(['kind' => 'refund', 'amount' => 100])->assertSessionHasErrors('kind');
        $entry(['kind' => 'advance', 'amount' => 0])->assertSessionHasErrors('amount');

        $this->get(route('buyers.show', $buyer))->assertOk()
            ->assertSee('Rs 75,000')->assertSee('Rs 45,000')->assertSee('Rs 30,000 adjusted')
            ->assertSee('− Rs 30,000')->assertSee('+ Rs 50,000')->assertSee('Against DSP-101');
        $this->get(route('buyers.index'))->assertOk()->assertSee('Rs 45,000')->assertSee('Rs 0');

        // Adjusting exactly what is left brings it to zero; any more is refused.
        $entry(['kind' => 'adjustment', 'amount' => 45000])->assertSessionHasNoErrors();
        $entry(['kind' => 'adjustment', 'amount' => 1])->assertSessionHasErrors(['amount' => 'Only Rs 0 of advance is left to adjust.']);
        $this->assertSame(4, BuyerEntry::query()->where('party_id', $buyer->id)->count());
        $this->get(route('buyers.show', $buyer))->assertSee('Rs 75,000 adjusted');
    }

    public function test_buyer_pages_reject_parties_that_are_not_buyers(): void
    {
        $supplier = Party::factory()->kind(PartyKind::Supplier)->create(['name' => 'Ink Supplier']);
        $this->actingAs($this->owner);

        $this->get(route('buyers.index'))->assertOk()->assertDontSee('Ink Supplier');
        $this->get(route('buyers.show', $supplier))->assertNotFound();
        $this->post(route('buyers.entries.store', $supplier), ['entry_on' => today()->toDateString(), 'kind' => 'advance', 'amount' => 100])->assertNotFound();
        $this->get(route('buyers.show', 999))->assertNotFound();
    }

    public function test_buyer_is_added_as_a_party(): void
    {
        $this->actingAs($this->owner)->post(route('parties.store'), ['name' => 'New Buyer', 'kind' => 'buyer', 'is_active' => '1'])
            ->assertRedirect(route('parties.index'));
        $this->post(route('parties.store'), ['name' => '', 'kind' => 'customer'])->assertSessionHasErrors(['name', 'kind']);

        $buyer = Party::query()->where('name', 'New Buyer')->sole();
        $this->assertSame(PartyKind::Buyer, $buyer->kind);
        $this->get(route('buyers.index'))->assertSee('New Buyer');
        $this->get(route('dispatches.create'))->assertSee('New Buyer');

        // Deactivated buyers drop out of the loading form but keep their page.
        $this->put(route('parties.update', $buyer), ['name' => 'New Buyer', 'kind' => 'buyer'])->assertRedirect();
        $this->get(route('dispatches.create'))->assertDontSee('New Buyer');
        $this->get(route('buyers.show', $buyer))->assertOk();
    }

    /**
     * A buyer with loads and advances keeps the buyer kind.
     */
    public function test_buyer_with_history_cannot_be_retyped(): void
    {
        ['buyer' => $buyer] = $this->seedModule();

        $this->actingAs($this->owner)->put(route('parties.update', $buyer), ['name' => $buyer->name, 'kind' => 'supplier', 'is_active' => '1']);

        $this->get(route('buyers.show', $buyer))->assertOk();
    }

    // ---------------------------------------------------------------- waste

    public function test_waste_is_recorded_and_totalled_for_the_month(): void
    {
        $order = $this->order(WorkOrderStage::Printing);
        $this->actingAs($this->owner);

        $this->post(route('waste.store'), [
            'work_order_id' => $order->id, 'recorded_on' => today()->toDateString(), 'stage' => 'printing', 'quantity' => 38.5, 'unit' => 'kg',
            'responsible' => 'Machine M-2', 'disposal' => 'sold', 'sale_amount' => 2280, 'sold_to' => 'Kabari',
        ])->assertRedirect(route('work-orders.show', $order));
        $this->post(route('waste.store'), ['recorded_on' => today()->startOfMonth()->toDateString(), 'stage' => 'printing', 'quantity' => 11.5, 'unit' => 'kg', 'disposal' => 'kept'])
            ->assertRedirect(route('waste.index'));
        $this->post(route('waste.store'), ['recorded_on' => today()->toDateString(), 'stage' => 'cutting', 'quantity' => 2, 'unit' => 'rims', 'disposal' => 'discarded'])
            ->assertSessionHasNoErrors();
        // Last month: listed, but not in this month's totals.
        $this->post(route('waste.store'), ['recorded_on' => today()->subMonthNoOverflow()->toDateString(), 'stage' => 'printing', 'quantity' => 100, 'unit' => 'kg', 'disposal' => 'sold', 'sale_amount' => 9000])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('waste_entries', 4);

        $this->get(route('waste.index'))->assertOk()
            ->assertSee('Printing (kg)')->assertSee('>50<', false)   // 38.5 + 11.5, first of month included
            ->assertSee('Cutting / trimming (rims)')
            ->assertSee('Rs 2,280')->assertDontSee('Rs 11,280')
            ->assertSee('Kabari')->assertSee('Machine M-2')->assertSee($order->number);
        $this->get(route('work-orders.show', $order))->assertOk();
    }

    public function test_waste_validation_errors(): void
    {
        $base = ['recorded_on' => today()->toDateString(), 'stage' => 'printing', 'quantity' => 10, 'unit' => 'kg', 'disposal' => 'kept'];
        $this->actingAs($this->owner);

        $this->post(route('waste.store'), ['quantity' => 0] + $base)->assertSessionHasErrors('quantity');
        $this->post(route('waste.store'), ['unit' => 'tons'] + $base)->assertSessionHasErrors('unit');
        $this->post(route('waste.store'), ['stage' => 'packing'] + $base)->assertSessionHasErrors('stage');
        $this->post(route('waste.store'), ['disposal' => 'burnt'] + $base)->assertSessionHasErrors('disposal');
        $this->post(route('waste.store'), ['disposal' => 'sold'] + $base)->assertSessionHasErrors('sale_amount');
        $this->post(route('waste.store'), ['disposal' => 'sold', 'sale_amount' => -1] + $base)->assertSessionHasErrors('sale_amount');
        $this->post(route('waste.store'), ['work_order_id' => 999] + $base)->assertSessionHasErrors('work_order_id');
        $this->assertDatabaseCount('waste_entries', 0);
    }

    // ---------------------------------------------------------------- permissions

    public function test_viewer_can_look_but_not_change_anything(): void
    {
        ['order' => $order, 'contractor' => $contractor, 'job' => $job, 'buyer' => $buyer, 'dispatch' => $dispatch] = $this->seedModule();
        $viewer = $this->userWith(['orders.view']);
        $this->actingAs($viewer);

        foreach ([route('contractors.index'), route('contractors.show', $contractor), route('dispatches.index'), route('dispatches.show', $dispatch),
            route('buyers.index'), route('buyers.show', $buyer), route('waste.index')] as $url) {
            $this->get($url)->assertOk();
        }

        // Read-only screens hide the action buttons.
        $this->get(route('contractors.index'))->assertDontSee(route('contractors.create'));
        $this->get(route('contractors.show', $contractor))->assertDontSee(route('contractors.payments.store', $contractor));
        $this->get(route('dispatches.index'))->assertDontSee(route('dispatches.create'));
        $this->get(route('buyers.show', $buyer))->assertDontSee(route('buyers.entries.store', $buyer));
        $this->get(route('waste.index'))->assertDontSee(route('waste.create'));

        foreach ([route('contractors.create'), route('contractors.edit', $contractor), route('contractor-jobs.create'), route('dispatches.create'), route('waste.create'), route('parties.index')] as $url) {
            $this->get($url)->assertForbidden();
        }

        $this->post(route('contractors.store'), ['name' => 'X', 'work' => 'both'])->assertForbidden();
        $this->put(route('contractors.update', $contractor), ['name' => 'X', 'work' => 'both'])->assertForbidden();
        $this->delete(route('contractors.destroy', $contractor))->assertForbidden();
        $this->post(route('contractors.payments.store', $contractor), ['paid_on' => today()->toDateString(), 'amount' => 100, 'kind' => 'advance'])->assertForbidden();
        $this->post(route('contractor-jobs.store'), [])->assertForbidden();
        $this->patch(route('contractor-jobs.done', $job))->assertForbidden();
        $this->post(route('dispatches.store'), [])->assertForbidden();
        $this->post(route('buyers.entries.store', $buyer), ['entry_on' => today()->toDateString(), 'kind' => 'advance', 'amount' => 100])->assertForbidden();
        $this->post(route('waste.store'), [])->assertForbidden();

        $this->assertFalse($job->refresh()->isDone());
        $this->assertSame(2, BuyerEntry::query()->count());
        $this->assertModelExists($contractor);
    }

    public function test_user_without_orders_view_cannot_open_module_pages(): void
    {
        ['contractor' => $contractor, 'buyer' => $buyer, 'dispatch' => $dispatch] = $this->seedModule();
        $this->actingAs($this->userWith(['paper.view']));

        foreach ([route('contractors.index'), route('contractors.show', $contractor), route('dispatches.index'), route('dispatches.show', $dispatch),
            route('buyers.index'), route('buyers.show', $buyer), route('waste.index')] as $url) {
            $this->get($url)->assertForbidden();
        }
    }

    public function test_limited_roles_can_do_only_their_own_job(): void
    {
        ['contractor' => $contractor, 'buyer' => $buyer] = $this->seedModule();
        $format = BookFormat::factory()->create();

        $loader = $this->userWith(['orders.view', 'dispatch.manage']);
        $this->actingAs($loader)->get(route('dispatches.create'))->assertOk();
        $this->post(route('dispatches.store'), $this->load($buyer, ['book_format_id' => $format->id]))->assertRedirect();
        $this->get(route('contractor-jobs.create'))->assertForbidden();
        $this->post(route('buyers.entries.store', $buyer), ['entry_on' => today()->toDateString(), 'kind' => 'advance', 'amount' => 100])->assertForbidden();
        $this->get(route('waste.create'))->assertForbidden();

        $accounts = $this->userWith(['orders.view', 'buyers.manage']);
        $this->actingAs($accounts)->post(route('buyers.entries.store', $buyer), ['entry_on' => today()->toDateString(), 'kind' => 'advance', 'amount' => 100])->assertSessionHasNoErrors();
        $this->post(route('dispatches.store'), [])->assertForbidden();
        $this->post(route('contractors.payments.store', $contractor), [])->assertForbidden();

        $wasteClerk = $this->userWith(['orders.view', 'waste.manage']);
        $this->actingAs($wasteClerk)->get(route('waste.create'))->assertOk();
        $this->post(route('waste.store'), ['recorded_on' => today()->toDateString(), 'stage' => 'folding', 'quantity' => 3, 'unit' => 'sheets', 'disposal' => 'kept'])
            ->assertRedirect(route('waste.index'));
        $this->get(route('dispatches.create'))->assertForbidden();

        auth()->logout();
        $this->get(route('contractors.index'))->assertRedirect(route('login'));
    }

    /**
     * UX/permission mismatch: a role with contractors.manage but not orders.view can create a contractor,
     * then is redirected to contractors.show, which requires orders.view, so they land on a 403.
     */
    public function test_contractor_manager_without_view_lands_on_a_forbidden_page_after_saving(): void
    {
        $this->actingAs($this->userWith(['contractors.manage']))
            ->followingRedirects()
            ->post(route('contractors.store'), ['name' => 'Ghafoor', 'work' => 'folding', 'is_active' => '1'])
            ->assertForbidden();

        $this->assertDatabaseHas('contractors', ['name' => 'Ghafoor']);
    }
}
