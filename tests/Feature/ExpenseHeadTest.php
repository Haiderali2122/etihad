<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\ExpenseHead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpenseHeadTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_manages_heads_and_manager_cannot(): void
    {
        $owner = User::factory()->owner()->create();
        $manager = User::factory()->create();
        $head = ExpenseHead::factory()->create();

        foreach ([route('expense-heads.index'), route('expense-heads.create'), route('expense-heads.edit', $head)] as $url) {
            $this->actingAs($owner)->get($url)->assertOk();
            $this->actingAs($manager)->get($url)->assertForbidden();
        }
    }

    public function test_create_and_update_a_head(): void
    {
        $owner = User::factory()->owner()->create();

        $this->actingAs($owner)->post(route('expense-heads.store'), [
            'name' => 'Generator', 'name_ur' => 'جنریٹر', 'color' => '#5A9C7D', 'is_active' => 1,
        ])->assertRedirect(route('expense-heads.index'));

        $head = ExpenseHead::query()->where('name', 'Generator')->sole();
        $this->assertSame('جنریٹر', $head->name_ur);

        $this->actingAs($owner)->put(route('expense-heads.update', $head), [
            'name' => 'Generator fuel', 'color' => '#C2412D', 'is_active' => 0,
        ])->assertSessionHasNoErrors();

        $head->refresh();
        $this->assertSame('Generator fuel', $head->name);
        $this->assertFalse($head->is_active);
    }

    public function test_names_are_unique_and_colour_must_be_hex(): void
    {
        $owner = User::factory()->owner()->create();
        ExpenseHead::factory()->create(['name' => 'Petrol']);

        $this->actingAs($owner)->post(route('expense-heads.store'), ['name' => 'Petrol', 'color' => 'red'])
            ->assertSessionHasErrors(['name', 'color']);
    }

    public function test_a_head_with_expenses_cannot_be_deleted(): void
    {
        $owner = User::factory()->owner()->create();
        $used = Expense::factory()->create()->head;
        $unused = ExpenseHead::factory()->create();

        $this->actingAs($owner)->delete(route('expense-heads.destroy', $used))->assertSessionHasErrors('head');
        $this->assertModelExists($used);

        $this->actingAs($owner)->delete(route('expense-heads.destroy', $unused))->assertRedirect(route('expense-heads.index'));
        $this->assertModelMissing($unused);
    }

    public function test_inactive_heads_are_hidden_from_new_expenses(): void
    {
        $manager = User::factory()->create();
        $active = ExpenseHead::factory()->create(['name' => 'Diesel']);
        $inactive = ExpenseHead::factory()->create(['name' => 'Old head', 'is_active' => false]);

        $this->actingAs($manager)->get(route('expenses.create'))->assertSee('Diesel')->assertDontSee('Old head');

        $this->actingAs($manager)->post(route('expenses.store'), [
            'spent_on' => today()->toDateString(), 'expense_head_id' => $inactive->id,
            'paid_to' => 'Depot', 'amount' => 100, 'reason' => 'Test',
        ])->assertSessionHasErrors('expense_head_id');
    }
}
