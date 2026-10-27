<?php

namespace Tests\Feature\E2E;

use App\Enums\PartyKind;
use App\Models\Book;
use App\Models\CashFloat;
use App\Models\Contractor;
use App\Models\ContractorJob;
use App\Models\Designation;
use App\Models\Dispatch;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\ExpenseHead;
use App\Models\ForemanSheet;
use App\Models\Godown;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\Loan;
use App\Models\Machine;
use App\Models\PaperReceipt;
use App\Models\PaperType;
use App\Models\Party;
use App\Models\PayrollRun;
use App\Models\PlateJob;
use App\Models\Role;
use App\Models\SalaryAdvance;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route as RouteFacade;
use Tests\TestCase;
use Throwable;

class AuthAccessE2ETest extends TestCase
{
    use RefreshDatabase;

    /**
     * Routes that are intentionally open to any signed-in user (or to guests).
     *
     * @var list<string>
     */
    private const OpenRoutes = ['login', 'locale', 'logout', 'dashboard', 'purchases.index'];

    /** @var list<string> */
    private array $skipped = [];

    /** @var array<string, mixed> */
    private array $paramCache = [];

    private function userWithPermissions(array $permissions, array $attributes = []): User
    {
        $role = Role::factory()->create(['permissions' => $permissions]);

        return User::factory()->create(['role_id' => $role->id] + $attributes);
    }

    // ---------------------------------------------------------------------
    // Authentication
    // ---------------------------------------------------------------------

    public function test_login_page_renders_for_guests_and_signed_in_users_are_bounced(): void
    {
        $this->get(route('login'))->assertOk()->assertSee('name="email"', false)->assertSee('dir="ltr"', false);

        $this->actingAs(User::factory()->create())->get(route('login'))->assertRedirect(route('dashboard'));
    }

    public function test_successful_login_regenerates_session_and_loads_user_locale(): void
    {
        $user = User::factory()->create(['email' => 'ali@etihad.test', 'locale' => 'ur']);

        $this->get(route('login'));
        $oldSession = session()->getId();

        $this->post(route('login'), ['email' => 'ali@etihad.test', 'password' => 'password'])
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('locale', 'ur');

        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($oldSession, session()->getId());
        $this->get(route('dashboard'))->assertOk()->assertSee('dir="rtl"', false)->assertSee('lang="ur"', false);
    }

    public function test_failed_login_shows_the_same_error_for_wrong_password_and_unknown_email(): void
    {
        User::factory()->create(['email' => 'ali@etihad.test']);

        $wrong = $this->from(route('login'))->post(route('login'), ['email' => 'ali@etihad.test', 'password' => 'nope']);
        $wrong->assertRedirect(route('login'))->assertSessionHasErrors('email');
        $wrongMessage = session('errors')->first('email');

        $unknown = $this->from(route('login'))->post(route('login'), ['email' => 'nobody@etihad.test', 'password' => 'password']);
        $unknown->assertSessionHasErrors('email');

        $this->assertSame($wrongMessage, session('errors')->first('email'));
        $this->assertGuest();
    }

    public function test_login_validates_input(): void
    {
        $this->post(route('login'), [])->assertSessionHasErrors(['email', 'password']);
        $this->post(route('login'), ['email' => 'not-an-email', 'password' => 'x'])->assertSessionHasErrors('email');
        $this->post(route('login'), ['email' => ['a@b.c'], 'password' => ['x']])->assertSessionHasErrors(['email', 'password']);
        $this->assertGuest();
    }

