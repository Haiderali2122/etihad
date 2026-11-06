<?php

namespace Tests\Feature;

use App\Models\Deletion;
use App\Models\Designation;
use App\Models\Expense;
use App\Models\Godown;
use App\Models\PlateJob;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeletionLogTest extends TestCase
{
    use RefreshDatabase;

    private function userWith(array $permissions): User
    {
        return User::factory()->create(['role_id' => Role::factory()->create(['permissions' => $permissions])->id]);
    }

    public function test_deleting_logs_the_record_reason_and_who_did_it(): void
    {
        $owner = User::factory()->owner()->create(['name' => 'Sheikh Sahib']);
        $godown = Godown::factory()->create(['name' => 'Back store']);

        $this->actingAs($owner)->delete(route('godowns.destroy', $godown), ['delete_note' => 'Store was closed'])
            ->assertRedirect(route('godowns.index'));

        $this->assertModelMissing($godown);
        $log = Deletion::query()->sole();
        $this->assertSame('Godown', $log->kind);
        $this->assertSame('Back store', $log->label);
        $this->assertSame('Store was closed', $log->note);
        $this->assertSame($owner->id, $log->deleted_by);
        $this->assertSame('Sheikh Sahib', $log->deleted_by_name);
    }

    public function test_the_reason_is_optional_and_a_blank_one_is_logged_as_none(): void
    {
        $owner = User::factory()->owner()->create();
        $godown = Godown::factory()->create();

        $this->actingAs($owner)->delete(route('godowns.destroy', $godown), ['delete_note' => '  '])->assertRedirect(route('godowns.index'));

        $this->assertModelMissing($godown);
        $this->assertNull(Deletion::query()->sole()->note);
    }

    public function test_a_role_without_the_delete_permission_cannot_delete_and_sees_no_button(): void
    {
        $user = $this->userWith(['godowns.manage', 'paper.view']); // no records.delete
        $godown = Godown::factory()->create();

        $this->actingAs($user)->get(route('godowns.index'))->assertOk()
            ->assertDontSee('value="DELETE"', false);
        $this->actingAs($user)->delete(route('godowns.destroy', $godown), ['delete_note' => 'nope'])->assertForbidden();
        $this->assertModelExists($godown);

        // The same role with the permission sees the button and can delete.
        $user->role->update(['permissions' => ['godowns.manage', 'paper.view', 'records.delete']]);
        $this->actingAs($user)->get(route('godowns.index'))->assertSee('value="DELETE"', false);
        $this->actingAs($user)->delete(route('godowns.destroy', $godown), ['delete_note' => 'done'])->assertRedirect();
        $this->assertModelMissing($godown);
    }

    public function test_an_expense_receipt_can_be_deleted_with_a_reason(): void
    {
        $owner = User::factory()->owner()->create();
        $expense = Expense::factory()->create();
        $item = $expense->items()->sole();

        $this->actingAs($owner)->get(route('expenses.index'))->assertSee('value="DELETE"', false);
        $this->actingAs($owner)->get(route('expenses.show', $expense))->assertSee('value="DELETE"', false);
        $this->actingAs($owner)->delete(route('expenses.destroy', $expense), ['delete_note' => 'Entered twice'])
            ->assertRedirect(route('expenses.index'));

        $this->assertModelMissing($expense);
        $this->assertModelMissing($item);
        $log = Deletion::query()->sole();
        $this->assertSame('Expense', $log->kind);
        $this->assertStringStartsWith($expense->receiptNumber(), $log->label);
        $this->assertSame('Entered twice', $log->note);
    }

    public function test_a_plate_job_delete_also_removes_its_cash_out_payment_and_logs_once(): void
    {
        $owner = User::factory()->owner()->create();
        $job = PlateJob::factory()->create(['paid_on' => today(), 'amount' => 4000, 'created_by' => $owner->id]);
        $mirror = $job->cashOutExpense()->sole();

        // The mirrored row has a delete button in Cash-out too; it would delete the plate job itself.
        $this->actingAs($owner)->get(route('expenses.index'))->assertSee('value="DELETE"', false);
        $this->actingAs($owner)->get(route('expenses.show', $mirror))->assertSee('value="DELETE"', false);

        $this->actingAs($owner)->get(route('plate-jobs.index', ['show' => 'all']))->assertSee(route('plate-jobs.destroy', $job), false);
        $this->actingAs($owner)->delete(route('plate-jobs.destroy', $job), ['delete_note' => 'Wrong processor'])->assertRedirect();

        $this->assertModelMissing($job);
        $this->assertModelMissing($mirror);
        $log = Deletion::query()->sole();
        $this->assertSame('Plate job', $log->kind);
        $this->assertSame('Wrong processor', $log->note);
    }

    public function test_only_the_owner_sees_the_deletion_log(): void
    {
        $owner = User::factory()->owner()->create();
        $manager = User::factory()->create();
        Designation::factory()->create(['name' => 'Cutter']);
        $this->actingAs($owner)->delete(route('designations.destroy', Designation::query()->sole()), ['delete_note' => 'Not used']);

        $this->actingAs($owner)->get(route('deletions.index'))->assertOk()
            ->assertSee('Cutter')->assertSee('Not used')->assertSee($owner->name);
        $this->actingAs($manager)->get(route('deletions.index'))->assertForbidden();
    }
}
