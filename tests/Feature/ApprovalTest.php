<?php

namespace Tests\Feature;

use App\Enums\ApprovalStatus;
use App\Enums\PartyKind;
use App\Models\Godown;
use App\Models\Loan;
use App\Models\Machine;
use App\Models\MachineRepair;
use App\Models\PaperReceipt;
use App\Models\Party;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApprovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_entries_are_approved_on_save(): void
    {
        $owner = User::factory()->owner()->create();

        $repair = MachineRepair::factory()->create(['created_by' => $owner->id]);

        $this->assertSame(ApprovalStatus::Approved, $repair->status);
        $this->assertSame($owner->id, $repair->reviewed_by);
    }

    public function test_owner_approves_a_manager_entry(): void
    {
        $owner = User::factory()->owner()->create();
        $manager = User::factory()->create();
        $repair = MachineRepair::factory()->create(['created_by' => $manager->id]);

        $this->actingAs($owner)->get(route('approvals.index'))->assertOk()->assertSee($repair->description);

        $this->actingAs($owner)
            ->patch(route('approvals.update', ['repair', $repair->id]), ['decision' => 'approve'])
            ->assertRedirect();

        $repair->refresh();
        $this->assertSame(ApprovalStatus::Approved, $repair->status);
        $this->assertSame($owner->id, $repair->reviewed_by);
    }

    public function test_sending_back_needs_a_note(): void
    {
        $owner = User::factory()->owner()->create();
        $receipt = PaperReceipt::factory()->create(['created_by' => User::factory()->create()->id]);

        $this->actingAs($owner)
            ->patch(route('approvals.update', ['paper-receipt', $receipt->id]), ['decision' => 'reject'])
            ->assertSessionHasErrors('note');

        $this->actingAs($owner)
            ->patch(route('approvals.update', ['paper-receipt', $receipt->id]), ['decision' => 'reject', 'note' => 'Wrong slip number'])
            ->assertSessionHasNoErrors();

        $this->assertSame(ApprovalStatus::Rejected, $receipt->refresh()->status);
        $this->assertSame('Wrong slip number', $receipt->review_note);
    }

    public function test_manager_cannot_approve(): void
    {
        $manager = User::factory()->create();
        $repair = MachineRepair::factory()->create(['created_by' => User::factory()->create()->id]);

        $this->actingAs($manager)->get(route('approvals.index'))->assertForbidden();
        $this->actingAs($manager)
            ->patch(route('approvals.update', ['repair', $repair->id]), ['decision' => 'approve'])
            ->assertForbidden();

        $this->assertSame(ApprovalStatus::Pending, $repair->refresh()->status);
    }

    public function test_a_decided_entry_cannot_be_decided_again(): void
    {
        $owner = User::factory()->owner()->create();
        $repair = MachineRepair::factory()->create(['created_by' => User::factory()->create()->id]);
        $repair->approve($owner);

        $this->actingAs($owner)
            ->patch(route('approvals.update', ['repair', $repair->id]), ['decision' => 'reject', 'note' => 'Too late'])
            ->assertStatus(409);
    }

    public function test_a_sent_back_receipt_is_corrected_and_resent_keeping_its_slip_number(): void
    {
        $owner = User::factory()->owner()->create();
        $manager = User::factory()->create();
        $godown = Godown::factory()->create();
        $receipt = PaperReceipt::factory()->create(['slip_no' => 'S-77', 'quantity' => 500, 'godown_id' => $godown->id, 'created_by' => $manager->id]);
        $this->actingAs($owner)->patch(route('approvals.update', ['paper-receipt', $receipt->id]), ['decision' => 'reject', 'note' => 'Quantity is 450'])->assertSessionHasNoErrors();

        $this->actingAs($manager)->get(route('dashboard'))->assertOk()->assertSee('Sent back to you')->assertSee('Quantity is 450');
        // Only the person who entered it can resend it.
        $this->actingAs(User::factory()->create())->get(route('approvals.resend', ['paper-receipt', $receipt->id]))->assertForbidden();

        $this->actingAs($manager)->get(route('approvals.resend', ['paper-receipt', $receipt->id]))
            ->assertRedirect(route('paper-receipts.create'))->assertSessionHasInput('slip_no', 'S-77')->assertSessionHasInput('quantity', 500);
        $this->actingAs($manager)->get(route('paper-receipts.create'))->assertOk()->assertSee('value="S-77"', false);

        $this->actingAs($manager)->post(route('paper-receipts.store'), [
            'slip_no' => 'S-77', 'received_at' => now()->format('Y-m-d H:i'), 'party_id' => $receipt->party_id, 'paper_type_id' => $receipt->paper_type_id,
            'quantity' => 450, 'godown_id' => $godown->id, 'received_by' => 'Faisal',
        ])->assertSessionHasNoErrors();

        $corrected = PaperReceipt::query()->where('slip_no', 'S-77')->sole();
        $this->assertSame(450, $corrected->quantity);
        $this->assertSame(ApprovalStatus::Pending, $corrected->status);
        $receipt->refresh();
        $this->assertNotNull($receipt->resent_at);
        $this->assertSame(ApprovalStatus::Rejected, $receipt->status);
        $this->actingAs($manager)->get(route('dashboard'))->assertDontSee('Sent back to you');
        $this->actingAs($manager)->get(route('approvals.resend', ['paper-receipt', $receipt->id]))->assertNotFound();
    }

    public function test_a_sent_back_loan_opens_with_its_month_and_amounts(): void
    {
        $owner = User::factory()->owner()->create();
        $manager = User::factory()->create();
        $loan = Loan::factory()->create(['issued_on' => '2026-10-05', 'amount' => 20000, 'monthly_instalment' => 2500, 'first_instalment_month' => '2026-11-01', 'created_by' => $manager->id]);
        $loan->reject($owner, 'Make it 2,000 a month');

        $this->actingAs($manager)->get(route('approvals.resend', ['loan', $loan->id]))
            ->assertRedirect(route('loans.create', ['employee' => $loan->employee_id]))
            ->assertSessionHasInput('first_instalment_month', '2026-11')->assertSessionHasInput('monthly_instalment', 2500);
    }

    public function test_repair_paid_cannot_exceed_the_bill_less_discount_and_form_goes_back_to_the_machine(): void
    {
        $owner = User::factory()->owner()->create();
        $machine = Machine::factory()->create();
        $payload = [
            'repaired_on' => today()->toDateString(), 'machine_id' => $machine->id, 'party_id' => Party::factory()->kind(PartyKind::Mechanic)->create()->id,
            'kind' => 'repair', 'description' => 'Chain', 'bill_amount' => 10000, 'discount' => 2000,
        ];

        $this->actingAs($owner)->post(route('machine-repairs.store'), $payload + ['amount_paid' => 8500])->assertSessionHasErrors('amount_paid');
        $this->actingAs($owner)->post(route('machine-repairs.store'), $payload + ['amount_paid' => 8000])->assertSessionHasNoErrors();
        $this->actingAs($owner)->get(route('machine-repairs.create', ['machine' => $machine->id]))->assertSee(route('machines.show', $machine), false);
    }
}
