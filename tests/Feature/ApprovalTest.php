<?php

namespace Tests\Feature;

use App\Enums\ApprovalStatus;
use App\Models\MachineRepair;
use App\Models\PaperReceipt;
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
}
