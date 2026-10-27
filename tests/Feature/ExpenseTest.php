<?php

namespace Tests\Feature;

use App\Enums\ApprovalStatus;
use App\Models\Expense;
use App\Models\ExpenseHead;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpenseTest extends TestCase
{
    use RefreshDatabase;

    private function spend(User $user, int $amount): Expense
    {
        $this->actingAs($user)->post(route('expenses.store'), [
            'spent_on' => today()->toDateString(),
            'expense_head_id' => ExpenseHead::factory()->create()->id,
            'paid_to' => 'Shell, Ravi Road',
            'amount' => $amount,
            'reason' => 'Petrol for van',
        ])->assertSessionHasNoErrors();

        return Expense::query()->latest('id')->first();
    }

    public function test_spends_within_the_daily_limit_are_approved_and_beyond_it_wait(): void
    {
        Setting::put(Setting::ManagerDailyLimit, 10000);
        $manager = User::factory()->create();

        $this->assertSame(ApprovalStatus::Approved, $this->spend($manager, 6000)->status);
        $this->assertSame(ApprovalStatus::Approved, $this->spend($manager, 4000)->status);
        $this->assertSame(ApprovalStatus::Pending, $this->spend($manager, 1)->status);
    }

    public function test_without_a_limit_every_manager_spend_waits(): void
    {
        $this->assertSame(ApprovalStatus::Pending, $this->spend(User::factory()->create(), 100)->status);
    }

    public function test_cash_in_hand_is_given_minus_spent(): void
    {
        Setting::put(Setting::ManagerDailyLimit, 50000);
        $owner = User::factory()->owner()->create();
        $manager = User::factory()->create();

        $this->actingAs($owner)->post(route('cash-floats.store'), [
            'given_on' => today()->toDateString(), 'user_id' => $manager->id, 'amount' => 100000,
        ])->assertSessionHasNoErrors();

        $this->spend($manager, 36230);

        $this->assertSame(63770, $manager->cashInHand());
    }

    public function test_only_the_owner_gives_cash(): void
    {
        $manager = User::factory()->create();

        $this->actingAs($manager)->post(route('cash-floats.store'), [
            'given_on' => today()->toDateString(), 'user_id' => $manager->id, 'amount' => 5000,
        ])->assertForbidden();
    }
}
