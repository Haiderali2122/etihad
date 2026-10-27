<?php

namespace Tests\Feature;

use App\Models\Designation;
use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DesignationTest extends TestCase
{
    use RefreshDatabase;

    public function test_designation_pages_need_the_staff_manage_permission(): void
    {
        $owner = User::factory()->owner()->create();
        $viewer = User::factory()->create(['role_id' => Role::factory()->create(['permissions' => ['staff.view']])->id]);
        $designation = Designation::factory()->create();

        foreach ([route('designations.index'), route('designations.create'), route('designations.edit', $designation)] as $url) {
            $this->actingAs($owner)->get($url)->assertOk();
            $this->actingAs($viewer)->get($url)->assertForbidden();
        }
    }

    public function test_create_update_and_delete_an_unused_designation(): void
    {
        $owner = User::factory()->owner()->create();

        $this->actingAs($owner)->post(route('designations.store'), ['name' => 'Supervisor', 'name_ur' => 'سپروائزر', 'is_active' => 1])
            ->assertRedirect(route('designations.index'));
        $designation = Designation::query()->where('name', 'Supervisor')->sole();

        $this->actingAs($owner)->post(route('designations.store'), ['name' => 'Supervisor', 'is_active' => 1])->assertSessionHasErrors('name');

        $this->actingAs($owner)->put(route('designations.update', $designation), ['name' => 'Shift supervisor', 'is_active' => 0])
            ->assertSessionHasNoErrors();
        $this->assertSame('Shift supervisor', $designation->refresh()->name);
        $this->assertFalse($designation->is_active);

        $this->actingAs($owner)->delete(route('designations.destroy', $designation))->assertRedirect(route('designations.index'));
        $this->assertModelMissing($designation);
    }

    public function test_renaming_a_designation_renames_it_on_its_employees(): void
    {
        $owner = User::factory()->owner()->create();
        $designation = Designation::factory()->create(['name' => 'Binder']);
        $binder = Employee::factory()->create(['designation' => 'Binder']);
        $helper = Employee::factory()->create(['designation' => 'Helper']);

        $this->actingAs($owner)->put(route('designations.update', $designation), ['name' => 'Book binder', 'is_active' => 1]);

        $this->assertSame('Book binder', $binder->refresh()->designation);
        $this->assertSame('Helper', $helper->refresh()->designation);
    }

    public function test_a_designation_held_by_staff_cannot_be_deleted(): void
    {
        $owner = User::factory()->owner()->create();
        $designation = Designation::factory()->create(['name' => 'Cutter']);
        Employee::factory()->create(['designation' => 'Cutter']);

        $this->actingAs($owner)->delete(route('designations.destroy', $designation))->assertSessionHasErrors('designation');
        $this->assertModelExists($designation);
    }

    public function test_employee_form_offers_active_designations_and_requires_one_from_the_list(): void
    {
        $owner = User::factory()->owner()->create();
        Designation::factory()->create(['name' => 'Printer']);
        Designation::factory()->create(['name' => 'Old title', 'is_active' => false]);

        $this->actingAs($owner)->get(route('employees.create'))->assertOk()->assertSee('Printer')->assertDontSee('Old title');

        $payload = ['code' => 'E-100', 'name' => 'Asad', 'monthly_salary' => 30000, 'is_active' => 1];
        $this->actingAs($owner)->post(route('employees.store'), $payload + ['designation' => 'Astronaut'])->assertSessionHasErrors('designation');
        $this->actingAs($owner)->post(route('employees.store'), $payload + ['designation' => 'Printer'])->assertSessionHasNoErrors();
        $this->assertSame('Printer', Employee::query()->where('code', 'E-100')->sole()->designation);
    }

    public function test_staff_list_links_to_each_profile(): void
    {
        $owner = User::factory()->owner()->create();
        $employee = Employee::factory()->create();

        $this->actingAs($owner)->get(route('employees.index'))->assertOk()
            ->assertSee('data-href="'.route('employees.show', $employee).'"', false);
        $this->actingAs($owner)->get(route('employees.show', $employee))->assertOk()->assertSee($employee->name);
    }
}