    public function test_inactive_user_cannot_sign_in_even_with_right_password(): void
    {
        User::factory()->owner()->create(['email' => 'old@etihad.test', 'is_active' => false]);

        $this->post(route('login'), ['email' => 'old@etihad.test', 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_login_is_throttled_after_ten_attempts_per_minute(): void
    {
        User::factory()->create(['email' => 'ali@etihad.test']);

        for ($i = 0; $i < 10; $i++) {
            $this->post(route('login'), ['email' => 'ali@etihad.test', 'password' => 'wrong'])->assertSessionHasErrors('email');
        }

        $this->post(route('login'), ['email' => 'ali@etihad.test', 'password' => 'password'])->assertStatus(429);
        $this->assertGuest();
    }

    public function test_guest_is_sent_to_login_and_back_to_the_intended_page(): void
    {
        $owner = User::factory()->owner()->create(['email' => 'boss@etihad.test']);

        $this->get(route('users.index'))->assertRedirect(route('login'));
        $this->post(route('login'), ['email' => 'boss@etihad.test', 'password' => 'password'])->assertRedirect(route('users.index'));
        $this->assertAuthenticatedAs($owner);
    }

    public function test_logout_ends_the_session(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('logout'))->assertStatus(405);
        $this->actingAs($user)->post(route('logout'))->assertRedirect(route('login'));
        $this->assertGuest();
        $this->get(route('dashboard'))->assertRedirect(route('login'));

        // A guest posting to logout is just sent to login.
        $this->post(route('logout'))->assertRedirect(route('login'));
    }

    public function test_user_deactivated_mid_session_loses_every_permission(): void
    {
        $owner = User::factory()->owner()->create();
        $this->actingAs($owner)->get(route('paper.index'))->assertOk();

        $owner->update(['is_active' => false]);

        $this->actingAs($owner->fresh())->get(route('paper.index'))->assertForbidden();
        $this->actingAs($owner->fresh())->get(route('users.index'))->assertForbidden();
    }

    // ---------------------------------------------------------------------
    // Gates & roles
    // ---------------------------------------------------------------------

    public function test_every_config_permission_is_a_gate_and_super_role_passes_all(): void
    {
        $owner = User::factory()->owner()->create();
        $nobody = $this->userWithPermissions([]);
        $inactiveOwner = User::factory()->owner()->create(['is_active' => false]);

        $permissions = Role::allPermissions();
        $this->assertNotEmpty($permissions);
        $this->assertSame(count($permissions), count(array_unique($permissions)), 'Duplicate permission keys in config/permissions.php');

        foreach ($permissions as $permission) {
            $this->assertTrue(Gate::has($permission), "Gate {$permission} not registered");
            $this->assertTrue($owner->can($permission), "Owner denied {$permission}");
            $this->assertFalse($nobody->can($permission), "No-permission user allowed {$permission}");
            $this->assertFalse($inactiveOwner->can($permission), "Inactive owner allowed {$permission}");
        }

        $this->assertFalse(Gate::forUser(null)->allows('paper.view'));
    }

    public function test_a_role_grants_exactly_its_own_permissions(): void
    {
        $all = Role::allPermissions();

        foreach ($all as $permission) {
            $user = $this->userWithPermissions([$permission]);

            foreach ($all as $other) {
                $this->assertSame($other === $permission, $user->can($other), "Role [{$permission}] check {$other}");
            }
        }
    }

    public function test_every_route_permission_exists_in_config(): void
    {
        $all = Role::allPermissions();

        foreach (RouteFacade::getRoutes() as $route) {
            foreach ($route->gatherMiddleware() as $middleware) {
                if (is_string($middleware) && str_starts_with($middleware, 'can:')) {
                    $this->assertContains(substr($middleware, 4), $all, "Route {$route->getName()} uses unknown permission {$middleware}");
                }
            }
        }
    }

    public function test_role_crud_round_trip(): void
    {
        $owner = User::factory()->owner()->create();

        $this->actingAs($owner)->get(route('roles.index'))->assertOk();
        $this->actingAs($owner)->get(route('roles.create'))->assertOk();

        $this->actingAs($owner)->post(route('roles.store'), [
            'name' => 'Store keeper', 'description' => 'Godown', 'permissions' => ['paper.view', 'paper.receive'],
            'is_super' => 1,
        ])->assertRedirect(route('roles.index'));

        $role = Role::query()->where('name', 'Store keeper')->sole();
        $this->assertFalse($role->is_super, 'is_super must not be mass-assignable from the form');
        $this->assertSame(['paper.view', 'paper.receive'], $role->permissions);

        $this->actingAs($owner)->get(route('roles.edit', $role))->assertOk();

        $this->actingAs($owner)->put(route('roles.update', $role), ['name' => 'Store keeper', 'is_super' => 1])
            ->assertRedirect(route('roles.index'));
        $this->assertSame([], $role->refresh()->permissions);
        $this->assertFalse($role->is_super);

        $this->actingAs($owner)->post(route('roles.store'), ['name' => 'Store keeper'])->assertSessionHasErrors('name');
        $this->actingAs($owner)->post(route('roles.store'), ['name' => ''])->assertSessionHasErrors('name');
        $this->actingAs($owner)->post(route('roles.store'), ['name' => str_repeat('x', 61)])->assertSessionHasErrors('name');

        $this->actingAs($owner)->delete(route('roles.destroy', $role))->assertRedirect(route('roles.index'));
        $this->assertModelMissing($role);
    }

    public function test_role_store_with_non_array_permissions_is_a_validation_error_not_a_crash(): void
    {
        $owner = User::factory()->owner()->create();

        $this->actingAs($owner)->post(route('roles.store'), ['name' => 'Odd', 'permissions' => 'paper.view'])
            ->assertSessionHasErrors('permissions');
    }

    /**
     * Non-array permissions on your own role are a validation error, not a crash.
     */
    public function test_updating_own_role_with_non_array_permissions_is_a_validation_error_not_a_crash(): void
    {
        $role = Role::factory()->create(['permissions' => ['users.manage']]);
        $admin = User::factory()->create(['role_id' => $role->id]);

        $this->actingAs($admin)->put(route('roles.update', $role), ['name' => $role->name, 'permissions' => 'users.manage'])
            ->assertSessionHasErrors('permissions');
    }

    public function test_admin_may_keep_user_management_on_own_role_while_changing_others(): void
    {
        $role = Role::factory()->create(['permissions' => ['users.manage']]);
        $admin = User::factory()->create(['role_id' => $role->id]);

        $this->actingAs($admin)->put(route('roles.update', $role), ['name' => $role->name])->assertSessionHasErrors('permissions');

        $this->actingAs($admin)->put(route('roles.update', $role), ['name' => $role->name, 'permissions' => ['users.manage', 'paper.view']])
            ->assertSessionHasNoErrors();
        $this->assertTrue($admin->fresh()->can('paper.view'));
    }

    public function test_role_edit_and_destroy_of_missing_role_is_404(): void
    {
        $owner = User::factory()->owner()->create();

        $this->actingAs($owner)->get(route('roles.edit', 99999))->assertNotFound();
        $this->actingAs($owner)->delete(route('roles.destroy', 99999))->assertNotFound();
    }

    // ---------------------------------------------------------------------
    // Users
    // ---------------------------------------------------------------------

    public function test_user_crud_and_validation(): void
    {
        $owner = User::factory()->owner()->create();
        $role = Role::factory()->create(['permissions' => ['paper.view']]);

        $this->actingAs($owner)->get(route('users.index', ['role' => $role->id]))->assertOk();
        $this->actingAs($owner)->get(route('users.index', ['role' => 'abc']))->assertOk();

        $base = ['name' => 'Clerk', 'email' => 'clerk@etihad.test', 'role_id' => $role->id, 'locale' => 'en', 'is_active' => 1];

        $this->actingAs($owner)->post(route('users.store'), $base)->assertSessionHasErrors('password');
        $this->actingAs($owner)->post(route('users.store'), $base + ['password' => 'short', 'password_confirmation' => 'short'])->assertSessionHasErrors('password');
        $this->actingAs($owner)->post(route('users.store'), $base + ['password' => 'secret123', 'password_confirmation' => 'different'])->assertSessionHasErrors('password');
        $this->actingAs($owner)->post(route('users.store'), ['role_id' => 99999, 'locale' => 'fr'] + $base + ['password' => 'secret123', 'password_confirmation' => 'secret123'])
            ->assertSessionHasErrors(['role_id', 'locale']);
        $this->actingAs($owner)->post(route('users.store'), ['email' => $owner->email] + $base + ['password' => 'secret123', 'password_confirmation' => 'secret123'])
            ->assertSessionHasErrors('email');

        $this->actingAs($owner)->post(route('users.store'), $base + ['password' => 'secret123', 'password_confirmation' => 'secret123'])
            ->assertRedirect(route('users.index'));
        $clerk = User::query()->where('email', 'clerk@etihad.test')->sole();
        $this->assertTrue($clerk->is_active);

        // Unchecked box: hidden input sends 0 -> inactive.
        $this->actingAs($owner)->put(route('users.update', $clerk), ['is_active' => 0] + $base)->assertRedirect(route('users.index'));
        $this->assertFalse($clerk->refresh()->is_active);

        // Keeping your own email is fine.
        $this->actingAs($owner)->put(route('users.update', $clerk), $base)->assertSessionHasNoErrors();

        $this->actingAs($owner)->delete(route('users.destroy', $clerk))->assertRedirect(route('users.index'));
        $this->assertModelMissing($clerk);
    }

    public function test_cannot_delete_self_even_with_another_owner_present(): void
    {
        $owner = User::factory()->owner()->create();
        User::factory()->owner()->create();

        $this->actingAs($owner)->delete(route('users.destroy', $owner))->assertSessionHasErrors('user');
        $this->assertModelExists($owner);
    }

    public function test_one_owner_can_demote_and_delete_another_but_not_the_last(): void
    {
        $a = User::factory()->owner()->create();
        $b = User::factory()->owner()->create();
        $managerRole = Role::factory()->create(['permissions' => Role::ManagerPermissions]);

        $this->actingAs($a)->put(route('users.update', $b), [
            'name' => $b->name, 'email' => $b->email, 'role_id' => $managerRole->id, 'locale' => 'en', 'is_active' => 1,
        ])->assertSessionHasNoErrors();
        $this->assertFalse($b->refresh()->isOwner());

        // Now $a is the last owner and cannot demote themselves.
        $this->actingAs($a)->put(route('users.update', $a), [
            'name' => $a->name, 'email' => $a->email, 'role_id' => $managerRole->id, 'locale' => 'en', 'is_active' => 1,
        ])->assertSessionHasErrors('role_id');
    }

    public function test_inactive_owners_do_not_count_toward_keeping_an_owner(): void
    {
        $active = User::factory()->owner()->create();
        User::factory()->owner()->create(['is_active' => false]);
        $admin = $this->userWithPermissions(['users.manage']);

        // A non-owner admin cannot demote, deactivate or delete the only *active* owner.
        $this->actingAs($admin)->put(route('users.update', $active), [
            'name' => $active->name, 'email' => $active->email, 'role_id' => $admin->role_id, 'locale' => 'en', 'is_active' => 1,
        ])->assertSessionHasErrors('role_id');
        $this->actingAs($admin)->put(route('users.update', $active), [
            'name' => $active->name, 'email' => $active->email, 'role_id' => $active->role_id, 'locale' => 'en', 'is_active' => 0,
        ])->assertSessionHasErrors('role_id');
        $this->actingAs($admin)->delete(route('users.destroy', $active))->assertSessionHasErrors('user');

        $this->assertTrue($active->refresh()->isOwner());
        $this->assertTrue($active->is_active);
    }

    public function test_user_with_cash_history_or_expenses_cannot_be_deleted(): void
    {
        $owner = User::factory()->owner()->create();
        $withFloat = User::factory()->create();
        CashFloat::factory()->create(['user_id' => $withFloat->id]);

        $this->actingAs($owner)->delete(route('users.destroy', $withFloat))->assertSessionHasErrors('user');
        $this->assertModelExists($withFloat);
    }

    /**
     * Documents current behaviour: users.manage is effectively owner-level, because the holder can give
     * themselves the full-access role. Reported as a design concern, not asserted as a failure.
     */
    public function test_users_manage_holder_can_promote_self_to_owner(): void
    {
        User::factory()->owner()->create();
        $admin = $this->userWithPermissions(['users.manage']);
        $ownerRole = Role::query()->where('is_super', true)->sole();

        $this->actingAs($admin)->put(route('users.update', $admin), [
            'name' => $admin->name, 'email' => $admin->email, 'role_id' => $ownerRole->id, 'locale' => 'en', 'is_active' => 1,
        ])->assertSessionHasNoErrors();

        $this->assertTrue($admin->refresh()->isOwner());
    }

    // ---------------------------------------------------------------------
    // Parties & suppliers
    // ---------------------------------------------------------------------

    public function test_party_crud_filtering_and_validation(): void
    {
        $user = $this->userWithPermissions(['parties.manage']);
        $supplier = Party::factory()->kind(PartyKind::Supplier)->create(['name' => 'Ink House']);
        Party::factory()->kind(PartyKind::Mechanic)->create(['name' => 'Gear Wala']);

        $this->actingAs($user)->get(route('parties.index'))->assertOk()->assertSee('Ink House')->assertSee('Gear Wala');
        $this->actingAs($user)->get(route('parties.index', ['kind' => 'supplier']))->assertOk()->assertSee('Ink House')->assertDontSee('Gear Wala');
        $this->actingAs($user)->get(route('parties.index', ['kind' => 'bogus']))->assertOk()->assertSee('Gear Wala');
        $this->actingAs($user)->get(route('parties.create'))->assertOk();

        $this->actingAs($user)->post(route('parties.store'), ['name' => '', 'kind' => 'nope'])->assertSessionHasErrors(['name', 'kind']);
        $this->actingAs($user)->post(route('parties.store'), ['name' => str_repeat('a', 121), 'kind' => 'supplier'])->assertSessionHasErrors('name');

        $this->actingAs($user)->post(route('parties.store'), [
            'name' => 'Packages Ltd', 'kind' => 'paper', 'phone' => '0300', 'address' => 'Lahore', 'is_active' => 1,
        ])->assertRedirect(route('parties.index'));
        $party = Party::query()->where('name', 'Packages Ltd')->sole();
        $this->assertSame(PartyKind::PaperParty, $party->kind);
        $this->assertTrue($party->is_active);

        $this->actingAs($user)->get(route('parties.edit', $party))->assertOk()->assertSee('Packages Ltd');
        $this->actingAs($user)->put(route('parties.update', $party), ['name' => 'Packages', 'kind' => 'paper'])->assertRedirect(route('parties.index'));
        $this->assertSame('Packages', $party->refresh()->name);
        $this->assertFalse($party->is_active, 'Missing is_active means unchecked -> inactive');

        $this->actingAs($user)->get(route('parties.edit', 99999))->assertNotFound();
        $this->assertFalse(RouteFacade::has('parties.destroy'));
        $this->assertFalse(RouteFacade::has('parties.show'));
        $this->assertSame(1, Party::query()->whereKey($supplier->id)->count());
    }

    /**
     * An array kind filter on the parties list is ignored.
     */
    public function test_party_index_with_array_kind_filter_does_not_crash(): void
    {
        $this->actingAs($this->userWithPermissions(['parties.manage']))
            ->get(route('parties.index', ['kind' => ['x']]))->assertOk();
    }

    public function test_parties_need_parties_manage(): void
    {
        $user = $this->userWithPermissions(['purchases.view', 'paper.view']);
        $party = Party::factory()->create();

        $this->actingAs($user)->get(route('parties.index'))->assertForbidden();
        $this->actingAs($user)->post(route('parties.store'), ['name' => 'X', 'kind' => 'other'])->assertForbidden();
        $this->actingAs($user)->put(route('parties.update', $party), ['name' => 'X', 'kind' => 'other'])->assertForbidden();
        $this->assertSame(0, Party::query()->where('name', 'X')->count());
    }

    // ---------------------------------------------------------------------
    // Language
    // ---------------------------------------------------------------------

    public function test_guest_can_switch_the_login_page_to_urdu_and_back(): void
    {
        $this->from(route('login'))->get(route('locale', 'ur'))->assertRedirect(route('login'))->assertSessionHas('locale', 'ur');
        $this->get(route('login'))->assertOk()->assertSee('dir="rtl"', false)->assertSee('lang="ur"', false);

        $this->get(route('locale', 'en'));
        $this->get(route('login'))->assertOk()->assertSee('dir="ltr"', false);
    }

    public function test_unknown_locale_is_404_and_not_stored(): void
    {
        $this->get(route('locale', 'fr'))->assertNotFound()->assertSessionMissing('locale');
        $this->get('/locale/'.urlencode('../en'))->assertNotFound();
    }

    public function test_signed_in_user_switch_is_remembered_on_their_account(): void
    {
        $user = User::factory()->create(['locale' => 'en']);

        $this->actingAs($user)->from(route('dashboard'))->get(route('locale', 'ur'))->assertRedirect(route('dashboard'));
        $this->assertSame('ur', $user->refresh()->locale);
        $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertSee('dir="rtl"', false);
    }

    public function test_stored_bad_locale_falls_back_to_english(): void
    {
        $user = User::factory()->create(['locale' => 'xx']);

        $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertSee('dir="ltr"', false)->assertSee('lang="en"', false);
    }

    // ---------------------------------------------------------------------
    // Access-control matrix
    // ---------------------------------------------------------------------

    /**
     * @return array<string, mixed>|null
     */
    private function paramsFor(Route $route): ?array
    {
        $params = [];

        foreach ($route->parameterNames() as $name) {
            if (array_key_exists($name, $this->paramCache)) {
                $params[$name] = $this->paramCache[$name];

                continue;
            }

            try {
                $params[$name] = $this->paramCache[$name] = match ($name) {
                    'book' => Book::factory()->create(),
                    'buyer' => Party::factory()->kind(PartyKind::Buyer)->create(),
                    'contractor' => Contractor::factory()->create(),
                    'contractorJob' => ContractorJob::factory()->create(),
                    'designation' => Designation::factory()->create(),
                    'dispatch' => Dispatch::factory()->create(),
                    'employee' => Employee::factory()->create(),
                    'expense_head' => ExpenseHead::factory()->create(),
                    'foreman_sheet' => ForemanSheet::factory()->create(),
                    'godown' => Godown::factory()->create(),
                    'inventory_category' => InventoryCategory::factory()->create(),
                    'inventory_item' => InventoryItem::factory()->create(),
                    'loan' => Loan::factory()->create(),
                    'machine' => Machine::factory()->create(),
                    'paper_receipt' => PaperReceipt::factory()->create(),
                    'paper_type' => PaperType::factory()->create(),
                    'party' => Party::factory()->create(),
                    'payrollRun' => PayrollRun::factory()->create(),
                    'plateJob' => PlateJob::factory()->create(),
                    'role' => Role::factory()->create(),
                    'salary_advance' => SalaryAdvance::factory()->create(),
                    'user' => User::factory()->create(),
                    'workOrder', 'work_order' => WorkOrder::factory()->create(),
                    'type' => 'expense',
                    'id' => Expense::factory()->create()->id,
                    'locale' => 'ur',
                    default => throw new \RuntimeException("no factory for {$name}"),
                };
            } catch (Throwable $e) {
                $this->skipped[] = "{$route->getName()} ({$name}): ".$e->getMessage();

                return null;
            }
        }

        return $params;
    }

    /**
     * @return list<array{0: Route, 1: string, 2: string}> route, method, url
     */
    private function protectedRoutes(): array
    {
        $out = [];

        foreach (RouteFacade::getRoutes() as $route) {
            $name = $route->getName();
            $action = $route->getActionName();

            if (! $name || in_array($name, self::OpenRoutes, true) || ! str_starts_with($action, 'App\\')) {
                continue;
            }

            $params = $this->paramsFor($route);
            if ($params === null) {
                continue;
            }

            $method = collect($route->methods())->first(fn (string $m) => $m !== 'HEAD');
            $out[] = [$route, $method, route($name, $params)];
        }

        return $out;
    }

    public function test_user_without_permissions_gets_403_on_every_protected_route(): void
    {
        User::factory()->owner()->create();
        $nobody = $this->userWithPermissions([]);
        $failures = [];

        foreach ($this->protectedRoutes() as [$route, $method, $url]) {
            $status = $this->actingAs($nobody)->call($method, $url)->getStatusCode();

            if ($status !== 403) {
                $failures[] = "{$method} {$route->getName()} -> {$status}";
            }
        }

        $this->assertSame([], $this->skipped, 'Skipped routes: '.implode('; ', $this->skipped));
        $this->assertSame([], $failures, implode("\n", $failures));
    }

    public function test_inactive_owner_session_gets_403_on_every_protected_route(): void
    {
        $owner = User::factory()->owner()->create(['is_active' => false]);
        $failures = [];

        foreach ($this->protectedRoutes() as [$route, $method, $url]) {
            $status = $this->actingAs($owner)->call($method, $url)->getStatusCode();

            if ($status !== 403) {
                $failures[] = "{$method} {$route->getName()} -> {$status}";
            }
        }

        $this->assertSame([], $failures, implode("\n", $failures));
    }

    public function test_guest_is_redirected_to_login_on_every_authenticated_route(): void
    {
        $failures = [];

        foreach ($this->protectedRoutes() as [$route, $method, $url]) {
            $response = $this->call($method, $url);

            if (! $response->isRedirect(route('login'))) {
                $failures[] = "{$method} {$route->getName()} -> {$response->getStatusCode()}";
            }
        }

        foreach (['dashboard' => 'GET', 'purchases.index' => 'GET', 'logout' => 'POST'] as $name => $method) {
            if (! $this->call($method, route($name))->isRedirect(route('login'))) {
                $failures[] = "{$method} {$name}";
            }
        }

        $this->assertSame([], $failures, implode("\n", $failures));
    }

    public function test_owner_never_gets_a_server_error_on_any_get_route(): void
    {
        $owner = User::factory()->owner()->create();
        $failures = [];

        foreach ($this->protectedRoutes() as [$route, $method, $url]) {
            if ($method !== 'GET') {
                continue;
            }

            $status = $this->actingAs($owner)->get($url)->getStatusCode();

            if ($status >= 500 || $status === 403) {
                $failures[] = "GET {$route->getName()} -> {$status}";
            }
        }

        $this->assertSame([], $failures, implode("\n", $failures));
    }

    public function test_no_permission_user_mutations_are_rejected_without_side_effects(): void
    {
        $nobody = $this->userWithPermissions([]);
        $role = Role::factory()->create(['permissions' => []]);

        $this->actingAs($nobody)->put(route('roles.update', $role), ['name' => 'Hacked', 'permissions' => Role::allPermissions()])->assertForbidden();
        $this->actingAs($nobody)->put(route('users.update', $nobody), [
            'name' => 'x', 'email' => $nobody->email, 'role_id' => Role::query()->where('is_super', true)->value('id') ?? Role::factory()->owner()->create()->id, 'locale' => 'en', 'is_active' => 1,
        ])->assertForbidden();
        $this->actingAs($nobody)->post(route('cash-floats.store'), ['user_id' => $nobody->id, 'amount' => 1000])->assertForbidden();

        $this->assertSame([], $role->refresh()->permissions);
        $this->assertFalse($nobody->refresh()->isOwner());
        $this->assertSame(0, CashFloat::query()->count());
    }

    // ---------------------------------------------------------------------
    // Sidebar navigation
    // ---------------------------------------------------------------------

    /**
     * @return array{links: list<string>, sequence: list<string>}
     */
    private function sidebar(string $html): array
    {
        preg_match('#<nav class="nav"[^>]*>(.*?)</nav>#s', $html, $m);
        $nav = $m[1] ?? '';
        preg_match_all('#<div class="nav-group">|<a [^>]*href="([^"]+)"#', $nav, $all, PREG_SET_ORDER);

        $sequence = array_map(fn ($match) => isset($match[1]) ? 'link' : 'group', $all);
        $links = array_values(array_filter(array_map(fn ($match) => $match[1] ?? null, $all)));

        return ['links' => $links, 'sequence' => $sequence];
    }

    private function assertSidebarConsistent(User $user, string $label): void
    {
        $html = $this->actingAs($user)->get(route('dashboard'))->assertOk()->getContent();
        ['links' => $links, 'sequence' => $sequence] = $this->sidebar($html);

        $this->assertNotEmpty($links, "{$label}: sidebar empty");

        foreach ($sequence as $i => $kind) {
            if ($kind === 'group') {
                $this->assertSame('link', $sequence[$i + 1] ?? null, "{$label}: empty group heading at position {$i}");
            }
        }

        foreach ($links as $href) {
            $status = $this->actingAs($user)->get(html_entity_decode($href))->getStatusCode();
            $this->assertContains($status, [200, 302], "{$label}: sidebar link {$href} -> {$status}");
        }
    }

    public function test_sidebar_only_shows_openable_links_for_every_single_permission_role(): void
    {
        User::factory()->owner()->create();

        $this->assertSidebarConsistent($this->userWithPermissions([]), '[none]');

        foreach (Role::allPermissions() as $permission) {
            $this->assertSidebarConsistent($this->userWithPermissions([$permission]), "[{$permission}]");
        }
    }

    public function test_sidebar_for_owner_and_manager(): void
    {
        $this->assertSidebarConsistent(User::factory()->owner()->create(), '[owner]');
        $this->assertSidebarConsistent(User::factory()->create(), '[manager]');
    }

    public function test_sidebar_shows_index_link_when_permission_allows_it(): void
    {
        $user = $this->userWithPermissions(['parties.manage']);
        $html = $this->actingAs($user)->get(route('dashboard'))->getContent();
        ['links' => $links, 'sequence' => $sequence] = $this->sidebar($html);

        $this->assertContains(route('parties.index'), $links);
        $this->assertSame(['link', 'group', 'link'], $sequence);
    }

    /**
     * The dashboard only links to machines and paper stock for users who may open them.
     */
    public function test_dashboard_links_for_no_permission_user_never_lead_to_403(): void
    {
        User::factory()->owner()->create();
        Machine::factory()->create();
        PaperType::factory()->create(['reorder_level' => 1000]);
        $nobody = $this->userWithPermissions([]);

        $html = $this->actingAs($nobody)->get(route('dashboard'))->assertOk()->getContent();
        preg_match_all('#href="(http://localhost[^"]*)"#', $html, $m);

        $bad = [];
        foreach (array_unique($m[1]) as $href) {
            $status = $this->actingAs($nobody)->get(html_entity_decode($href))->getStatusCode();
            if ($status === 403) {
                $bad[] = $href;
            }
        }

        $this->assertSame([], $bad, "Dashboard shows links that 403:\n".implode("\n", $bad));
    }

    /**
     * Suspicious design: the dashboard has no permission gate, so a user with no permissions sees
     * company-wide cash, machine spend and the pending-approval list. Documented, not asserted as failure.
     */
    public function test_dashboard_is_visible_to_no_permission_user(): void
    {
        $nobody = $this->userWithPermissions([]);
        $boss = User::factory()->owner()->create();
        CashFloat::factory()->create(['user_id' => $boss->id, 'amount' => 123456]);

        $this->actingAs($nobody)->get(route('dashboard'))->assertOk()->assertSee('123,456');
    }
}
