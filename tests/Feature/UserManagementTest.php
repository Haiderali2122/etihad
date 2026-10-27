<?php

namespace Tests\Feature;

use App\Enums\ApprovalStatus;
use App\Models\CashFloat;
use App\Models\MachineRepair;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_sees_user_and_role_screens_and_manager_does_not(): void
    {
        $owner = User::factory()->owner()->create();
        $manager = User::factory()->create();

        foreach (['users.index', 'users.create', 'roles.index', 'roles.create'] as $route) {
            $this->actingAs($owner)->get(route($route))->assertOk();
            $this->actingAs($manager)->get(route($route))->assertForbidden();
        }
        $this->actingAs($owner)->get(route('users.edit', $manager))->assertOk();
        $this->actingAs($owner)->get(route('roles.edit', $manager->role))->assertOk();
    }

    public function test_owner_adds_a_user_who_can_then_sign_in(): void
    {
        $owner = User::factory()->owner()->create();
        $role = Role::factory()->create(['permissions' => ['paper.view']]);

        $this->actingAs($owner)->post(route('users.store'), [
            'name' => 'Store Keeper', 'email' => 'store@etihad.test', 'role_id' => $role->id, 'locale' => 'ur',
            'password' => 'secret123', 'password_confirmation' => 'secret123', 'is_active' => 1,
        ])->assertRedirect(route('users.index'));

        auth()->logout();
        $this->post(route('login'), ['email' => 'store@etihad.test', 'password' => 'secret123'])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs(User::query()->where('email', 'store@etihad.test')->sole());
    }

    public function test_update_keeps_password_when_left_blank(): void
    {
        $owner = User::factory()->owner()->create();
        $user = User::factory()->create();
        $hash = $user->password;

        $this->actingAs($owner)->put(route('users.update', $user), [
            'name' => 'Renamed', 'email' => $user->email, 'role_id' => $user->role_id, 'locale' => 'en', 'password' => '', 'is_active' => 1,
        ])->assertSessionHasNoErrors();

        $this->assertSame('Renamed', $user->refresh()->name);
        $this->assertSame($hash, $user->password);
    }

    public function test_inactive_users_cannot_sign_in(): void
    {
        User::factory()->create(['email' => 'gone@etihad.test', 'is_active' => false]);

        $this->post(route('login'), ['email' => 'gone@etihad.test', 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_the_last_owner_cannot_be_demoted_deactivated_or_deleted(): void
    {
        $owner = User::factory()->owner()->create();
        $managerRole = User::factory()->create()->role;

        $this->actingAs($owner)->put(route('users.update', $owner), [
            'name' => $owner->name, 'email' => $owner->email, 'role_id' => $managerRole->id, 'locale' => 'en', 'is_active' => 1,
        ])->assertSessionHasErrors('role_id');

        $this->actingAs($owner)->put(route('users.update', $owner), [
            'name' => $owner->name, 'email' => $owner->email, 'role_id' => $owner->role_id, 'locale' => 'en', 'is_active' => 0,
        ])->assertSessionHasErrors('is_active');

        $this->actingAs($owner)->delete(route('users.destroy', $owner))->assertSessionHasErrors('user');
        $this->assertTrue($owner->refresh()->isOwner());
    }

    public function test_a_user_with_cash_history_is_deactivated_not_deleted(): void
    {
        $owner = User::factory()->owner()->create();
        $faisal = User::factory()->create();
        CashFloat::factory()->create(['user_id' => $faisal->id]);
        $newcomer = User::factory()->create();

        $this->actingAs($owner)->delete(route('users.destroy', $faisal))->assertSessionHasErrors('user');
        $this->assertModelExists($faisal);

        $this->actingAs($owner)->delete(route('users.destroy', $newcomer))->assertRedirect(route('users.index'));
        $this->assertModelMissing($newcomer);
    }

    public function test_role_permissions_control_access(): void
    {
        $owner = User::factory()->owner()->create();
        $role = Role::factory()->create(['permissions' => []]);
        $clerk = User::factory()->create(['role_id' => $role->id]);

        $this->actingAs($clerk)->get(route('paper.index'))->assertForbidden();
        $this->actingAs($clerk)->get(route('dashboard'))->assertOk()->assertDontSee(route('paper.index'));

        $this->actingAs($owner)->put(route('roles.update', $role), [
            'name' => $role->name, 'permissions' => ['paper.view', 'books.view'],
        ])->assertRedirect(route('roles.index'));

        $this->actingAs($clerk->refresh())->get(route('paper.index'))->assertOk();
        $this->actingAs($clerk)->get(route('books.index'))->assertOk();
        $this->actingAs($clerk)->get(route('books.create'))->assertForbidden();
    }

    public function test_unknown_permissions_are_rejected(): void
    {
        $this->actingAs(User::factory()->owner()->create())
            ->post(route('roles.store'), ['name' => 'Hacker', 'permissions' => ['everything']])
            ->assertSessionHasErrors('permissions.0');
    }

    public function test_full_access_role_cannot_be_deleted_or_limited(): void
    {
        $owner = User::factory()->owner()->create();

        $this->actingAs($owner)->put(route('roles.update', $owner->role), ['name' => 'Malik', 'permissions' => ['paper.view']])
            ->assertSessionHasNoErrors();
        $this->assertSame('Malik', $owner->role->refresh()->name);
        $this->assertTrue($owner->refresh()->can('users.manage'));

        $this->actingAs($owner)->delete(route('roles.destroy', $owner->role))->assertSessionHasErrors('role');
        $this->assertModelExists($owner->role);
    }

    public function test_a_role_in_use_cannot_be_deleted(): void
    {
        $owner = User::factory()->owner()->create();
        $used = User::factory()->create()->role;
        $unused = Role::factory()->create();

        $this->actingAs($owner)->delete(route('roles.destroy', $used))->assertSessionHasErrors('role');
        $this->actingAs($owner)->delete(route('roles.destroy', $unused))->assertRedirect(route('roles.index'));
        $this->assertModelMissing($unused);
    }

    public function test_cannot_remove_user_management_from_your_own_role(): void
    {
        $role = Role::factory()->create(['permissions' => ['users.manage']]);
        $admin = User::factory()->create(['role_id' => $role->id]);

        $this->actingAs($admin)->put(route('roles.update', $role), ['name' => $role->name, 'permissions' => ['paper.view']])
            ->assertSessionHasErrors('permissions');
    }

    public function test_entries_by_anyone_with_approve_permission_are_auto_approved(): void
    {
        $approver = User::factory()->create(['role_id' => Role::factory()->create(['permissions' => ['approve', 'machines.repair']])->id]);

        $repair = MachineRepair::factory()->create(['created_by' => $approver->id]);

        $this->assertSame(ApprovalStatus::Approved, $repair->status);
    }
}
