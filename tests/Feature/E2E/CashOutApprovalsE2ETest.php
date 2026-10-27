<?php

namespace Tests\Feature\E2E;

use App\Enums\ApprovalStatus;
use App\Http\Controllers\ApprovalController;
use App\Models\CashFloat;
use App\Models\ContractorPayment;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\ExpenseHead;
use App\Models\ForemanSheet;
use App\Models\Loan;
use App\Models\Machine;
use App\Models\MachineRepair;
use App\Models\PaperReceipt;
use App\Models\Purchase;
use App\Models\Role;
use App\Models\SalaryAdvance;
use App\Models\Setting;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CashOutApprovalsE2ETest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $manager;

    private ExpenseHead $head;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->owner()->create(['name' => 'Owner Sahib']);
        $this->manager = User::factory()->create(['name' => 'Manager Faisal']);
        $this->head = ExpenseHead::factory()->create(['name' => 'Petrol', 'name_ur' => 'پیٹرول', 'is_active' => true]);
    }

    /**
     * @param  list<string>  $permissions
     */
    private function userWith(array $permissions): User
    {
        return User::factory()->create(['role_id' => Role::factory()->create(['permissions' => $permissions])->id]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function expensePayload(array $overrides = []): array
    {
        return $overrides + [
            'spent_on' => today()->toDateString(),
            'expense_head_id' => $this->head->id,
            'paid_to' => 'Shell, Ravi Road',
            'amount' => 1000,
            'reason' => 'Petrol for van',
        ];
    }

    private function spend(User $user, int $amount, array $overrides = []): Expense
    {
        $this->actingAs($user)->post(route('expenses.store'), $this->expensePayload(['amount' => $amount] + $overrides))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('expenses.index'));

        return Expense::query()->latest('id')->firstOrFail();
    }

    private function setLimitViaSettings(int $limit): void
    {
        $this->actingAs($this->owner)->put(route('settings.update'), [
            'manager_daily_limit' => $limit,
            'default_wastage_percent' => 3,
            'ot_divisor' => 6,
            'ot_multiplier' => 1,
            'standard_hours' => 8,
            'unmarked_as' => 'present',
            'mid_month_day' => 22,
            'mid_month_percent' => 50,
            'pay_day' => 10,
        ])->assertSessionHasNoErrors()->assertRedirect(route('settings.edit'));
    }

    // ---------------------------------------------------------------- pages

    public function test_owner_can_open_every_cash_out_and_approvals_page(): void
    {
        Expense::factory()->create(['expense_head_id' => $this->head->id, 'user_id' => $this->manager->id, 'created_by' => $this->manager->id]);
        CashFloat::factory()->create(['user_id' => $this->manager->id, 'amount' => 5000]);

        $this->actingAs($this->owner);
        $this->get(route('expenses.index'))->assertOk()->assertSee('Cash-out')->assertSee('Give cash to manager')->assertSee('Manager Faisal');
        $this->get(route('expenses.create'))->assertOk()->assertSee('Save expense')->assertSee('Petrol');
        $this->get(route('expense-heads.index'))->assertOk()->assertSee('Petrol');
        $this->get(route('expense-heads.create'))->assertOk();
        $this->get(route('expense-heads.edit', $this->head))->assertOk()->assertSee('پیٹرول');
        $this->get(route('approvals.index'))->assertOk()->assertSee('Petrol');
        $this->get(route('settings.edit'))->assertOk();
    }

    public function test_pages_render_in_urdu(): void
    {
        Expense::factory()->create(['expense_head_id' => $this->head->id, 'user_id' => $this->manager->id, 'created_by' => $this->manager->id, 'paid_to' => 'Shell']);

        $this->actingAs($this->owner)->get(route('locale', 'ur'))->assertRedirect();
        $this->assertSame('ur', $this->owner->refresh()->locale);

        $this->get(route('expenses.index'))->assertOk()->assertSee('اخراجات')->assertSee('پیٹرول')->assertSee('منتظر');
        $this->get(route('expenses.create'))->assertOk()->assertSee('خرچ محفوظ کریں');
        $this->get(route('expense-heads.index'))->assertOk()->assertSee('اخراجات کی مدات');
        $this->get(route('expense-heads.edit', $this->head))->assertOk();
        $this->get(route('approvals.index'))->assertOk()->assertSee('منظوری')->assertSee('پیٹرول')->assertSee('Shell کو ادا، روزانہ حد سے زیادہ', false);
    }

    public function test_urdu_flash_after_saving_an_over_limit_expense(): void
    {
        $this->withSession(['locale' => 'ur']);
        $this->spend($this->manager, 500);

        $this->get(route('expenses.index'))->assertOk()->assertSee('روزانہ حد سے زیادہ — منظوری کے لیے مالک کو بھیج دیا گیا۔');
    }

    public function test_empty_approvals_page_shows_all_clear(): void
    {
        $this->actingAs($this->owner)->get(route('approvals.index'))->assertOk()->assertSee('All clear');
    }

    // ---------------------------------------------------------------- permissions

    public function test_viewer_only_role_sees_index_but_cannot_add_or_manage(): void
    {
        $viewer = $this->userWith(['expenses.view']);

        $this->actingAs($viewer)->get(route('expenses.index'))->assertOk()
            ->assertDontSee(route('expenses.create'))
            ->assertDontSee(route('cash-floats.store'))
            ->assertDontSee(route('expense-heads.index'));
        $this->get(route('expenses.create'))->assertForbidden();
        $this->post(route('expenses.store'), $this->expensePayload())->assertForbidden();
        $this->get(route('expense-heads.index'))->assertForbidden();
        $this->post(route('expense-heads.store'), ['name' => 'X', 'color' => '#000000'])->assertForbidden();
        $this->post(route('cash-floats.store'), ['given_on' => today()->toDateString(), 'user_id' => $viewer->id, 'amount' => 10])->assertForbidden();
        $this->get(route('approvals.index'))->assertForbidden();
        $this->get(route('settings.edit'))->assertForbidden();

        $this->assertSame(0, Expense::count());
        $this->assertSame(0, CashFloat::count());
    }

    public function test_role_without_any_cash_key_is_forbidden_everywhere(): void
    {
        $nobody = $this->userWith(['paper.view']);
        $expense = Expense::factory()->create(['expense_head_id' => $this->head->id, 'created_by' => $this->manager->id]);

        $this->actingAs($nobody);
        $this->get(route('expenses.index'))->assertForbidden();
        $this->get(route('expenses.create'))->assertForbidden();
        $this->get(route('expense-heads.edit', $this->head))->assertForbidden();
        $this->put(route('expense-heads.update', $this->head), ['name' => 'Hacked', 'color' => '#000000'])->assertForbidden();
        $this->delete(route('expense-heads.destroy', $this->head))->assertForbidden();
        $this->patch(route('approvals.update', ['expense', $expense->id]), ['decision' => 'approve'])->assertForbidden();

        $this->assertSame('Petrol', $this->head->refresh()->name);
        $this->assertTrue($expense->refresh()->isPending());
    }

    public function test_creator_only_role_can_add_but_not_view_list(): void
    {
        $clerk = $this->userWith(['expenses.create']);

        $this->actingAs($clerk)->get(route('expenses.create'))->assertOk();
        $this->post(route('expenses.store'), $this->expensePayload())->assertRedirect(route('expenses.index'));
        $this->assertSame(1, Expense::count());

        // UX: the store redirects to expenses.index which this role cannot open.
        $this->get(route('expenses.index'))->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('expenses.index'))->assertRedirect(route('login'));
        $this->get(route('approvals.index'))->assertRedirect(route('login'));
        $this->post(route('expenses.store'), $this->expensePayload())->assertRedirect(route('login'));
    }

    public function test_bell_and_nav_count_only_for_approvers(): void
    {
        Expense::factory()->count(2)->create(['expense_head_id' => $this->head->id, 'created_by' => $this->manager->id]);
        MachineRepair::factory()->create(['created_by' => $this->manager->id]);

        $this->actingAs($this->owner)->get(route('expenses.index'))->assertOk()
            ->assertSee('<span class="count">3</span>', false)
            ->assertSee('<span class="dot"></span>', false);

        $this->actingAs($this->manager)->get(route('expenses.index'))->assertOk()
            ->assertDontSee('<span class="dot"></span>', false)
            ->assertDontSee(route('approvals.index'));
    }

    public function test_bell_has_no_dot_when_nothing_waits(): void
    {
        $this->actingAs($this->owner)->get(route('expenses.index'))->assertOk()
            ->assertDontSee('<span class="dot"></span>', false);
    }

    // ---------------------------------------------------------------- expense create + validation

    public function test_expense_validation_errors(): void
    {
        $inactive = ExpenseHead::factory()->create(['is_active' => false]);

        $this->actingAs($this->manager)->post(route('expenses.store'), [])
            ->assertSessionHasErrors(['spent_on', 'expense_head_id', 'paid_to', 'amount', 'reason']);

        $this->post(route('expenses.store'), $this->expensePayload(['amount' => 0]))->assertSessionHasErrors('amount');
        $this->post(route('expenses.store'), $this->expensePayload(['amount' => -5]))->assertSessionHasErrors('amount');
        $this->post(route('expenses.store'), $this->expensePayload(['amount' => '12.5']))->assertSessionHasErrors('amount');
        $this->post(route('expenses.store'), $this->expensePayload(['spent_on' => 'not-a-date']))->assertSessionHasErrors('spent_on');
        $this->post(route('expenses.store'), $this->expensePayload(['expense_head_id' => $inactive->id]))->assertSessionHasErrors('expense_head_id');
        $this->post(route('expenses.store'), $this->expensePayload(['expense_head_id' => 99999]))->assertSessionHasErrors('expense_head_id');
        $this->post(route('expenses.store'), $this->expensePayload(['machine_id' => 99999]))->assertSessionHasErrors('machine_id');
        $this->post(route('expenses.store'), $this->expensePayload(['paid_to' => str_repeat('a', 121)]))->assertSessionHasErrors('paid_to');
        $this->post(route('expenses.store'), $this->expensePayload(['reason' => str_repeat('a', 256)]))->assertSessionHasErrors('reason');

        $this->assertSame(0, Expense::count());
    }

    public function test_inactive_head_is_not_offered_on_create(): void
    {
        ExpenseHead::factory()->create(['name' => 'Retiredhead', 'is_active' => false]);

        $this->actingAs($this->manager)->get(route('expenses.create'))->assertOk()
            ->assertSee('Petrol')->assertDontSee('Retiredhead');
    }

    public function test_expense_with_machine_and_receipt_upload(): void
    {
        Storage::fake('public');
        $machine = Machine::factory()->create();

        $expense = $this->spend($this->owner, 2500, [
            'machine_id' => $machine->id,
            'attachment' => UploadedFile::fake()->image('bill.jpg'),
        ]);

        $this->assertNotNull($expense->attachment);
        $this->assertStringStartsWith('attachments/expenses/', $expense->attachment);
        Storage::disk('public')->assertExists($expense->attachment);
        $this->assertSame($machine->id, $expense->machine_id);

        $this->get(route('expenses.index'))->assertOk()
            ->assertSee(Storage::disk('public')->url($expense->attachment))
            ->assertSee($machine->code);
    }

    public function test_pdf_receipt_is_accepted_and_bad_files_are_rejected(): void
    {
        Storage::fake('public');

        $this->actingAs($this->owner)->post(route('expenses.store'), $this->expensePayload([
            'attachment' => UploadedFile::fake()->create('bill.pdf', 200, 'application/pdf'),
        ]))->assertSessionHasNoErrors();

        $this->post(route('expenses.store'), $this->expensePayload([
            'attachment' => UploadedFile::fake()->create('virus.exe', 10, 'application/octet-stream'),
        ]))->assertSessionHasErrors('attachment');

        $this->post(route('expenses.store'), $this->expensePayload([
            'attachment' => UploadedFile::fake()->image('huge.jpg')->size(9000),
        ]))->assertSessionHasErrors('attachment');

        $this->assertSame(1, Expense::count());
        $this->assertCount(1, Storage::disk('public')->allFiles('attachments/expenses'));
    }

    public function test_pending_expense_attachment_is_linked_on_the_approvals_screen(): void
    {
        Storage::fake('public');
        $expense = $this->spend($this->manager, 700, ['attachment' => UploadedFile::fake()->image('slip.png')]);

        $this->assertTrue($expense->isPending());
        $this->actingAs($this->owner)->get(route('approvals.index'))->assertOk()
            ->assertSee(Storage::disk('public')->url($expense->attachment));
    }

    public function test_expense_cannot_be_edited_or_deleted_over_http(): void
    {
        $expense = $this->spend($this->owner, 1000);

        $this->actingAs($this->owner)->put('/expenses/'.$expense->id, $this->expensePayload(['amount' => 5]))->assertNotFound();
        $this->delete('/expenses/'.$expense->id)->assertNotFound();
        $this->get('/expenses/'.$expense->id.'/edit')->assertNotFound();

        $this->assertSame(1000, $expense->refresh()->amount);
    }

    public function test_index_filters_by_head(): void
    {
        $diesel = ExpenseHead::factory()->create(['name' => 'Diesel']);
        $this->spend($this->owner, 100, ['reason' => 'Van petrol run']);
        $this->spend($this->owner, 200, ['expense_head_id' => $diesel->id, 'reason' => 'Generator diesel run']);

        $this->get(route('expenses.index', ['head' => $diesel->id]))->assertOk()
            ->assertSee('Generator diesel run')->assertDontSee('Van petrol run');
        $this->get(route('expenses.index'))->assertOk()
            ->assertSee('Generator diesel run')->assertSee('Van petrol run');
    }

    // ---------------------------------------------------------------- daily limit

    public function test_owner_entries_auto_approve_regardless_of_limit(): void
    {
        $this->setLimitViaSettings(100);

        $expense = $this->spend($this->owner, 999999);

        $this->assertSame(ApprovalStatus::Approved, $expense->status);
        $this->assertSame($this->owner->id, $expense->reviewed_by);
        $this->get(route('expenses.index'))->assertSee('Expense saved.');
        $this->get(route('approvals.index'))->assertSee('All clear');
    }

    public function test_limit_boundary_exactly_at_and_one_over(): void
    {
        $this->setLimitViaSettings(5000);

        $atLimit = $this->spend($this->manager, 5000);
        $this->assertSame(ApprovalStatus::Approved, $atLimit->status);
        $this->assertNull($atLimit->reviewed_by);

        $over = $this->spend($this->manager, 1);
        $this->assertSame(ApprovalStatus::Pending, $over->status);
        $this->get(route('expenses.index'))->assertSee('Over the daily limit');
    }

    public function test_single_spend_over_limit_waits_even_on_an_empty_day(): void
    {
        $this->setLimitViaSettings(5000);

        $this->assertSame(ApprovalStatus::Pending, $this->spend($this->manager, 5001)->status);
    }

    public function test_limit_is_per_manager_and_per_day(): void
    {
        $this->setLimitViaSettings(3000);
        $other = User::factory()->create();

        $this->assertSame(ApprovalStatus::Approved, $this->spend($this->manager, 3000)->status);
        $this->assertSame(ApprovalStatus::Approved, $this->spend($other, 3000)->status);

        // Tomorrow's limit is fresh (well: any other date — see the backdating test).
        $this->travel(1)->days();
        $this->assertSame(ApprovalStatus::Approved, $this->spend($this->manager, 3000)->status);
    }

    public function test_rejected_spend_frees_up_the_limit_and_pending_spend_consumes_it(): void
    {
        $this->setLimitViaSettings(5000);

        $big = $this->spend($this->manager, 6000);
        $this->assertTrue($big->isPending());

        // The pending 6000 already counts against today, so a small spend also waits.
        $this->assertTrue($this->spend($this->manager, 100)->isPending());

        $this->actingAs($this->owner)->patch(route('approvals.update', ['expense', $big->id]), ['decision' => 'reject', 'note' => 'Too much'])
            ->assertSessionHasNoErrors();

        // 100 (pending) + 4900 = 5000 → within the limit once the 6000 is out.
        $this->assertSame(ApprovalStatus::Approved, $this->spend($this->manager, 4900)->status);
    }

    public function test_limit_zero_means_every_manager_spend_waits(): void
    {
        $this->setLimitViaSettings(0);

        $this->assertTrue($this->spend($this->manager, 1)->isPending());
        $this->actingAs($this->owner)->get(route('expenses.index'))->assertSee('No daily limit set');
    }

    public function test_settings_limit_validation(): void
    {
        $this->actingAs($this->owner)->put(route('settings.update'), ['manager_daily_limit' => -1])
            ->assertSessionHasErrors('manager_daily_limit');
        $this->actingAs($this->manager)->put(route('settings.update'), ['manager_daily_limit' => 999999])
            ->assertForbidden();
        $this->assertNull(Setting::get(Setting::ManagerDailyLimit));
    }

    public function test_non_owner_role_with_approve_key_auto_approves_own_spends(): void
    {
        Setting::put(Setting::ManagerDailyLimit, 100);
        $deputy = $this->userWith(['expenses.view', 'expenses.create', 'approve']);

        $expense = $this->spend($deputy, 50000);

        $this->assertSame(ApprovalStatus::Approved, $expense->status);
        $this->assertSame($deputy->id, $expense->reviewed_by);
    }

    /**
     * Future-dated spends are refused and backdated ones still count against today's limit.
     */
    public function test_daily_limit_cannot_be_dodged_by_dating_spends_on_other_days(): void
    {
        Setting::put(Setting::ManagerDailyLimit, 5000);

        $this->assertTrue($this->spend($this->manager, 5000)->status === ApprovalStatus::Approved);
        $this->actingAs($this->manager)->post(route('expenses.store'), $this->expensePayload(['amount' => 5000, 'spent_on' => today()->addYear()->toDateString()]))
            ->assertSessionHasErrors('spent_on');
        $past = $this->spend($this->manager, 5000, ['spent_on' => today()->subDay()->toDateString()]);

        $this->assertSame(ApprovalStatus::Pending, $past->status, 'Backdated spend entered today was auto-approved');
    }

    /**
     * A non-ISO date (e.g. "10/27/2026") is stored as Y-m-d and still counts against the daily limit.
     */
    public function test_non_iso_date_format_does_not_bypass_the_daily_limit(): void
    {
        Setting::put(Setting::ManagerDailyLimit, 5000);

        $this->spend($this->manager, 5000);
        $second = $this->spend($this->manager, 5000, ['spent_on' => today()->format('m/d/Y')]);

        $this->assertTrue($second->spent_on->isToday());
        $this->assertSame(ApprovalStatus::Pending, $second->status, 'Second 5000 on the same day slipped through the limit');
    }

    // ---------------------------------------------------------------- cash totals

    public function test_cash_kpis_and_cash_in_hand(): void
    {
        Setting::put(Setting::ManagerDailyLimit, 10000);

        $this->actingAs($this->owner)->post(route('cash-floats.store'), [
            'given_on' => today()->toDateString(), 'user_id' => $this->manager->id, 'amount' => 50000, 'note' => 'Weekly float',
        ])->assertSessionHasNoErrors()->assertRedirect(route('expenses.index'));

        $this->spend($this->manager, 4000);              // approved
        $pending = $this->spend($this->manager, 7000);   // pending (11000 > 10000)
        $rejected = $this->spend($this->manager, 9000);  // pending, then rejected
        $this->actingAs($this->owner)->patch(route('approvals.update', ['expense', $rejected->id]), ['decision' => 'reject', 'note' => 'No'])->assertSessionHasNoErrors();
        $this->spend($this->owner, 2500);                 // owner has no float → not in "cash spent"

        $this->assertTrue($pending->refresh()->isPending());
        $this->assertSame(50000 - 4000 - 7000, $this->manager->cashInHand());

        $this->actingAs($this->owner)->get(route('expenses.index'))->assertOk()
            ->assertSeeInOrder(['Cash with manager', 'Rs 39,000', 'Total given', 'Rs 50,000', 'Total spent', 'Rs 11,000', 'Spent today', 'Rs 13,500'])
            ->assertSee('Daily limit Rs 10,000')
            ->assertSee('Weekly float')
            ->assertSee('Sent back');
    }

    public function test_cash_float_validation(): void
    {
        $this->actingAs($this->owner)->post(route('cash-floats.store'), [])
            ->assertSessionHasErrors(['given_on', 'user_id', 'amount']);
        $this->post(route('cash-floats.store'), ['given_on' => today()->toDateString(), 'user_id' => 999, 'amount' => 0])
            ->assertSessionHasErrors(['user_id', 'amount']);
        $this->assertSame(0, CashFloat::count());
    }

    // ---------------------------------------------------------------- expense heads

    public function test_expense_head_crud(): void
    {
        $this->actingAs($this->owner)->post(route('expense-heads.store'), [
            'name' => 'Diesel', 'name_ur' => 'ڈیزل', 'color' => '#C2412D', 'is_active' => '1',
        ])->assertSessionHasNoErrors()->assertRedirect(route('expense-heads.index'));

        $diesel = ExpenseHead::query()->where('name', 'Diesel')->firstOrFail();
        $this->assertTrue($diesel->is_active);

        $this->put(route('expense-heads.update', $diesel), ['name' => 'Diesel', 'name_ur' => '', 'color' => '#262B7E', 'is_active' => '0'])
            ->assertSessionHasNoErrors();
        $diesel->refresh();
        $this->assertFalse($diesel->is_active);
        $this->assertSame('#262B7E', $diesel->color);
        $this->assertNull($diesel->name_ur);

        // Unchecked box sends nothing at all → inactive.
        $this->put(route('expense-heads.update', $this->head), ['name' => 'Petrol', 'color' => '#262B7E']);
        $this->assertFalse($this->head->refresh()->is_active);

        $this->delete(route('expense-heads.destroy', $diesel))->assertRedirect(route('expense-heads.index'));
        $this->assertModelMissing($diesel);
    }

    public function test_expense_head_validation(): void
    {
        $this->actingAs($this->owner)->post(route('expense-heads.store'), ['name' => 'Petrol', 'color' => 'red'])
            ->assertSessionHasErrors(['name', 'color']);
        $this->post(route('expense-heads.store'), [])->assertSessionHasErrors(['name', 'color']);
        $this->post(route('expense-heads.store'), ['name' => str_repeat('x', 61), 'color' => '#FFFFFF'])->assertSessionHasErrors('name');
        $this->post(route('expense-heads.store'), ['name' => 'Ok', 'color' => '#FFF'])->assertSessionHasErrors('color');

        // Renaming a head to its own name is allowed.
        $this->put(route('expense-heads.update', $this->head), ['name' => 'Petrol', 'color' => '#FFFFFF', 'is_active' => '1'])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, ExpenseHead::count());
    }

    public function test_head_with_expenses_cannot_be_deleted_and_index_totals_skip_rejected(): void
    {
        Setting::put(Setting::ManagerDailyLimit, 1000);
        $this->spend($this->owner, 3000);
        $rejected = $this->spend($this->manager, 2000);
        $rejected->reject($this->owner, 'no');
        $this->travel(-40)->days();
        $this->spend($this->owner, 700);
        $this->travelBack();

        $this->actingAs($this->owner)->from(route('expense-heads.index'))
            ->delete(route('expense-heads.destroy', $this->head))
            ->assertRedirect(route('expense-heads.index'))
            ->assertSessionHasErrors('head');
        $this->assertModelExists($this->head);

        $head = ExpenseHead::query()->withCount('expenses')
            ->withSum(['expenses' => fn ($q) => $q->notRejected()], 'amount')->find($this->head->id);
        $this->assertSame(3, $head->expenses_count);
        $this->assertSame(3700, (int) $head->expenses_sum_amount);

        $this->get(route('expense-heads.index'))->assertOk()->assertSee('3,700')->assertSee('3,000');
    }

    // ---------------------------------------------------------------- approvals: expense

    public function test_manager_over_limit_expense_full_approval_flow(): void
    {
        $this->setLimitViaSettings(1000);
        $expense = $this->spend($this->manager, 4500, ['reason' => 'New belt for folder']);
        $this->assertTrue($expense->isPending());

        $this->actingAs($this->owner)->get(route('approvals.index'))->assertOk()
            ->assertSee('New belt for folder')->assertSee('Rs 4,500')->assertSee('by Manager Faisal', false);

        $this->from(route('approvals.index'))
            ->patch(route('approvals.update', ['expense', $expense->id]), ['decision' => 'approve', 'note' => 'ok'])
            ->assertRedirect(route('approvals.index'))->assertSessionHas('status', 'Approved.');

        $expense->refresh();
        $this->assertSame(ApprovalStatus::Approved, $expense->status);
        $this->assertSame($this->owner->id, $expense->reviewed_by);
        $this->assertNotNull($expense->reviewed_at);
        $this->assertSame('ok', $expense->review_note);

        $this->get(route('approvals.index'))->assertSee('All clear')->assertDontSee('<span class="dot"></span>', false);
    }

    public function test_rejecting_needs_a_note_and_shows_on_roznamcha(): void
    {
        $expense = $this->spend($this->manager, 800);

        $this->actingAs($this->owner)->patch(route('approvals.update', ['expense', $expense->id]), ['decision' => 'reject'])
            ->assertSessionHasErrors('note');
        $this->patch(route('approvals.update', ['expense', $expense->id]), ['decision' => 'reject', 'note' => str_repeat('n', 256)])
            ->assertSessionHasErrors('note');
        $this->patch(route('approvals.update', ['expense', $expense->id]), ['decision' => 'maybe'])
            ->assertSessionHasErrors('decision');
        $this->assertTrue($expense->refresh()->isPending());

        $this->patch(route('approvals.update', ['expense', $expense->id]), ['decision' => 'reject', 'note' => 'Get a proper bill'])
            ->assertSessionHas('status', 'Sent back to the manager.');
        $this->assertSame(ApprovalStatus::Rejected, $expense->refresh()->status);

        $this->actingAs($this->manager)->get(route('expenses.index'))->assertOk()->assertSee('Sent back')->assertSee('Get a proper bill');
    }

    public function test_approving_twice_or_after_rejection_is_409(): void
    {
        $a = $this->spend($this->manager, 100);
        $b = $this->spend($this->manager, 100);

        $this->actingAs($this->owner)->patch(route('approvals.update', ['expense', $a->id]), ['decision' => 'approve'])->assertRedirect();
        $this->patch(route('approvals.update', ['expense', $a->id]), ['decision' => 'approve'])->assertStatus(409);
        $this->patch(route('approvals.update', ['expense', $a->id]), ['decision' => 'reject', 'note' => 'oops'])->assertStatus(409);

        $this->patch(route('approvals.update', ['expense', $b->id]), ['decision' => 'reject', 'note' => 'no'])->assertRedirect();
        $this->patch(route('approvals.update', ['expense', $b->id]), ['decision' => 'approve'])->assertStatus(409);

        $this->assertSame(ApprovalStatus::Approved, $a->refresh()->status);
        $this->assertSame(ApprovalStatus::Rejected, $b->refresh()->status);
    }

    public function test_auto_approved_expense_cannot_be_decided_via_approvals(): void
    {
        Setting::put(Setting::ManagerDailyLimit, 1000);
        $expense = $this->spend($this->manager, 500);

        $this->actingAs($this->owner)->patch(route('approvals.update', ['expense', $expense->id]), ['decision' => 'reject', 'note' => 'x'])
            ->assertStatus(409);
    }

    public function test_unknown_type_or_missing_id_is_404(): void
    {
        $this->actingAs($this->owner)->patch(route('approvals.update', ['banana', 1]), ['decision' => 'approve'])->assertNotFound();
        $this->patch(route('approvals.update', ['expense', 99999]), ['decision' => 'approve'])->assertNotFound();

        // A non-numeric id is not a route match.
        $this->patch('/approvals/expense/abc', ['decision' => 'approve'])->assertNotFound();
    }

    public function test_approver_cannot_decide_own_pending_entry(): void
    {
        $deputy = $this->userWith(['approve']);
        $expense = Expense::factory()->create(['expense_head_id' => $this->head->id, 'created_by' => $deputy->id, 'status' => ApprovalStatus::Pending]);

        $this->actingAs($deputy)->patch(route('approvals.update', ['expense', $expense->id]), ['decision' => 'approve'])->assertForbidden();
        $this->assertTrue($expense->refresh()->isPending());
    }

    public function test_limited_approver_role_can_use_approvals_screen(): void
    {
        $approver = $this->userWith(['approve']);
        $expense = $this->spend($this->manager, 300);

        $this->actingAs($approver)->get(route('approvals.index'))->assertOk()->assertSee('Petrol');
        $this->patch(route('approvals.update', ['expense', $expense->id]), ['decision' => 'approve'])->assertRedirect();
        $this->assertSame($approver->id, $expense->refresh()->reviewed_by);
    }

    // ---------------------------------------------------------------- approvals: every other type

    public function test_every_item_type_shows_on_the_queue_and_can_be_approved(): void
    {
        $by = ['created_by' => $this->manager->id];
        $receipt = PaperReceipt::factory()->create($by + ['quantity' => 1000]);
        $items = [
            'paper-receipt' => $receipt,
            'purchase' => Purchase::factory()->create($by),
            'expense' => Expense::factory()->create($by + ['expense_head_id' => $this->head->id]),
            'repair' => MachineRepair::factory()->create($by),
            'advance' => SalaryAdvance::factory()->create($by),
            'foreman-sheet' => ForemanSheet::factory()->create($by),
            'loan' => Loan::factory()->create($by),
            'work-order' => WorkOrder::factory()->create($by + ['paper_type_id' => $receipt->paper_type_id, 'party_id' => $receipt->party_id, 'rims_needed' => 200]),
            'contractor-payment' => ContractorPayment::factory()->create($by),
        ];

        foreach ($items as $model) {
            $this->assertTrue($model->refresh()->isPending(), $model::class.' should start pending');
        }

        $page = $this->actingAs($this->owner)->get(route('approvals.index'))->assertOk()
            ->assertSee('<span class="count">9</span>', false);
        foreach ($items as $type => $model) {
            $page->assertSee(route('approvals.update', [$type, $model->id]));
        }

        foreach ($items as $type => $model) {
            $this->from(route('approvals.index'))->patch(route('approvals.update', [$type, $model->id]), ['decision' => 'approve'])
                ->assertSessionHasNoErrors()->assertRedirect(route('approvals.index'));
            $this->assertSame(ApprovalStatus::Approved, $model->refresh()->status, "$type not approved");
        }

        $this->assertNotNull($items['work-order']->refresh()->paper_issue_id);
        $this->get(route('approvals.index'))->assertSee('All clear');
    }

    public function test_every_item_type_can_be_sent_back(): void
    {
        $by = ['created_by' => $this->manager->id];
        $items = [
            'paper-receipt' => PaperReceipt::factory()->create($by),
            'purchase' => Purchase::factory()->create($by),
            'repair' => MachineRepair::factory()->create($by),
            'advance' => SalaryAdvance::factory()->create($by),
            'foreman-sheet' => ForemanSheet::factory()->create($by),
            'loan' => Loan::factory()->create($by),
            'work-order' => WorkOrder::factory()->create($by),
            'contractor-payment' => ContractorPayment::factory()->create($by),
        ];

        $this->actingAs($this->owner);
        foreach ($items as $type => $model) {
            $this->patch(route('approvals.update', [$type, $model->id]), ['decision' => 'reject', 'note' => 'Check again'])
                ->assertSessionHasNoErrors();
            $this->assertSame(ApprovalStatus::Rejected, $model->refresh()->status, "$type not rejected");
        }

        $this->assertNull($items['work-order']->refresh()->paper_issue_id);
    }

    public function test_work_order_blocker_stops_approval_but_not_rejection(): void
    {
        $order = WorkOrder::factory()->create(['created_by' => $this->manager->id, 'rims_needed' => 500]);

        $this->actingAs($this->owner)->get(route('approvals.index'))->assertOk();
        $this->from(route('approvals.index'))->patch(route('approvals.update', ['work-order', $order->id]), ['decision' => 'approve'])
            ->assertRedirect(route('approvals.index'))
            ->assertSessionHasErrors('decision');
        $this->assertTrue($order->refresh()->isPending());
        $this->assertNull($order->paper_issue_id);

        $this->from(route('approvals.index'))->followingRedirects()
            ->patch(route('approvals.update', ['work-order', $order->id]), ['decision' => 'approve'])
            ->assertOk()->assertSee('Only 0 rims of this paper are in stock');

        $this->patch(route('approvals.update', ['work-order', $order->id]), ['decision' => 'reject', 'note' => 'No paper'])
            ->assertSessionHasNoErrors();
        $this->assertSame(ApprovalStatus::Rejected, $order->refresh()->status);
    }

    public function test_work_order_blocker_boundary_exact_stock_passes(): void
    {
        $receipt = PaperReceipt::factory()->create(['created_by' => $this->owner->id, 'quantity' => 300]);
        $exact = WorkOrder::factory()->create(['created_by' => $this->manager->id, 'paper_type_id' => $receipt->paper_type_id, 'party_id' => $receipt->party_id, 'rims_needed' => 300]);
        $next = WorkOrder::factory()->create(['created_by' => $this->manager->id, 'paper_type_id' => $receipt->paper_type_id, 'party_id' => $receipt->party_id, 'rims_needed' => 1]);

        $this->actingAs($this->owner)->patch(route('approvals.update', ['work-order', $exact->id]), ['decision' => 'approve'])
            ->assertSessionHasNoErrors();
        $this->assertNotNull($exact->refresh()->paper_issue_id);

        $this->patch(route('approvals.update', ['work-order', $next->id]), ['decision' => 'approve'])
            ->assertSessionHasErrors('decision');
        $this->assertTrue($next->refresh()->isPending());
    }

    public function test_foreman_sheet_advances_are_hidden_from_queue_and_follow_the_sheet(): void
    {
        $sheet = ForemanSheet::factory()->create(['created_by' => $this->manager->id]);
        $advance = SalaryAdvance::factory()->create(['created_by' => $this->manager->id, 'foreman_sheet_id' => $sheet->id, 'employee_id' => Employee::factory()->create()->id]);

        $this->assertCount(1, app(ApprovalController::class)->pendingItems());
        $this->actingAs($this->owner)->get(route('approvals.index'))->assertOk()
            ->assertSee('<span class="count">1</span>', false)
            ->assertDontSee(route('approvals.update', ['advance', $advance->id]));

        $this->patch(route('approvals.update', ['foreman-sheet', $sheet->id]), ['decision' => 'approve'])->assertSessionHasNoErrors();
        $this->assertSame(ApprovalStatus::Approved, $advance->refresh()->status);
        $this->assertSame($this->owner->id, $advance->reviewed_by);
    }

    /**
     * SUSPICIOUS: an advance that belongs to a pending foreman sheet is hidden from the queue but can
     * still be decided directly over PATCH, leaving it out of step with its sheet.
     */
    public function test_sheet_advance_can_be_decided_directly_out_of_step_with_its_sheet(): void
    {
        $sheet = ForemanSheet::factory()->create(['created_by' => $this->manager->id]);
        $advance = SalaryAdvance::factory()->create(['created_by' => $this->manager->id, 'foreman_sheet_id' => $sheet->id]);

        $this->actingAs($this->owner)->patch(route('approvals.update', ['advance', $advance->id]), ['decision' => 'approve']);

        $this->assertTrue($sheet->refresh()->isPending());
        $this->assertSame(ApprovalStatus::Approved, $advance->refresh()->status, 'Documenting current behaviour: the advance is approved while its sheet still waits');
    }

    public function test_queue_is_sorted_newest_first(): void
    {
        $this->travel(-2)->hours();
        $old = Expense::factory()->create(['created_by' => $this->manager->id, 'expense_head_id' => $this->head->id, 'reason' => 'Oldest reason']);
        $this->travelBack();
        $this->travel(-1)->hours();
        MachineRepair::factory()->create(['created_by' => $this->manager->id, 'description' => 'Middle repair']);
        $this->travelBack();
        Expense::factory()->create(['created_by' => $this->manager->id, 'expense_head_id' => $this->head->id, 'reason' => 'Newest reason']);

        $this->actingAs($this->owner)->get(route('approvals.index'))->assertOk()
            ->assertSeeInOrder(['Newest reason', 'Middle repair', 'Oldest reason']);
        $this->assertTrue($old->isPending());
    }
}
