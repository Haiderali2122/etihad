<?php

namespace Tests\Feature;

use App\Models\Deletion;
use App\Models\Designation;
use App\Models\Godown;
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

    public function test_a_delete_without_a_reason_is_refused_and_nothing_is_removed(): void
    {
        $owner = User::factory()->owner()->create();
        $godown = Godown::factory()->create();

        $this->actingAs($owner)->delete(route('godowns.destroy', $godown), ['delete_note' => '  '])->assertStatus(422);

        $this->assertModelExists($godown);
        $this->assertSame(0, Deletion::query()->count());
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
